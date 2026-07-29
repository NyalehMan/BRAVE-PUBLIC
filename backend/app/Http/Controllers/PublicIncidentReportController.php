<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MobileAppUser;
use App\Models\PublicIncidentReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class PublicIncidentReportController extends Controller
{
   private function getMobileUserFromToken(Request $request)
    {
        $token = $request->bearerToken();
        $hashedToken = $token ? hash('sha256', $token) : null;

        $mobileUser = $hashedToken
            ? MobileAppUser::where('api_token', $hashedToken)->first()
            : null;

        $identity = $mobileUser
            ? \App\Models\BruneiIdentity::find($mobileUser->brunei_identity_id)
            : null;

        if (!$mobileUser) {
            return null;
        }

        $mobileUser->setRelation('identity', $identity);

        return $mobileUser;
    }

    public function store(Request $request)
    {
        $mobileUser = $this->getMobileUserFromToken($request);

        if (!$mobileUser || !$mobileUser->identity) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'location' => ['required', 'string', 'max:80'],
            'incident_type' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
        ]);

        $photoPath = null;


        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('public_reports', 'public');
        }

        $report = PublicIncidentReport::create([
            'mobile_app_user_id' => $mobileUser->id,
            'brunei_identity_id' => $mobileUser->brunei_identity_id,
            'reporter_ic_no' => $mobileUser->identity->ic_no,
            'reporter_full_name' => $mobileUser->identity->full_name,

            'district' => $validated['location'],
            'incident_type' => $validated['incident_type'],
            'description' => $validated['description'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'photo_path' => $photoPath,

            'status' => 'PENDING',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Public incident report submitted for operator verification.',
            'data' => $report,
        ], 201);
    }

    public function myReports(Request $request)
    {
        $mobileUser = $this->getMobileUserFromToken($request);

        if (!$mobileUser) {
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
            ->map(function ($report) use ($request) {
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
                    ? 'https://' . $request->getHost() . '/storage/' . $report->photo_path
                        : null,
                ];
                
            });

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }
}