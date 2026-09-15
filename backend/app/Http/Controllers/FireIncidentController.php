<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class FireIncidentController extends Controller
{
    private ?string $objectIdField = null;
    private ?array $layerMetadata = null;

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateFeaturePayload($request, true);

        $feature = [
            'attributes' => $validated['attributes'],
        ];

        if (!empty($validated['geometry'])) {
            $feature['geometry'] = $validated['geometry'];
        }

        return $this->applyEdits(
            'adds',
            [$feature],
            'addResults',
            'Fire incident created successfully.'
        );
    }

    public function update(
        Request $request,
        int $objectId
    ): JsonResponse {
        $validated = $this->validateFeaturePayload($request, false);

        try {
            $objectIdField = $this->objectIdFieldName();
        } catch (Throwable $exception) {
            return $this->configurationFailure($exception);
        }

        $feature = [
            'attributes' => array_merge(
                $validated['attributes'],
                [$objectIdField => $objectId]
            ),
        ];

        if (!empty($validated['geometry'])) {
            $feature['geometry'] = $validated['geometry'];
        }

        return $this->applyEdits(
            'updates',
            [$feature],
            'updateResults',
            'Fire incident updated successfully.'
        );
    }

    public function destroy(int $objectId): JsonResponse
    {
        try {
            $response = $this->arcgisHttp()->post(
                $this->featureLayerUrl() . '/applyEdits',
                [
                    'f' => 'json',
                    'token' => $this->arcgisToken(),
                    'deletes' => (string) $objectId,
                    'rollbackOnFailure' => 'true',
                ]
            );

            $payload = $response->json() ?? [];
            $result = $payload['deleteResults'][0] ?? null;

            if (
                !$response->successful()
                || isset($payload['error'])
                || !is_array($result)
                || empty($result['success'])
            ) {
                return response()->json([
                    'message' => 'Failed to delete fire incident.',
                    'error' => $this->arcgisErrorMessage(
                        $payload,
                        $response->status(),
                        is_array($result) ? $result : null
                    ),
                ], 502);
            }

            return response()->json([
                'message' => 'Fire incident deleted successfully.',
                'object_id' => $result['objectId'] ?? $objectId,
            ]);
        } catch (Throwable $exception) {
            return $this->configurationFailure($exception);
        }
    }

    public function route(Request $request): JsonResponse
    {
        $request->validate([
            'start.latitude' => ['required', 'numeric', 'between:-90,90'],
            'start.longitude' => ['required', 'numeric', 'between:-180,180'],
            'end.latitude' => ['required', 'numeric', 'between:-90,90'],
            'end.longitude' => ['required', 'numeric', 'between:-180,180'],
            'stops' => ['nullable', 'array', 'max:20'],
            'stops.*.latitude' => [
                'required_with:stops',
                'numeric',
                'between:-90,90',
            ],
            'stops.*.longitude' => [
                'required_with:stops',
                'numeric',
                'between:-180,180',
            ],
            'mode' => ['nullable', 'string'],
            'departureType' => ['nullable', 'in:now,depart_at'],
            'departureDate' => ['nullable', 'date_format:Y-m-d'],
            'departureTime' => ['nullable', 'date_format:H:i'],
            'optimizeOrder' => ['nullable', 'boolean'],
        ]);

        $features = [];

        $addStop = function (
            array $point,
            string $name,
            int $sequence
        ) use (&$features): void {
            $features[] = [
                'geometry' => [
                    'x' => (float) $point['longitude'],
                    'y' => (float) $point['latitude'],
                    'spatialReference' => ['wkid' => 4326],
                ],
                'attributes' => [
                    'Name' => $name,
                    'RouteName' => 'Route 1',
                    'Sequence' => $sequence,
                ],
            ];
        };

        $addStop($request->input('start'), 'Start', 1);

        foreach ($request->input('stops', []) as $index => $stop) {
            $addStop($stop, 'Stop ' . ($index + 1), $index + 2);
        }

        $addStop(
            $request->input('end'),
            'Destination',
            count($features) + 1
        );

        $travelMode = match ($request->input('mode', 'Driving Time')) {
            'Driving Distance' => 'Driving Distance',
            'Walking Time' => 'Walking Time',
            'Walking Distance' => 'Walking Distance',
            'Trucking Time' => 'Trucking Time',
            'Trucking Distance' => 'Trucking Distance',
            'Rural Driving Time' => 'Rural Driving Time',
            'Rural Driving Distance' => 'Rural Driving Distance',
            default => 'Driving Time',
        };

        try {
            $token = $this->arcgisToken();
        } catch (Throwable $exception) {
            return $this->configurationFailure($exception);
        }

        $payload = [
            'f' => 'json',
            'token' => $token,
            'stops' => json_encode(
                ['features' => $features],
                JSON_THROW_ON_ERROR
            ),
            'travelMode' => $travelMode,
            'returnRoutes' => 'true',
            'returnDirections' => 'true',
            'returnStops' => 'true',
            'outSR' => 4326,
            'findBestSequence' => $request->boolean('optimizeOrder')
                ? 'true'
                : 'false',
            'preserveFirstStop' => 'true',
            'preserveLastStop' => 'true',
            'ignoreInvalidLocations' => 'true',
        ];

        if ($request->input('departureType') === 'depart_at') {
            $timestamp = strtotime(
                $request->input('departureDate')
                . ' '
                . $request->input('departureTime')
            );

            if ($timestamp !== false) {
                $payload['startTime'] = $timestamp * 1000;
            }
        }

        try {
            $response = $this->arcgisHttp()->post(
                'https://route.arcgis.com/arcgis/rest/services/'
                . 'World/Route/NAServer/Route_World/solve',
                $payload
            );

            $responsePayload = $response->json() ?? [];

            if (
                !$response->successful()
                || isset($responsePayload['error'])
            ) {
                return response()->json([
                    'message' => 'ArcGIS could not calculate the route.',
                    'error' => $this->arcgisErrorMessage(
                        $responsePayload,
                        $response->status()
                    ),
                ], 502);
            }

            return response()->json($responsePayload);
        } catch (Throwable $exception) {
            return $this->configurationFailure($exception);
        }
    }

    private function validateFeaturePayload(
        Request $request,
        bool $creating
    ): array {
        $presence = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'attributes' => [
                'required',
                'array:district,categories,other_categories,'
                    . 'severity_level,more_details,datetime_reported,'
                    . 'resolved,unresolved',
            ],
            'attributes.district' => [$presence, 'string', 'max:100'],
            'attributes.categories' => [$presence, 'string', 'max:100'],
            'attributes.other_categories' => ['nullable', 'string', 'max:100'],
            'attributes.severity_level' => [$presence, 'string', 'max:30'],
            'attributes.more_details' => ['nullable', 'string', 'max:5000'],
            'attributes.datetime_reported' => ['nullable', 'numeric'],
            'attributes.resolved' => ['nullable', 'string', 'max:10'],
            'attributes.unresolved' => ['nullable', 'string', 'max:10'],
            'geometry' => ['nullable', 'array'],
            'geometry.x' => ['required_with:geometry', 'numeric', 'between:-180,180'],
            'geometry.y' => ['required_with:geometry', 'numeric', 'between:-90,90'],
            'geometry.spatialReference' => ['nullable', 'array'],
            'geometry.spatialReference.wkid' => ['nullable', 'integer'],
        ]);
    }

    private function applyEdits(
        string $operation,
        array $features,
        string $resultKey,
        string $successMessage
    ): JsonResponse {
        try {
            foreach ($features as &$feature) {
                $feature['attributes'] =
                    $this->filterAttributesForLayer(
                        $feature['attributes'] ?? []
                    );
            }
            unset($feature);

            $response = $this->arcgisHttp()->post(
                $this->featureLayerUrl() . '/applyEdits',
                [
                    'f' => 'json',
                    'token' => $this->arcgisToken(),
                    $operation => json_encode(
                        $features,
                        JSON_THROW_ON_ERROR
                    ),
                    'rollbackOnFailure' => 'true',
                ]
            );

            $payload = $response->json() ?? [];
            $result = $payload[$resultKey][0] ?? null;

            if (
                !$response->successful()
                || isset($payload['error'])
                || !is_array($result)
                || empty($result['success'])
            ) {
                return response()->json([
                    'message' => 'ArcGIS rejected the incident change.',
                    'error' => $this->arcgisErrorMessage(
                        $payload,
                        $response->status(),
                        is_array($result) ? $result : null
                    ),
                ], 502);
            }

            return response()->json([
                'message' => $successMessage,
                'object_id' => $result['objectId'] ?? null,
            ]);
        } catch (Throwable $exception) {
            return $this->configurationFailure($exception);
        }
    }

    private function objectIdFieldName(): string
    {
        if ($this->objectIdField !== null) {
            return $this->objectIdField;
        }

        $payload = $this->layerMetadata();

        $this->objectIdField =
            (string) ($payload['objectIdField'] ?? 'OBJECTID');

        return $this->objectIdField;
    }

    private function layerMetadata(): array
    {
        if ($this->layerMetadata !== null) {
            return $this->layerMetadata;
        }

        $response = $this->arcgisHttp()->get(
            $this->featureLayerUrl(),
            [
                'f' => 'json',
                'token' => $this->arcgisToken(),
            ]
        );

        $payload = $response->json() ?? [];

        if (
            !$response->successful()
            || isset($payload['error'])
        ) {
            throw new RuntimeException(
                $this->arcgisErrorMessage(
                    $payload,
                    $response->status()
                )
            );
        }

        $this->layerMetadata = $payload;

        return $this->layerMetadata;
    }

    private function filterAttributesForLayer(
        array $attributes
    ): array {
        $fields = $this->layerMetadata()['fields'] ?? [];
        $fieldMap = [];

        foreach ($fields as $field) {
            if (is_array($field) && !empty($field['name'])) {
                $actualName = (string) $field['name'];

                $fieldMap[$this->normaliseFieldName($actualName)] =
                    $actualName;

                if (!empty($field['alias'])) {
                    $fieldMap[
                        $this->normaliseFieldName(
                            (string) $field['alias']
                        )
                    ] = $actualName;
                }
            }
        }

        $filtered = [];

        foreach ($attributes as $name => $value) {
            $actualName = $fieldMap[
                $this->normaliseFieldName((string) $name)
            ]
                ?? null;

            if ($actualName !== null) {
                $filtered[$actualName] = $value;
            }
        }

        if ($filtered === []) {
            throw new RuntimeException(
                'No submitted attributes match the ArcGIS layer fields.'
            );
        }

        return $filtered;
    }

    private function normaliseFieldName(string $value): string
    {
        return strtolower(
            preg_replace('/[^a-z0-9]+/i', '', $value) ?? ''
        );
    }

    private function featureLayerUrl(): string
    {
        $url = rtrim(
            (string) config(
                'services.arcgis.fire_incident_layer_url'
            ),
            '/'
        );

        if ($url === '') {
            throw new RuntimeException(
                'ARCGIS_FIRE_INCIDENT_LAYER_URL is missing.'
            );
        }

        return $url;
    }

    private function arcgisToken(): string
    {
        $token = (string) config('services.arcgis.token');

        if ($token === '') {
            throw new RuntimeException('ARCGIS_TOKEN is missing.');
        }

        return $token;
    }

    private function arcgisHttp(): PendingRequest
    {
        return Http::asForm()
            ->withHeaders([
                'Referer' => (string) config(
                    'services.arcgis.referer'
                ),
            ])
            ->withOptions(['verify' => true])
            ->connectTimeout(5)
            ->timeout(30);
    }

    private function arcgisErrorMessage(
        array $payload,
        int $httpStatus,
        ?array $result = null
    ): string {
        $message = $result['error']['description']
            ?? $result['error']['message']
            ?? $payload['error']['message']
            ?? 'ArcGIS request failed with HTTP ' . $httpStatus . '.';

        $details = $payload['error']['details'] ?? [];

        if (is_array($details) && $details !== []) {
            $message .= ' ' . implode(' ', $details);
        }

        return trim($message);
    }

    private function configurationFailure(
        Throwable $exception
    ): JsonResponse {
        return response()->json([
            'message' => 'The BRAVE ArcGIS service is unavailable.',
            'error' => $exception->getMessage(),
        ], 502);
    }
}
