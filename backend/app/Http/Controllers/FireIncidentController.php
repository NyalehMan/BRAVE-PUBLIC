<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FireIncidentController extends Controller
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

    public function destroy(int $objectId): JsonResponse
    {
        $payload = [
            'f' => 'json',
            'token' => config('services.arcgis.token'),
            'deletes' => (string) $objectId,
        ];

        try {
            $response = $this->arcgisHttp()->post(
                $this->featureLayerUrl() . '/applyEdits',
                $payload
            );

            $json = $response->json();

            if (!$response->ok() || !empty($json['error'])) {
                return response()->json([
                    'message' => 'Failed to delete fire incident.',
                    'details' => $json,
                ], 500);
            }

            $result = $json['deleteResults'][0] ?? null;

            if (!$result || empty($result['success'])) {
                return response()->json([
                    'message' => 'Fire incident was not deleted successfully.',
                    'details' => $json,
                ], 500);
            }

            return response()->json([
                'message' => 'Fire incident deleted successfully.',
                'object_id' => $result['objectId'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Server error during delete.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, int $objectId): JsonResponse
    {
        Log::info('Fire incident update reached', [
            'id' => $objectId,
            'payload' => $request->all(),
        ]);

        $attributes = $request->input('attributes', []);
        $geometry = $request->input('geometry');

        $payloadFeature = [
            'attributes' => array_merge($attributes, [
                'OBJECTID' => $objectId,
            ]),
        ];

        if ($geometry) {
            $payloadFeature['geometry'] = $geometry;
        }

        $payload = [
            'f' => 'json',
            'token' => config('services.arcgis.token'),
            'updates' => json_encode([$payloadFeature]),
        ];

        $response = $this->arcgisHttp()->post(
            $this->featureLayerUrl() . '/applyEdits',
            $payload
        );

        $json = $response->json();

        Log::info('ArcGIS Update Response', [
            'status' => $response->status(),
            'body' => $json,
        ]);

        $result = $json['updateResults'][0] ?? null;

        if (!$result || empty($result['success'])) {
            return response()->json([
                'message' => 'Failed to update fire incident.',
                'details' => $json,
            ], 500);
        }

        return response()->json([
            'message' => 'Fire incident updated successfully.',
        ]);
    }

    public function route(Request $request): JsonResponse
    {
        $request->validate([
            'start.latitude' => ['required', 'numeric'],
            'start.longitude' => ['required', 'numeric'],
            'end.latitude' => ['required', 'numeric'],
            'end.longitude' => ['required', 'numeric'],
            'stops' => ['nullable', 'array'],
            'stops.*.latitude' => ['required_with:stops', 'numeric'],
            'stops.*.longitude' => ['required_with:stops', 'numeric'],
            'mode' => ['nullable', 'string'],
            'departureType' => ['nullable', 'string'],
            'departureDate' => ['nullable', 'string'],
            'departureTime' => ['nullable', 'string'],
            'optimizeOrder' => ['nullable', 'boolean'],
        ]);

        $start = $request->input('start');
        $end = $request->input('end');
        $extraStops = $request->input('stops', []);

        $features = [];

        $addStop = function (array $point, string $name, int $sequence) use (&$features) {
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

        $addStop($start, 'Start', 1);

        foreach ($extraStops as $index => $stop) {
            $addStop($stop, 'Stop ' . ($index + 1), $index + 2);
        }

        $addStop($end, 'Destination', count($features) + 1);

        $stops = [
            'features' => $features,
        ];

        $mode = $request->input('mode', 'Driving Time');

        $travelMode = match ($mode) {
            'Driving Distance' => 'Driving Distance',
            'Walking Time' => 'Walking Time',
            'Walking Distance' => 'Walking Distance',
            'Trucking Time' => 'Trucking Time',
            'Trucking Distance' => 'Trucking Distance',
            'Rural Driving Time' => 'Rural Driving Time',
            'Rural Driving Distance' => 'Rural Driving Distance',
            default => 'Driving Time',
        };

        $departureType = $request->input('departureType', 'now');
        $departureTimestamp = null;

        if ($departureType === 'depart_at') {
            $departureDate = $request->input('departureDate');
            $departureClock = $request->input('departureTime');

            if ($departureDate && $departureClock) {
                $departureTimestamp = strtotime($departureDate . ' ' . $departureClock);

                if ($departureTimestamp !== false) {
                    $departureTimestamp *= 1000;
                }
            }
        }

        $payload = [
            'f' => 'json',
            'token' => config('services.arcgis.token'),
            'stops' => json_encode($stops),
            'returnRoutes' => 'true',
            'returnDirections' => 'true',
            'returnStops' => 'true',
            'outSR' => 4326,
            'findBestSequence' => $request->boolean('optimizeOrder') ? 'true' : 'false',
            'preserveFirstStop' => 'true',
            'preserveLastStop' => 'true',
            'ignoreInvalidLocations' => 'true',
        ];

        if ($departureTimestamp) {
            $payload['startTime'] = $departureTimestamp;
        }

        $response = Http::asForm()
            ->post(
                'https://route.arcgis.com/arcgis/rest/services/World/Route/NAServer/Route_World/solve',
                $payload
            );

        return response()->json($response->json());
    }
}