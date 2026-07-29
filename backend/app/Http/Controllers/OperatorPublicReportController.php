<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PublicIncidentReport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
class OperatorPublicReportController extends Controller
{
    public function index()
    {
        $reports = PublicIncidentReport::latest()
            ->get()
            ->map(function ($report) {
                return [
                    'id' => $report->id,
                    'mobile_app_user_id' => $report->mobile_app_user_id,
                    'brunei_identity_id' => $report->brunei_identity_id,
                    'reporter_ic_no' => $report->reporter_ic_no,
                    'reporter_full_name' => $report->reporter_full_name,
                    'district' => $report->district,
                    'incident_type' => $report->incident_type,
                    'description' => $report->description,
                    'latitude' => $report->latitude,
                    'longitude' => $report->longitude,
                    'photo_path' => $report->photo_path,
                    'photo_url' => $report->photo_path
                        ? asset('storage/' . $report->photo_path)
                        : null,
                    'status' => $report->status,
                    'operator_notes' => $report->operator_notes,
                    'verified_by' => $report->verified_by,
                    'verified_at' => $report->verified_at,
                    'created_at' => $report->created_at,
                    'updated_at' => $report->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:VERIFIED,NEEDS_VERIFY,REJECTED'],
            'operator_notes' => ['nullable', 'string'],
        ]);

        $report = PublicIncidentReport::findOrFail($id);

        if ($report->status === 'VERIFIED') {
            return response()->json([
                'message' => 'This public report has already been verified and submitted to BRAVE.',
            ], 422);
        }

        $report->update([
            'status' => $validated['status'],
            'operator_notes' => $validated['operator_notes'] ?? null,
            'verified_at' => $validated['status'] === 'VERIFIED' ? now() : null,
        ]);

        if ($validated['status'] === 'VERIFIED') {
            return $this->submitToBraveDashboard($report->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Public report status updated.',
            'data' => $report,
        ]);
    }

    public function submitToBraveDashboard(int $id): JsonResponse
    {
        $report = PublicIncidentReport::findOrFail($id);

        if ($report->status !== 'VERIFIED') {
            return response()->json([
                'message' => 'Only verified public reports can be submitted to BRAVE.',
            ], 422);
        }

        if (!empty($report->submitted_to_brave)) {
            return response()->json([
                'message' => 'This public report has already been submitted to BRAVE.',
            ], 422);
        }

        $attributes = [
            'categories' => $report->incident_type,
            'severity_level' => 'Public Verified',
            'district' => $report->district,
            'more_details' => $report->description,
            'datetime_reported' => now()->timestamp * 1000,
            'resolved' => 'No',
            'unresolved' => 'Yes',
        ];

        $feature = [
            'attributes' => $attributes,
        ];

        if ($report->latitude && $report->longitude) {
            $feature['geometry'] = [
                'x' => (float) $report->longitude,
                'y' => (float) $report->latitude,
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
                'message' => 'Failed to submit public report to BRAVE.',
                'details' => $json,
            ], 500);
        }

        $objectId = $result['objectId'] ?? null;

        $report->update([
            'submitted_to_brave' => 1,
            'brave_incident_objectid' => $objectId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Public report verified and submitted to BRAVE successfully.',
            'objectId' => $objectId,
        ]);
    }

    private function arcgisHttp()
    {
        return Http::asForm()->withoutVerifying();
    }

    private function featureLayerUrl(): string
    {
        return config('services.arcgis.fire_incident_layer_url');
    }
}