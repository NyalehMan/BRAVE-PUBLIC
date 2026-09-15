<?php

namespace App\Http\Controllers;

use App\Models\CadCall;
use App\Models\CadIncidentIntake;
use App\Models\CadValidationAnswer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class CadIntakeController extends Controller
{
    public function storeCall(Request $request): JsonResponse
    {
        $data = $request->validate([
            'caller_phone' => 'nullable|string|max:30',
            'caller_name' => 'nullable|string|max:150',
            'caller_id_type' => 'nullable|string|max:30',
            'caller_id_no' => 'nullable|string|max:80',
            'operator_name' => 'nullable|string|max:150',
        ]);

        $call = CadCall::create([
            ...$data,
            'call_ref' => 'CAD-'
                . now()->format('YmdHisv')
                . '-'
                . random_int(100, 999),
            'status' => 'OPEN',
            'call_started_at' => now(),
        ]);

        return response()->json($call);
    }

    public function storeIntake(Request $request): JsonResponse
    {
        $data = $request->validate([
            'call_id' => 'required|integer|exists:cad_calls,id',
            'incident_category' => 'required|string|max:100',
            'severity_level' => 'required|string|max:30',
            'district' => 'nullable|string|max:100',
            'location_description' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'more_details' => 'nullable|string',
            'answers' => 'nullable|array',
            'answers.*.question_text' => 'required|string|max:1000',
            'answers.*.answer' => 'nullable|string|max:5000',
            'answers.*.risk_score' => 'nullable|integer|between:-100,100',
        ]);

        return DB::transaction(function () use ($data) {
            $answers = $data['answers'] ?? [];
            unset($data['answers']);

            $intake = CadIncidentIntake::create([
                ...$data,
                'validation_status' => 'PENDING',
            ]);

            foreach ($answers as $answer) {
                CadValidationAnswer::create([
                    'intake_id' => $intake->id,
                    'question_text' => $answer['question_text'] ?? '',
                    'answer' => $answer['answer'] ?? '',
                    'risk_score' => $answer['risk_score'] ?? 0,
                ]);
            }

            return response()->json($intake->load('answers'));
        });
    }

    public function validateIntake(int $id): JsonResponse
    {
        $intake = CadIncidentIntake::with(['answers', 'call'])->findOrFail($id);

        $score = $intake->answers->sum('risk_score');

        $validationStatus = match (true) {
            $score >= 6 => 'VALID',
            $score >= 3 => 'NEEDS_VERIFY',
            default => 'REJECTED',
        };

        $suspiciousScore = 0;

        if ($intake->call?->caller_phone) {
            $previousCallCount = CadCall::where('caller_phone', $intake->call->caller_phone)
                ->where('id', '<>', $intake->call->id)
                ->count();

            $rejectedCallCount = CadCall::where('caller_phone', $intake->call->caller_phone)
                ->where('id', '<>', $intake->call->id)
                ->whereIn('status', ['REJECTED', 'SUSPICIOUS', 'FALSE_ALARM'])
                ->count();

            if ($previousCallCount >= 3) {
                $suspiciousScore += 3;
            }

            if ($rejectedCallCount >= 2) {
                $suspiciousScore += 5;
            }

            $intake->call->update([
                'previous_call_count' => $previousCallCount,
                'false_alarm_count' => $rejectedCallCount,
            ]);
        }

        $finalStatus = $validationStatus;

        if ($suspiciousScore >= 5 && $validationStatus === 'VALID') {
            $finalStatus = 'NEEDS_VERIFY';
        }

        if ($suspiciousScore >= 8) {
            $finalStatus = 'SUSPICIOUS';
        }

        $intake->update([
            'validation_status' => $finalStatus,
        ]);

        $intake->call?->update([
            'status' => $finalStatus === 'VALID' ? 'VALIDATED' : $finalStatus,
            'suspicious_score' => $suspiciousScore,
            'prank_flag' => $suspiciousScore >= 5 ? 1 : 0,
        ]);

        return response()->json([
            'message' => 'Intake validated.',
            'validation_status' => $finalStatus,
            'original_validation_status' => $validationStatus,
            'score' => $score,
            'suspicious_score' => $suspiciousScore,
            'prank_flag' => $suspiciousScore >= 5,
        ]);
    }

    public function submitToBrave(int $id): JsonResponse
    {
        $intake = CadIncidentIntake::with('call')->findOrFail($id);

        if ($intake->validation_status !== 'VALID') {
            return response()->json([
                'message' => 'Only valid incidents can be submitted to BRAVE.',
            ], 422);
        }

        if ($intake->submitted_to_brave) {
            return response()->json([
                'message' => 'This intake has already been submitted to BRAVE.',
            ], 422);
        }

        $attributes = [
            'categories' => $intake->incident_category,
            'severity_level' => $intake->severity_level,
            'district' => $intake->district,
            'more_details' => $intake->more_details ?: $intake->location_description,
            'datetime_reported' => now()->timestamp * 1000,
            'resolved' => 'No',
            'unresolved' => 'Yes',
        ];

        $feature = [
            'attributes' => $attributes,
        ];

        if ($intake->latitude && $intake->longitude) {
            $feature['geometry'] = [
                'x' => (float) $intake->longitude,
                'y' => (float) $intake->latitude,
                'spatialReference' => [
                    'wkid' => 4326,
                ],
            ];
        }

        try {
            $response = $this->arcgisHttp()->post(
                $this->featureLayerUrl() . '/applyEdits',
                [
                    'f' => 'json',
                    'token' => $this->arcgisToken(),
                    'adds' => json_encode(
                        [$feature],
                        JSON_THROW_ON_ERROR
                    ),
                    'rollbackOnFailure' => 'true',
                ]
            );
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Failed to submit CAD intake to BRAVE.',
                'error' => $exception->getMessage(),
            ], 502);
        }

        $json = $response->json() ?? [];

        $result = $json['addResults'][0] ?? null;

        if (
            !$response->successful()
            || isset($json['error'])
            || !is_array($result)
            || empty($result['success'])
        ) {
            return response()->json([
                'message' => 'Failed to submit CAD intake to BRAVE.',
                'error' => $this->arcgisErrorMessage(
                    $json,
                    $response->status(),
                    is_array($result) ? $result : null
                ),
            ], 502);
        }

        $objectId = $result['objectId'] ?? null;

        if (!is_numeric($objectId)) {
            return response()->json([
                'message' => 'ArcGIS did not return an object ID.',
            ], 502);
        }

        $intake->update([
            'submitted_to_brave' => 1,
            'brave_incident_objectid' => $objectId,
        ]);

        $intake->call?->update([
            'status' => 'SUBMITTED_TO_BRAVE',
        ]);

        return response()->json([
            'message' => 'CAD intake submitted to BRAVE successfully.',
            'objectId' => $objectId,
        ]);
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

    public function duplicateCheck(int $id): JsonResponse
    {
        $intake = CadIncidentIntake::findOrFail($id);

        if (!$intake->latitude || !$intake->longitude) {
            return response()->json([
                'duplicate' => false,
                'matches' => [],
                'message' => 'No coordinates available.',
            ]);
        }

        $where = "severity_level <> 'Resolved'";

        $geometry = json_encode([
            'x' => (float) $intake->longitude,
            'y' => (float) $intake->latitude,
            'spatialReference' => ['wkid' => 4326],
        ]);

        try {
            $response = $this->arcgisHttp()->post(
                $this->featureLayerUrl() . '/query',
                [
                    'f' => 'json',
                    'token' => $this->arcgisToken(),
                    'where' => $where,
                    'geometry' => $geometry,
                    'geometryType' => 'esriGeometryPoint',
                    'inSR' => 4326,
                    'spatialRel' => 'esriSpatialRelIntersects',
                    'distance' => 500,
                    'units' => 'esriSRUnit_Meter',
                    'outFields' => '*',
                    'returnGeometry' => 'true',
                ]
            );
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Duplicate check failed.',
                'error' => $exception->getMessage(),
            ], 502);
        }

        $payload = $response->json() ?? [];

        if (
            !$response->successful()
            || isset($payload['error'])
        ) {
            return response()->json([
                'message' => 'Duplicate check failed.',
                'error' => $this->arcgisErrorMessage(
                    $payload,
                    $response->status()
                ),
            ], 502);
        }

        $features = $payload['features'] ?? [];

        return response()->json([
            'duplicate' => count($features) > 0,
            'matches' => $features,
            'count' => count($features),
        ]);
    }

    private function calculateSuspiciousScore(CadCall $call): int
    {
        $score = 0;

        $previousCalls = CadCall::where('caller_phone', $call->caller_phone)
            ->where('id', '<>', $call->id)
            ->count();

        if ($previousCalls >= 3) {
            $score += 3;
        }

        $rejectedCalls = CadCall::where('caller_phone', $call->caller_phone)
            ->whereIn('status', ['REJECTED', 'SUSPICIOUS'])
            ->count();

        if ($rejectedCalls >= 2) {
            $score += 5;
        }

        return $score;
    }

    public function queue(): JsonResponse
    {
        $items = CadIncidentIntake::with('call')
            ->latest()
            ->limit(100)
            ->get();

        return response()->json($items);
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
}
