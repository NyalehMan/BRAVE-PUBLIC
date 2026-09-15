<?php

namespace Tests\Feature;

use App\Models\PublicIncidentReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NiatReviewerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_routes_require_an_active_reviewer_role(): void
    {
        $viewer = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
        ]);

        $this->actingAs($viewer)
            ->getJson('/api/operator/public-reports')
            ->assertForbidden();

        $inactiveReviewer = User::factory()->create([
            'role' => 'reviewer',
            'is_active' => false,
        ]);

        $this->actingAs($inactiveReviewer)
            ->getJson('/api/niat/me')
            ->assertForbidden();

        $reviewer = User::factory()->create([
            'role' => 'reviewer',
            'is_active' => true,
        ]);

        $this->actingAs($reviewer)
            ->getJson('/api/operator/public-reports')
            ->assertOk();
    }

    public function test_reports_are_filtered_searched_and_paginated_on_the_server(): void
    {
        $reviewer = User::factory()->create([
            'role' => 'supervisor',
            'is_active' => true,
        ]);

        foreach (range(1, 26) as $index) {
            $this->createReport([
                'status' => 'PENDING',
                'description' => $index === 26 ? 'Unique control room search phrase' : "Report {$index}",
            ]);
        }

        $this->createReport(['status' => 'NEEDS_VERIFY']);
        $this->createReport(['status' => 'VERIFIED']);
        $this->createReport(['status' => 'REJECTED']);

        $response = $this->actingAs($reviewer)
            ->getJson('/api/operator/public-reports?status=PENDING&per_page=10&page=2')
            ->assertOk()
            ->assertJsonPath('pagination.current_page', 2)
            ->assertJsonPath('pagination.last_page', 3)
            ->assertJsonPath('pagination.total', 26)
            ->assertJsonPath('status_counts.PENDING', 26)
            ->assertJsonPath('status_counts.NEEDS_VERIFY', 1)
            ->assertJsonPath('status_counts.VERIFIED', 1)
            ->assertJsonPath('status_counts.REJECTED', 1);

        $this->assertCount(10, $response->json('data'));

        $this->actingAs($reviewer)
            ->getJson('/api/operator/public-reports?status=ALL&search=control%20room')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.description', 'Unique control room search phrase');
    }

    public function test_report_photos_are_served_only_from_authenticated_private_storage(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $report = $this->createReport([
            'photo_path' => 'public_reports/evidence.jpg',
        ]);
        Storage::disk('local')->put('public_reports/evidence.jpg', 'private evidence');

        $this->get("/api/operator/public-reports/{$report->id}/photo")
            ->assertUnauthorized();

        $reviewer = User::factory()->create([
            'role' => 'administrator',
            'is_active' => true,
        ]);

        $response = $this->actingAs($reviewer)
            ->get("/api/operator/public-reports/{$report->id}/photo")
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $this->assertSame('private evidence', $response->streamedContent());
    }

    public function test_legacy_photo_command_copies_then_removes_the_public_file(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('public')->put('public_reports/legacy.jpg', 'legacy evidence');

        $this->artisan('brave:secure-report-photos')
            ->expectsOutput('Secured 1 public report photo(s).')
            ->assertSuccessful();

        Storage::disk('local')->assertExists('public_reports/legacy.jpg');
        Storage::disk('public')->assertMissing('public_reports/legacy.jpg');
        $this->assertSame(
            'legacy evidence',
            Storage::disk('local')->get('public_reports/legacy.jpg')
        );
    }

    public function test_arcgis_retry_recovers_the_existing_feature_by_persistent_global_id(): void
    {
        $reviewer = User::factory()->create([
            'role' => 'reviewer',
            'is_active' => true,
        ]);
        $report = $this->createReport([
            'arcgis_submission_uuid' => '56bb8945-98ed-4c26-b8d0-98d72165930d',
        ]);

        config([
            'services.arcgis.fire_incident_layer_url' => 'https://example.test/FeatureServer/0',
            'services.arcgis.token' => 'test-token',
        ]);

        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/query')) {
                return Http::response([
                    'features' => [
                        ['attributes' => ['OBJECTID' => 734]],
                    ],
                ]);
            }

            return Http::response([
                'objectIdField' => 'OBJECTID',
                'fields' => [
                    ['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID'],
                    ['name' => 'GlobalID', 'type' => 'esriFieldTypeGlobalID'],
                    ['name' => 'Categories', 'alias' => 'Category'],
                    ['name' => 'District', 'alias' => 'District'],
                    ['name' => 'Details', 'alias' => 'More Details'],
                ],
            ]);
        });

        $this->actingAs($reviewer)
            ->postJson("/api/operator/public-reports/{$report->id}/status", [
                'status' => 'VERIFIED',
                'operator_notes' => 'Confirmed by control room.',
            ])
            ->assertOk()
            ->assertJsonPath('data.brave_incident_objectid', 734)
            ->assertJsonPath('data.status', 'VERIFIED');

        Http::assertSentCount(2);
        Http::assertNotSent(
            fn (Request $request): bool => str_ends_with($request->url(), '/addFeatures')
        );
    }

    /** @param array<string, mixed> $overrides */
    private function createReport(array $overrides = []): PublicIncidentReport
    {
        return PublicIncidentReport::query()->create(array_merge([
            'reporter_ic_no' => '00-123456',
            'reporter_full_name' => 'Test Reporter',
            'district' => 'Brunei-Muara',
            'incident_type' => 'House Fire',
            'description' => 'Smoke was observed.',
            'latitude' => 4.9031,
            'longitude' => 114.9398,
            'status' => 'PENDING',
        ], $overrides));
    }
}
