<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class PublicIncidentController extends Controller
{
    public function ongoing(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => [
                'nullable',
                'required_with:lng',
                'numeric',
                'between:-90,90',
            ],
            'lng' => [
                'nullable',
                'required_with:lat',
                'numeric',
                'between:-180,180',
            ],
        ]);

        $userLat = isset($validated['lat'])
            ? (float) $validated['lat']
            : null;
        $userLng = isset($validated['lng'])
            ? (float) $validated['lng']
            : null;

        try {
            $response = $this->arcgisHttp()->post(
                $this->featureLayerUrl() . '/query',
                [
                    'f' => 'json',
                    'token' => $this->arcgisToken(),
                    'where' => "severity_level <> 'Resolved'",
                    'outFields' => '*',
                    'returnGeometry' => 'true',
                    'outSR' => '4326',
                ]
            );

            $payload = $response->json() ?? [];

            if (
                !$response->successful()
                || isset($payload['error'])
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to load ongoing incidents.',
                ], 502);
            }

            $features = collect($payload['features'] ?? [])
                ->map(function (array $feature) use (
                    $userLat,
                    $userLng
                ): array {
                    $attributes = $feature['attributes'] ?? [];
                    $geometry = $feature['geometry'] ?? [];
                    $latitude = $geometry['y'] ?? null;
                    $longitude = $geometry['x'] ?? null;
                    $distanceKm = null;

                    if (
                        $userLat !== null
                        && $userLng !== null
                        && is_numeric($latitude)
                        && is_numeric($longitude)
                    ) {
                        $distanceKm = $this->calculateDistanceKm(
                            $userLat,
                            $userLng,
                            (float) $latitude,
                            (float) $longitude
                        );
                    }

                    return [
                        'id' => $attributes['OBJECTID']
                            ?? $attributes['objectid']
                            ?? null,
                        'incident_type' =>
                            $attributes['categories']
                            ?? 'Fire Incident',
                        'district' =>
                            $attributes['district']
                            ?? 'Unknown',
                        'status' =>
                            $attributes['severity_level']
                            ?? 'ONGOING',
                        'description' =>
                            $attributes['more_details']
                            ?? '',
                        'reported_at' =>
                            $attributes['datetime_reported']
                            ?? null,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'distance_km' => $distanceKm,
                    ];
                })
                ->when(
                    $userLat !== null && $userLng !== null,
                    fn ($collection) => $collection
                        ->sortBy('distance_km')
                        ->values()
                )
                ->values();

            return response()->json([
                'success' => true,
                'data' => $features,
            ]);
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load ongoing incidents.',
            ], 502);
        }
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

    private function calculateDistanceKm(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}
