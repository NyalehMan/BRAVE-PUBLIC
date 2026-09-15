<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicPsiTest extends TestCase
{
    private const LAST_OFFICIAL_CACHE_KEY = 'brave:jastre-psi:ocr:last-official:v2';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_it_returns_unavailable_instead_of_falling_back_to_modelled_aqi(): void
    {
        Http::fake([
            '*' => Http::response('JASTRe unavailable', 503),
        ]);

        $this->getJson('/api/public/psi')
            ->assertServiceUnavailable()
            ->assertJson([
                'source_type' => 'unavailable',
                'official' => false,
                'fallback' => false,
                'metric' => 'psi',
                'readings' => [],
            ])
            ->assertJsonMissing([
                'source_type' => 'modelled_fallback',
            ]);
    }

    public function test_it_returns_only_a_complete_cached_official_reading_when_refresh_fails(): void
    {
        Cache::put(self::LAST_OFFICIAL_CACHE_KEY, $this->officialPayload());
        Http::fake([
            '*' => Http::response('JASTRe unavailable', 503),
        ]);

        $response = $this->getJson('/api/public/psi')
            ->assertOk()
            ->assertJson([
                'source_type' => 'official',
                'official' => true,
                'fallback' => false,
                'metric' => 'psi',
                'stale' => true,
            ]);

        $this->assertCount(4, $response->json('readings'));
        $this->assertSame(['BM', 'BL', 'TM', 'TT'], array_column($response->json('readings'), 'code'));
    }

    public function test_it_rejects_a_cached_aqi_fallback_even_if_it_has_four_readings(): void
    {
        $payload = $this->officialPayload();
        $payload['source'] = 'Open-Meteo';
        $payload['source_type'] = 'modelled_fallback';
        $payload['official'] = false;
        $payload['fallback'] = true;
        $payload['metric'] = 'us_aqi';

        Cache::put(self::LAST_OFFICIAL_CACHE_KEY, $payload);
        Http::fake([
            '*' => Http::response('JASTRe unavailable', 503),
        ]);

        $this->getJson('/api/public/psi')
            ->assertServiceUnavailable()
            ->assertJson([
                'source_type' => 'unavailable',
                'readings' => [],
            ]);
    }

    /** @return array<string, mixed> */
    private function officialPayload(): array
    {
        return [
            'source' => 'Department of Environment, Parks and Recreation (JASTRe)',
            'source_url' => 'https://www.env.gov.bn/',
            'source_type' => 'official',
            'official' => true,
            'fallback' => false,
            'metric' => 'psi',
            'metric_label' => 'PSI',
            'updated_at_label' => 'As of 11 September 2026, 10:00 AM',
            'retrieved_at' => now()->toIso8601String(),
            'stale' => false,
            'readings' => [
                ['code' => 'BM', 'district' => 'Brunei-Muara', 'psi' => 18, 'value' => 18],
                ['code' => 'BL', 'district' => 'Belait', 'psi' => 21, 'value' => 21],
                ['code' => 'TM', 'district' => 'Temburong', 'psi' => 15, 'value' => 15],
                ['code' => 'TT', 'district' => 'Tutong', 'psi' => 19, 'value' => 19],
            ],
        ];
    }
}
