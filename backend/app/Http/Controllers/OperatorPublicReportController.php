<?php

namespace App\Http\Controllers;

use App\Models\PublicIncidentReport;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class OperatorPublicReportController extends Controller
{
    private const STATUSES = [
        'PENDING',
        'NEEDS_VERIFY',
        'VERIFIED',
        'REJECTED',
    ];

    public function index(Request $request): JsonResponse
    {
        $request->merge([
            'status' => strtoupper((string) $request->query('status', 'PENDING')),
        ]);

        $validated = $request->validate([
            'status' => ['required', Rule::in([...self::STATUSES, 'ALL'])],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = $validated['status'];
        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 25);
        $reportId = preg_match('/^(?:report\s*#?\s*)?(\d+)$/i', $search, $matches) === 1
            ? (int) $matches[1]
            : null;

        $query = PublicIncidentReport::query()
            ->when(
                $status !== 'ALL',
                fn ($query) => $query->where('status', $status)
            )
            ->when($search !== '', function ($query) use ($reportId, $search): void {
                $query->where(function ($query) use ($reportId, $search): void {
                    $query
                        ->where('incident_type', 'like', "%{$search}%")
                        ->orWhere('district', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('reporter_full_name', 'like', "%{$search}%")
                        ->orWhere('reporter_ic_no', 'like', "%{$search}%");

                    if ($reportId !== null) {
                        $query->orWhere('id', $reportId);
                    }
                });
            })
            ->latest();

        $paginator = $query->paginate($perPage);
        $reports = $paginator->getCollection()
            ->map(fn (PublicIncidentReport $report): array => $this->reportData($report))
            ->values();

        $statusCounts = array_fill_keys(self::STATUSES, 0);

        foreach (
            PublicIncidentReport::query()
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status') as $reportStatus => $count
        ) {
            if (array_key_exists($reportStatus, $statusCounts)) {
                $statusCounts[$reportStatus] = (int) $count;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $reports,
            'reports' => $reports,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'status_counts' => $statusCounts,
            'pending_report_ids' => PublicIncidentReport::query()
                ->where('status', 'PENDING')
                ->latest()
                ->limit(200)
                ->pluck('id')
                ->values(),
        ]);
    }

    public function show($id): JsonResponse
    {
        $report = PublicIncidentReport::findOrFail($id);
        $data = $this->reportData($report);

        return response()->json([
            'success' => true,
            'data' => $data,
            'report' => $data,
        ]);
    }

    public function photo($id): StreamedResponse
    {
        $report = PublicIncidentReport::findOrFail($id);
        $path = str_replace('\\', '/', (string) $report->photo_path);
        $disk = Storage::disk(
            config('filesystems.report_photos_disk', 'local')
        );

        abort_unless(
            preg_match('#^public_reports/[A-Za-z0-9._-]+$#', $path) === 1
            && $disk->exists($path),
            404
        );

        return $disk->response(
            $path,
            null,
            [
                'Cache-Control' => 'private, no-store, max-age=0',
                'Content-Disposition' => 'inline',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function updateStatus(
        Request $request,
        $id
    ): JsonResponse {
        $request->merge([
            'status' => strtoupper(
                (string) $request->input('status')
            ),
        ]);

        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'VERIFIED',
                    'NEEDS_VERIFY',
                    'REJECTED',
                ]),
            ],
            'operator_notes' => [
                'required_if:status,NEEDS_VERIFY',
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        try {
            return Cache::lock("brave:public-report:decision:{$id}", 180)
                ->block(10, function () use ($id, $request, $validated): JsonResponse {
                    $report = PublicIncidentReport::findOrFail($id);

                    return $this->applyDecision(
                        $report,
                        $validated['status'],
                        $validated['operator_notes'] ?? null,
                        $request->user()?->id
                    );
                });
        } catch (LockTimeoutException) {
            return response()->json([
                'success' => false,
                'message' => 'Another reviewer is currently updating this report. Please try again.',
            ], 409);
        }
    }

    public function update(
        Request $request,
        $id
    ): JsonResponse {
        return $this->updateStatus($request, $id);
    }

    public function verify(
        Request $request,
        $id
    ): JsonResponse {
        $request->merge([
            'status' => 'VERIFIED',
        ]);

        return $this->updateStatus($request, $id);
    }

    public function approve(
        Request $request,
        $id
    ): JsonResponse {
        return $this->verify($request, $id);
    }

    public function reject(
        Request $request,
        $id
    ): JsonResponse {
        $request->merge([
            'status' => 'REJECTED',
        ]);

        return $this->updateStatus($request, $id);
    }

    public function submitToArcgis(
        Request $request,
        $id
    ): JsonResponse {
        return $this->verify($request, $id);
    }

    public function retryArcgis(
        Request $request,
        $id
    ): JsonResponse {
        return $this->verify($request, $id);
    }

    private function applyDecision(
        PublicIncidentReport $report,
        string $status,
        ?string $operatorNotes,
        ?int $reviewerId
    ): JsonResponse {
        if (
            $status !== 'VERIFIED'
            && $this->existingArcgisObjectId($report) !== null
        ) {
            return response()->json([
                'success' => false,
                'message' => 'A report already submitted to BRAVE cannot be moved back to an unverified status.',
                'data' => $this->reportData($report),
            ], 409);
        }

        if ($status !== 'VERIFIED') {
            $report->status = $status;
            $report->operator_notes = $operatorNotes;
            $report->verified_by = $reviewerId;
            $report->verified_at = null;
            $report->save();

            return response()->json([
                'success' => true,
                'message' => $status === 'REJECTED'
                    ? 'Public report rejected.'
                    : 'Public report marked as needing verification.',
                'data' => $this->reportData(
                    $report->fresh()
                ),
            ]);
        }

        $objectId = $this->existingArcgisObjectId(
            $report
        );

        if ($objectId === null) {
            try {
                $objectId = $this->createArcgisFeature(
                    $report
                );
            } catch (Throwable $exception) {
                $this->recordArcgisFailure(
                    $report,
                    $exception->getMessage()
                );

                Log::error(
                    'BRAVE public report ArcGIS submission failed.',
                    [
                        'public_report_id' => $report->id,
                        'message' => $exception->getMessage(),
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' => 'The report was not submitted to ArcGIS.',
                    'error' => $exception->getMessage(),
                    'data' => $this->reportData(
                        $report->fresh()
                    ),
                ], 502);
            }
        }

        $report->status = 'VERIFIED';
        $report->operator_notes = $operatorNotes;
        $report->verified_by = $reviewerId;
        $report->verified_at = now();

        $this->setOptionalColumns(
            $report,
            [
                'arcgis_object_id' => $objectId,
                'arcgis_feature_object_id' => $objectId,
                'brave_incident_objectid' => $objectId,
                'arcgis_submitted_at' => now(),
                'submitted_to_arcgis_at' => now(),
                'arcgis_submission_error' => null,
                'arcgis_error' => null,
                'submitted_to_arcgis' => true,
                'is_submitted_to_arcgis' => true,
                'submitted_to_brave' => true,
            ]
        );

        $report->save();

        return response()->json([
            'success' => true,
            'message' => 'Public report verified and submitted to BRAVE successfully.',
            'data' => $this->reportData(
                $report->fresh()
            ),
        ]);
    }

    private function createArcgisFeature(
        PublicIncidentReport $report
    ): int {
        $layerUrl = rtrim(
            (string) config(
                'services.arcgis.fire_incident_layer_url'
            ),
            '/'
        );

        $token = (string) config(
            'services.arcgis.token'
        );

        if ($layerUrl === '') {
            throw new RuntimeException(
                'ARCGIS_FIRE_INCIDENT_LAYER_URL is missing.'
            );
        }

        if ($token === '') {
            throw new RuntimeException(
                'ARCGIS_TOKEN is missing.'
            );
        }

        $metadata = $this->arcgisLayerMetadata($layerUrl, $token);
        $fields = $metadata['fields'];
        $globalIdField = $this->findArcgisFieldByType(
            $fields,
            'esriFieldTypeGlobalID'
        );
        $objectIdField = is_string($metadata['objectIdField'] ?? null)
            ? $metadata['objectIdField']
            : $this->findArcgisFieldByType($fields, 'esriFieldTypeOID');

        if (! $globalIdField || ! $objectIdField) {
            throw new RuntimeException(
                'The ArcGIS layer must expose Object ID and Global ID fields for idempotent submissions.'
            );
        }

        $globalId = $this->arcgisSubmissionGlobalId($report);
        $existingObjectId = $this->findArcgisObjectIdByGlobalId(
            $layerUrl,
            $token,
            $globalIdField,
            $objectIdField,
            $globalId
        );

        if ($existingObjectId !== null) {
            return $existingObjectId;
        }

        $attributes = $this->buildArcgisAttributes(
            $report,
            $fields
        );
        $attributes[$globalIdField] = $globalId;

        $feature = [
            'attributes' => $attributes,
            'geometry' => [
                'x' => (float) $report->longitude,
                'y' => (float) $report->latitude,
                'spatialReference' => [
                    'wkid' => 4326,
                ],
            ],
        ];

        try {
            $response = $this->arcgisHttp()->post(
                $layerUrl.'/addFeatures',
                [
                    'f' => 'json',
                    'token' => $token,
                    'rollbackOnFailure' => 'true',
                    'useGlobalIds' => 'true',
                    'features' => json_encode(
                        [$feature],
                        JSON_THROW_ON_ERROR
                    ),
                ]
            );
        } catch (Throwable $exception) {
            $recoveredObjectId = $this->recoverArcgisObjectId(
                $layerUrl,
                $token,
                $globalIdField,
                $objectIdField,
                $globalId
            );

            if ($recoveredObjectId !== null) {
                return $recoveredObjectId;
            }

            throw $exception;
        }

        $payload = $response->json() ?? [];

        if (
            ! $response->successful() ||
            isset($payload['error'])
        ) {
            $recoveredObjectId = $this->recoverArcgisObjectId(
                $layerUrl,
                $token,
                $globalIdField,
                $objectIdField,
                $globalId
            );

            if ($recoveredObjectId !== null) {
                return $recoveredObjectId;
            }

            throw new RuntimeException(
                $this->arcgisErrorMessage(
                    $payload,
                    $response->status()
                )
            );
        }

        $result = $payload['addResults'][0] ?? null;

        if (
            ! is_array($result) ||
            empty($result['success'])
        ) {
            $recoveredObjectId = $this->recoverArcgisObjectId(
                $layerUrl,
                $token,
                $globalIdField,
                $objectIdField,
                $globalId
            );

            if ($recoveredObjectId !== null) {
                return $recoveredObjectId;
            }

            $message =
                $result['error']['description']
                ?? $result['error']['message']
                ?? 'ArcGIS rejected the new feature.';

            throw new RuntimeException($message);
        }

        $objectId = $result['objectId'] ?? null;

        if (! is_numeric($objectId)) {
            throw new RuntimeException(
                'ArcGIS did not return an object ID.'
            );
        }

        return (int) $objectId;
    }

    /** @return array<string, mixed> */
    private function arcgisLayerMetadata(string $layerUrl, string $token): array
    {
        $response = $this->arcgisHttp()->get(
            $layerUrl,
            [
                'f' => 'json',
                'token' => $token,
            ]
        );

        $payload = $response->json() ?? [];

        if (
            ! $response->successful()
            || isset($payload['error'])
            || ! is_array($payload['fields'] ?? null)
            || $payload['fields'] === []
        ) {
            throw new RuntimeException(
                isset($payload['error'])
                    ? $this->arcgisErrorMessage($payload, $response->status())
                    : 'ArcGIS layer fields could not be read.'
            );
        }

        return $payload;
    }

    private function arcgisSubmissionGlobalId(PublicIncidentReport $report): string
    {
        $uuid = trim((string) $report->arcgis_submission_uuid, '{}');

        if (! Str::isUuid($uuid)) {
            $uuid = (string) Str::uuid();
            $report->arcgis_submission_uuid = $uuid;
            $report->save();
        }

        return '{'.strtoupper($uuid).'}';
    }

    private function findArcgisObjectIdByGlobalId(
        string $layerUrl,
        string $token,
        string $globalIdField,
        string $objectIdField,
        string $globalId
    ): ?int {
        $response = $this->arcgisHttp()->post(
            $layerUrl.'/query',
            [
                'f' => 'json',
                'token' => $token,
                'where' => $globalIdField." = '".$globalId."'",
                'outFields' => $objectIdField,
                'returnGeometry' => 'false',
                'resultRecordCount' => 1,
            ]
        );

        $payload = $response->json() ?? [];

        if (! $response->successful() || isset($payload['error'])) {
            throw new RuntimeException(
                $this->arcgisErrorMessage($payload, $response->status())
            );
        }

        $objectId = $payload['features'][0]['attributes'][$objectIdField] ?? null;

        return is_numeric($objectId) ? (int) $objectId : null;
    }

    private function recoverArcgisObjectId(
        string $layerUrl,
        string $token,
        string $globalIdField,
        string $objectIdField,
        string $globalId
    ): ?int {
        try {
            return $this->findArcgisObjectIdByGlobalId(
                $layerUrl,
                $token,
                $globalIdField,
                $objectIdField,
                $globalId
            );
        } catch (Throwable $exception) {
            Log::warning('Unable to recover an ArcGIS submission by Global ID.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function buildArcgisAttributes(
        PublicIncidentReport $report,
        array $fields
    ): array {
        [$severity, $description] =
            $this->splitSeverity(
                (string) $report->description
            );

        $categoryField = $this->findArcgisField(
            $fields,
            [
                'categories',
                'category',
                'incident type',
                'incident category',
                'fire type',
            ]
        );

        $districtField = $this->findArcgisField(
            $fields,
            [
                'district',
                'location',
            ]
        );

        $severityField = $this->findArcgisField(
            $fields,
            [
                'severity level',
                'severity',
                'urgency',
            ]
        );

        $detailsField = $this->findArcgisField(
            $fields,
            [
                'more details',
                'details',
                'description',
                'remarks',
                'notes',
            ]
        );

        $reportedAtField = $this->findArcgisField(
            $fields,
            [
                'datetime reported',
                'reported at',
                'report date',
                'date reported',
            ]
        );

        $resolvedField = $this->findArcgisField(
            $fields,
            [
                'resolved',
                'is resolved',
            ]
        );

        $unresolvedField = $this->findArcgisField(
            $fields,
            [
                'unresolved',
                'is unresolved',
            ]
        );

        if (
            ! $categoryField ||
            ! $districtField ||
            ! $detailsField
        ) {
            throw new RuntimeException(
                'ArcGIS fields for category, district, or details were not found.'
            );
        }

        $attributes = [
            $categoryField => $report->incident_type,
            $districtField => $report->district,
            $detailsField => $description,
        ];

        if ($severityField) {
            $attributes[$severityField] = $severity;
        }

        if ($reportedAtField) {
            $attributes[$reportedAtField] =
                $report->created_at->getTimestamp() * 1000;
        }

        if ($resolvedField) {
            $attributes[$resolvedField] = 'No';
        }

        if ($unresolvedField) {
            $attributes[$unresolvedField] = 'Yes';
        }

        return $attributes;
    }

    private function findArcgisField(
        array $fields,
        array $candidates
    ): ?string {
        $candidateNames = array_map(
            fn (string $value): string => $this->normaliseFieldName($value),
            $candidates
        );

        foreach ($fields as $field) {
            if (
                ! is_array($field) ||
                empty($field['name'])
            ) {
                continue;
            }

            $name = $this->normaliseFieldName(
                (string) $field['name']
            );

            $alias = $this->normaliseFieldName(
                (string) ($field['alias'] ?? '')
            );

            if (
                in_array(
                    $name,
                    $candidateNames,
                    true
                ) ||
                in_array(
                    $alias,
                    $candidateNames,
                    true
                )
            ) {
                return (string) $field['name'];
            }
        }

        return null;
    }

    private function findArcgisFieldByType(array $fields, string $type): ?string
    {
        foreach ($fields as $field) {
            if (
                is_array($field)
                && ($field['type'] ?? null) === $type
                && is_string($field['name'] ?? null)
            ) {
                return $field['name'];
            }
        }

        return null;
    }

    private function normaliseFieldName(
        string $value
    ): string {
        return strtolower(
            preg_replace(
                '/[^a-z0-9]+/i',
                '',
                $value
            ) ?? ''
        );
    }

    private function splitSeverity(
        string $description
    ): array {
        $pattern =
            '/^Observed urgency:\s*'.
            '(Low|Medium|High|Critical)\s*/i';

        if (
            preg_match(
                $pattern,
                $description,
                $matches
            ) === 1
        ) {
            $severity = ucfirst(
                strtolower($matches[1])
            );

            $cleanDescription = trim(
                preg_replace(
                    $pattern,
                    '',
                    $description
                ) ?? $description
            );

            return [
                $severity,
                $cleanDescription,
            ];
        }

        return [
            'Medium',
            trim($description),
        ];
    }

    private function arcgisHttp(): PendingRequest
    {
        return Http::asForm()
            ->withHeaders([
                'Referer' => (string) config(
                    'services.arcgis.referer'
                ),
            ])
            ->withOptions([
                'verify' => true,
            ]);
    }

    private function arcgisErrorMessage(
        array $payload,
        int $httpStatus
    ): string {
        $message =
            $payload['error']['message']
            ?? 'ArcGIS request failed with HTTP '.
                $httpStatus.
                '.';

        $details =
            $payload['error']['details']
            ?? [];

        if (
            is_array($details) &&
            $details !== []
        ) {
            $message .=
                ' '.
                implode(' ', $details);
        }

        return trim($message);
    }

    private function recordArcgisFailure(
        PublicIncidentReport $report,
        string $message
    ): void {
        $this->setOptionalColumns(
            $report,
            [
                'arcgis_submission_error' => $message,
                'arcgis_error' => $message,
                'submitted_to_arcgis' => false,
                'is_submitted_to_arcgis' => false,
                'submitted_to_brave' => false,
            ]
        );

        $report->save();
    }

    private function setOptionalColumns(
        PublicIncidentReport $report,
        array $values
    ): void {
        $table = $report->getTable();

        foreach ($values as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $report->setAttribute(
                    $column,
                    $value
                );
            }
        }
    }

    private function existingArcgisObjectId(
        PublicIncidentReport $report
    ): ?int {
        $columns = [
            'arcgis_object_id',
            'arcgis_feature_object_id',
            'brave_incident_objectid',
        ];

        foreach ($columns as $column) {
            $value = $report->getAttribute(
                $column
            );

            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    private function firstAttribute(
        PublicIncidentReport $report,
        array $columns
    ) {
        foreach ($columns as $column) {
            $value = $report->getAttribute(
                $column
            );

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function reportData(
        PublicIncidentReport $report
    ): array {
        $objectId = $this->existingArcgisObjectId(
            $report
        );

        $submittedAt = $this->firstAttribute(
            $report,
            [
                'arcgis_submitted_at',
                'submitted_to_arcgis_at',
            ]
        );

        $submissionError = $this->firstAttribute(
            $report,
            [
                'arcgis_submission_error',
                'arcgis_error',
            ]
        );

        return [
            'id' => $report->id,

            'reporter_full_name' => $report->reporter_full_name,

            'reporter_name' => $report->reporter_full_name,

            'reporter_ic_no' => $report->reporter_ic_no,

            'district' => $report->district,

            'location' => $report->district,

            'incident_type' => $report->incident_type,

            'description' => $report->description,

            'latitude' => $report->latitude,

            'longitude' => $report->longitude,

            'photo_path' => $report->photo_path,

            'photo_url' => $report->photo_path
                ? url("/api/operator/public-reports/{$report->id}/photo")
                : null,

            'status' => $report->status,

            'operator_notes' => $report->operator_notes,

            'created_at' => $report->created_at,

            'updated_at' => $report->updated_at,

            'verified_at' => $report->verified_at,

            'arcgis_object_id' => $objectId,

            'brave_incident_objectid' => $objectId,

            'arcgis_submitted_at' => $submittedAt,

            'arcgis_submission_error' => $submissionError,

            'submitted_to_arcgis' => $objectId !== null,

            'submitted_to_brave' => $objectId !== null,
        ];
    }
}
