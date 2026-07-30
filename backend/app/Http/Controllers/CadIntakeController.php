<?php

namespace App\Http\Controllers;

use App\Models\CadCall;
use App\Models\CadIncidentIntake;
use App\Models\CadValidationAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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
            'call_ref' => 'CAD-' . now()->format('YmdHis') . '-' . rand(100, 999),
            'status' => 'OPEN',
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
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'more_details' => 'nullable|string',
            'answers' => 'nullable|array',
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

        $payload = [
            'f' => 'json',
            'token' => config('services.arcgis.token'),
            'adds' => json_encode([$feature]),
        ];

        $response = $this->arcgisHttp()->post(
            $this->featureLayerUrl() . '/applyEdits',
            $payload
        );

        $json = $response->json();

        $result = $json['addResults'][0] ?? null;

        if (!$result || empty($result['success'])) {
            return response()->json([
                'message' => 'Failed to submit CAD intake to BRAVE.',
                'details' => $json,
            ], 500);
        }

        $objectId = $result['objectId'] ?? null;

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

    private function arcgisHttp()
    {
        return Http::asForm()->withOptions(['verify' => true]);
    }

    private function featureLayerUrl(): string
    {
        return config('services.arcgis.fire_incident_layer_url');
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

        $response = $this->arcgisHttp()->post(
            $this->featureLayerUrl() . '/query',
            [
                'f' => 'json',
                'token' => config('services.arcgis.token'),
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

        $features = $response->json('features', []);

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
}