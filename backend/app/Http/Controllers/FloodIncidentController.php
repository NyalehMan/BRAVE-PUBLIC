<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class FloodIncidentController extends Controller
{
    private ?array $layerMetadata = null;
    private ?string $resolvedLayerUrl = null;

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
            'Flood incident created successfully.'
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
            return $this->serviceFailure($exception);
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
            'Flood incident updated successfully.'
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
                    'message' => 'Failed to delete flood incident.',
                    'error' => $this->arcgisErrorMessage(
                        $payload,
                        $response->status(),
                        is_array($result) ? $result : null
                    ),
                ], 502);
            }

            return response()->json([
                'message' => 'Flood incident deleted successfully.',
                'object_id' => $result['objectId'] ?? $objectId,
            ]);
        } catch (Throwable $exception) {
            return $this->serviceFailure($exception);
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
                'array:Location,Latitude,Longitude,Incident_Type,Status,'
                    . 'Date_and_Time,Water_Level,Rainfall,Intensity,Remarks',
            ],
            'attributes.Location' => [$presence, 'string', 'max:150'],
            'attributes.Latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'attributes.Longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'attributes.Incident_Type' => [$presence, 'string', 'max:100'],
            'attributes.Status' => [$presence, 'string', 'max:40'],
            'attributes.Date_and_Time' => ['nullable', 'numeric'],
            'attributes.Water_Level' => ['nullable', 'numeric'],
            'attributes.Rainfall' => ['nullable', 'numeric'],
            'attributes.Intensity' => ['nullable', 'numeric'],
            'attributes.Remarks' => ['nullable', 'string', 'max:5000'],
            'geometry' => ['nullable', 'array'],
            'geometry.x' => [
                'required_with:geometry',
                'numeric',
                'between:-180,180',
            ],
            'geometry.y' => [
                'required_with:geometry',
                'numeric',
                'between:-90,90',
            ],
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
                $feature['attributes'] = $this->filterAttributesForLayer(
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
                    'message' => 'ArcGIS rejected the flood incident change.',
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
            return $this->serviceFailure($exception);
        }
    }

    private function objectIdFieldName(): string
    {
        return (string) (
            $this->layerMetadata()['objectIdField']
            ?? 'OBJECTID'
        );
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
            || empty($payload['fields'])
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
            if (!is_array($field) || empty($field['name'])) {
                continue;
            }

            $actualName = (string) $field['name'];
            $fieldMap[$this->normaliseFieldName($actualName)] = $actualName;

            if (!empty($field['alias'])) {
                $fieldMap[
                    $this->normaliseFieldName(
                        (string) $field['alias']
                    )
                ] = $actualName;
            }
        }

        $filtered = [];

        foreach ($attributes as $name => $value) {
            $actualName = $fieldMap[
                $this->normaliseFieldName((string) $name)
            ] ?? null;

            if ($actualName !== null) {
                $filtered[$actualName] = $value;
            }
        }

        if ($filtered === []) {
            throw new RuntimeException(
                'No submitted attributes match the flood ArcGIS layer fields.'
            );
        }

        return $filtered;
    }

    private function featureLayerUrl(): string
    {
        if ($this->resolvedLayerUrl !== null) {
            return $this->resolvedLayerUrl;
        }

        $configuredUrl = rtrim(
            (string) config(
                'services.arcgis.flood_incident_layer_url'
            ),
            '/'
        );

        if ($configuredUrl !== '') {
            $this->resolvedLayerUrl = $configuredUrl;

            return $this->resolvedLayerUrl;
        }

        $webmapId = (string) config(
            'services.arcgis.flood_webmap_id'
        );

        if (!preg_match('/^[a-f0-9]{32}$/i', $webmapId)) {
            throw new RuntimeException(
                'ARCGIS_FLOOD_WEBMAP_ID is invalid.'
            );
        }

        $response = $this->arcgisHttp()->get(
            'https://www.arcgis.com/sharing/rest/content/items/'
                . $webmapId
                . '/data',
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

        $candidates = [];
        $this->collectFloodLayerUrls(
            $payload['operationalLayers'] ?? [],
            $candidates
        );

        if ($candidates === []) {
            throw new RuntimeException(
                'The flood incident layer was not found in the configured WebMap.'
            );
        }

        usort(
            $candidates,
            fn (array $left, array $right): int =>
                $right['priority'] <=> $left['priority']
        );

        $this->resolvedLayerUrl = rtrim(
            (string) $candidates[0]['url'],
            '/'
        );

        $scheme = parse_url($this->resolvedLayerUrl, PHP_URL_SCHEME);
        $host = strtolower((string) parse_url(
            $this->resolvedLayerUrl,
            PHP_URL_HOST
        ));

        if (
            $scheme !== 'https'
            || (
                $host !== 'arcgis.com'
                && !str_ends_with($host, '.arcgis.com')
            )
        ) {
            throw new RuntimeException(
                'The WebMap returned an untrusted flood layer URL.'
            );
        }

        return $this->resolvedLayerUrl;
    }

    private function collectFloodLayerUrls(
        array $layers,
        array &$candidates,
        ?string $parentUrl = null
    ): void {
        foreach ($layers as $layer) {
            if (!is_array($layer)) {
                continue;
            }

            $title = strtolower((string) ($layer['title'] ?? ''));
            $url = $layer['url'] ?? $parentUrl;
            $hasChildLayers = !empty($layer['layers'])
                && is_array($layer['layers']);

            if (
                is_string($url)
                && str_contains($title, 'flood sensor')
            ) {
                if (
                    array_key_exists('id', $layer)
                    && !preg_match('/\/[0-9]+$/', $url)
                    && is_numeric($layer['id'])
                ) {
                    $url .= '/' . $layer['id'];
                }

                if (
                    preg_match('/\/[0-9]+$/', $url)
                    || !$hasChildLayers
                ) {
                    $candidates[] = [
                        'url' => $url,
                        'priority' => str_contains($title, 'simulated')
                            ? 2
                            : 1,
                    ];
                }
            }

            if ($hasChildLayers) {
                $this->collectFloodLayerUrls(
                    $layer['layers'],
                    $candidates,
                    is_string($url) ? $url : $parentUrl
                );
            }
        }
    }

    private function normaliseFieldName(string $value): string
    {
        return strtolower(
            preg_replace('/[^a-z0-9]+/i', '', $value) ?? ''
        );
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

    private function serviceFailure(
        Throwable $exception
    ): JsonResponse {
        return response()->json([
            'message' => 'The BRAVE flood ArcGIS service is unavailable.',
            'error' => $exception->getMessage(),
        ], 502);
    }
}
