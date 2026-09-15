<?php

namespace App\Http\Controllers;

use App\Models\BruneiIdentity;
use App\Models\MobileAppUser;
use App\Models\PublicIncidentReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicIncidentReportController extends Controller
{
    private function getMobileUserFromToken(
        Request $request
    ): ?MobileAppUser {
        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        $mobileUser = MobileAppUser::where(
            'api_token',
            hash('sha256', $token)
        )->first();

        if (! $mobileUser) {
            return null;
        }

        $identity = BruneiIdentity::find(
            $mobileUser->brunei_identity_id
        );

        $mobileUser->setRelation('identity', $identity);

        return $mobileUser;
    }

    public function store(Request $request): JsonResponse
    {
        $mobileUser = $this->getMobileUserFromToken($request);

        $validated = $request->validate([
            'location' => [
                'required',
                'string',
                'max:80',
            ],

            'incident_type' => [
                'required',
                'string',
                'max:100',
            ],

            'severity_level' => [
                'nullable',
                'in:Low,Medium,High,Critical',
            ],

            'description' => [
                'required',
                'string',
                'max:5000',
            ],

            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],

            'photo' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:15360',
            ],
        ]);

        $photoPath = null;

        if ($request->hasFile('photo')) {
            $photoPath = $request
                ->file('photo')
                ->store('public_reports', 'local');
        }

        $hasMobileIdentity =
            $mobileUser !== null &&
            $mobileUser->identity !== null;

        $description = trim($validated['description']);

        if (! empty($validated['severity_level'])) {
            $description =
                'Observed urgency: '.
                $validated['severity_level'].
                PHP_EOL.
                PHP_EOL.
                $description;
        }

        $report = PublicIncidentReport::create([
            'mobile_app_user_id' => $hasMobileIdentity
                ? $mobileUser->id
                : null,

            'brunei_identity_id' => $hasMobileIdentity
                ? $mobileUser->brunei_identity_id
                : null,

            'reporter_ic_no' => $hasMobileIdentity
                ? $mobileUser->identity->ic_no
                : 'Not provided',

            'reporter_full_name' => $hasMobileIdentity
                ? $mobileUser->identity->full_name
                : 'Public Website User',

            'district' => $validated['location'],

            'incident_type' => $validated['incident_type'],

            'description' => $description,

            'latitude' => $validated['latitude'],

            'longitude' => $validated['longitude'],

            'photo_path' => $photoPath,

            'status' => 'PENDING',
        ]);

        return response()->json([
            'success' => true,

            'message' => 'Your report has been submitted for operator verification.',

            'data' => [
                'id' => $report->id,
                'status' => $report->status,
                'created_at' => $report->created_at,
            ],
        ], 201);
    }

    public function myReports(Request $request): JsonResponse
    {
        $mobileUser = $this->getMobileUserFromToken($request);

        if (! $mobileUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $reports = PublicIncidentReport::where(
            'brunei_identity_id',
            $mobileUser->brunei_identity_id
        )
            ->latest()
            ->get()
            ->map(function (
                PublicIncidentReport $report
            ): array {
                return [
                    'id' => $report->id,
                    'district' => $report->district,
                    'incident_type' => $report->incident_type,
                    'description' => $report->description,
                    'latitude' => $report->latitude,
                    'longitude' => $report->longitude,
                    'status' => $report->status,
                    'operator_notes' => $report->operator_notes,
                    'created_at' => $report->created_at,
                    'verified_at' => $report->verified_at,
                    'photo_path' => $report->photo_path,

                    'photo_url' => $report->photo_path
                        ? asset(
                            'storage/'.$report->photo_path
                        )
                        : null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }
}
