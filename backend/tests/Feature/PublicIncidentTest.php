<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicIncidentTest extends TestCase
{
    public function test_public_incidents_use_the_public_layer_without_a_token(): void
    {
        config([
            'services.arcgis.fire_incident_layer_url' =>
                'https://example.test/FeatureServer/0',
            'services.arcgis.token' => 'expired-token',
        ]);

        Http::fake([
            'https://example.test/FeatureServer/0/query' => Http::response([
                'features' => [[
                    'attributes' => [
                        'OBJECTID' => 42,
                        'categories' => 'Fire Incident',
                        'district' => 'Brunei-Muara',
                        'severity_level' => 'ONGOING',
                        'more_details' => 'Test incident',
                        'datetime_reported' => 1_789_569_600_000,
                    ],
                    'geometry' => [
                        'x' => 114.9398,
                        'y' => 4.9031,
                    ],
                ]],
            ]),
        ]);

        $this->getJson('/api/public/incidents/ongoing')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', 42)
            ->assertJsonPath('data.0.district', 'Brunei-Muara');

        Http::assertSent(function (Request $request): bool {
            return $request->url()
                    === 'https://example.test/FeatureServer/0/query'
                && !array_key_exists('token', $request->data());
        });
    }
}
