<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class PublicIncidentController extends Controller
{
    protected function arcgisHttp()
    {
        $http = Http::asForm();

        if (app()->environment('local')) {
            $http = Http::withOptions(['verify' => true])->asForm();
        }

        return $http;
    }

    protected function featureLayerUrl(): string
    {
        return rtrim(config('services.arcgis.fire_incident_layer_url'), '/');
    }

    public function ongoing(Request $request): JsonResponse
    {
        $userLat = $request->query('lat');
        $userLng = $request->query('lng');

        try {
            $response = $this->arcgisHttp()->post(
                $this->featureLayerUrl() . '/query',
                [
                    'f' => 'json',
                    'token' => config('services.arcgis.token'),
                    'where' => "UPPER(severity_level) <> 'RESOLVED'",
                    'outFields' => '*',
                    'returnGeometry' => 'true',
                    'outSR' => '4326',
                ]
            );

            $json = $response->json();

            $features = collect($json['features'] ?? [])
                ->map(function ($feature) use ($userLat, $userLng) {
                    $attrs = $feature['attributes'] ?? [];
                    $geometry = $feature['geometry'] ?? [];

                    $lat = $geometry['y'] ?? null;
                    $lng = $geometry['x'] ?? null;

                    $distanceKm = null;

                    if ($userLat && $userLng && $lat && $lng) {
                        $distanceKm = $this->calculateDistanceKm(
                            (float) $userLat,
                            (float) $userLng,
                            (float) $lat,
                            (float) $lng
                        );
                    }

                    return [
                        'id' => $attrs['OBJECTID'] ?? null,
                        'incident_type' => $attrs['categories'] ?? 'Fire Incident',
                        'district' => $attrs['district'] ?? 'Unknown',
                        'status' => $attrs['severity_level'] ?? 'ONGOING',
                        'description' => $attrs['more_details'] ?? '',
                        'reported_at' => $attrs['datetime_reported'] ?? null,
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'distance_km' => $distanceKm,
                    ];
                })
                ->when($userLat && $userLng, function ($collection) {
                    return $collection->sortBy('distance_km')->values();
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $features,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load ongoing incidents.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

private function calculateDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earthRadius = 6371;

    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a =
        sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) *
        cos(deg2rad($lat2)) *
        sin($dLng / 2) *
        sin($dLng / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return round($earthRadius * $c, 2);
}
}