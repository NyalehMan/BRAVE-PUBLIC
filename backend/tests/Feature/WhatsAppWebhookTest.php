<?php

namespace Tests\Feature;

use App\Models\PublicIncidentReport;
use App\Models\WhatsAppInboundMessage;
use App\Models\WhatsAppReportDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_can_verify_the_webhook_without_authentication(): void
    {
        config()->set('services.whatsapp.verify_token', 'local-test-token');

        $response = $this->get(
            '/api/integrations/whatsapp/webhook?'
            .http_build_query([
                'hub.mode' => 'subscribe',
                'hub.verify_token' => 'local-test-token',
                'hub.challenge' => 'brave-challenge',
            ])
        );

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertContent('brave-challenge');
    }

    public function test_webhook_verification_rejects_the_wrong_token(): void
    {
        config()->set('services.whatsapp.verify_token', 'expected-token');

        $this->get(
            '/api/integrations/whatsapp/webhook?'
            .http_build_query([
                'hub.mode' => 'subscribe',
                'hub.verify_token' => 'wrong-token',
                'hub.challenge' => 'brave-challenge',
            ])
        )->assertForbidden();
    }

    public function test_webhook_post_fails_closed_without_an_app_secret(): void
    {
        config()->set('services.whatsapp.app_secret', '');

        $this->postJson('/api/integrations/whatsapp/webhook', [
            'object' => 'whatsapp_business_account',
            'entry' => [],
        ])
            ->assertStatus(503)
            ->assertExactJson(['received' => false]);
    }

    public function test_description_and_location_create_one_pending_report(): void
    {
        $payload = $this->payload([
            [
                'from' => '6738123456',
                'id' => 'wamid.description',
                'type' => 'text',
                'text' => [
                    'body' => implode("\n", [
                        'Type: House Fire',
                        'District: Brunei Muara',
                        'Urgency: High',
                        'Description: Thick smoke is coming from the kitchen.',
                    ]),
                ],
            ],
            [
                'from' => '6738123456',
                'id' => 'wamid.location',
                'type' => 'location',
                'location' => [
                    'latitude' => 4.9031,
                    'longitude' => 114.9398,
                    'name' => 'Bandar Seri Begawan',
                ],
            ],
        ]);

        $this->postSignedPayload($payload)
            ->assertOk()
            ->assertExactJson(['received' => true]);

        $report = PublicIncidentReport::query()->sole();

        $this->assertSame('PENDING', $report->status);
        $this->assertSame('House Fire', $report->incident_type);
        $this->assertSame('Brunei Muara', $report->district);
        $this->assertSame('Test Reporter (WhatsApp)', $report->reporter_full_name);
        $this->assertSame(4.9031, $report->latitude);
        $this->assertSame(114.9398, $report->longitude);
        $this->assertStringContainsString(
            'Observed urgency: High',
            $report->description
        );
        $this->assertStringContainsString(
            'Thick smoke is coming from the kitchen.',
            $report->description
        );
        $this->assertSame(0, WhatsAppReportDraft::query()->count());
        $this->assertSame(2, WhatsAppInboundMessage::query()->count());

        $this->postSignedPayload($payload)
            ->assertOk();

        $this->assertSame(1, PublicIncidentReport::query()->count());
        $this->assertSame(2, WhatsAppInboundMessage::query()->count());
    }

    public function test_an_incomplete_report_is_kept_as_a_draft(): void
    {
        $this->postSignedPayload(
            $this->payload([[
                'from' => '6738123456',
                'id' => 'wamid.draft',
                'type' => 'text',
                'text' => [
                    'body' => 'There is a car fire near the school.',
                ],
            ]])
        )->assertOk();

        $this->assertSame(0, PublicIncidentReport::query()->count());

        $draft = WhatsAppReportDraft::query()->sole();
        $this->assertSame('Car Fire', $draft->incident_type);
        $this->assertSame(
            'There is a car fire near the school.',
            $draft->description
        );
    }

    public function test_a_whatsapp_photo_is_downloaded_into_private_report_storage(): void
    {
        Storage::fake('local');
        config()->set('services.whatsapp.access_token', 'test-access-token');
        config()->set('services.whatsapp.graph_api_version', 'v23.0');
        config()->set('services.whatsapp.send_replies', false);

        $imageBytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );

        Http::fake([
            'https://graph.facebook.com/v23.0/media-123' => Http::response([
                'url' => 'https://lookaside.fbsbx.com/whatsapp_business/attachments/media-123',
                'mime_type' => 'image/png',
                'file_size' => strlen((string) $imageBytes),
            ]),
            'https://lookaside.fbsbx.com/*' => Http::response(
                $imageBytes,
                200,
                ['Content-Type' => 'image/png']
            ),
        ]);

        $payload = $this->payload([
            [
                'from' => '6738123456',
                'id' => 'wamid.photo-description',
                'type' => 'text',
                'text' => ['body' => 'Smoke is spreading from a house fire.'],
            ],
            [
                'from' => '6738123456',
                'id' => 'wamid.photo',
                'type' => 'image',
                'image' => [
                    'id' => 'media-123',
                    'mime_type' => 'image/png',
                ],
            ],
            [
                'from' => '6738123456',
                'id' => 'wamid.photo-location',
                'type' => 'location',
                'location' => [
                    'latitude' => 4.9031,
                    'longitude' => 114.9398,
                ],
            ],
        ]);

        $this->postSignedPayload($payload)
            ->assertOk();

        $report = PublicIncidentReport::query()->sole();
        $this->assertNotNull($report->photo_path);
        Storage::disk('local')->assertExists($report->photo_path);
        $this->assertSame(
            $imageBytes,
            Storage::disk('local')->get($report->photo_path)
        );
    }

    public function test_configured_app_secret_rejects_an_invalid_signature(): void
    {
        config()->set('services.whatsapp.app_secret', 'test-app-secret');
        $body = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [],
        ], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/api/integrations/whatsapp/webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $body
        )
            ->assertUnauthorized()
            ->assertExactJson(['received' => false]);

        $this->call(
            'POST',
            '/api/integrations/whatsapp/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid',
            ],
            $body
        )
            ->assertUnauthorized()
            ->assertExactJson(['received' => false]);

        $signature = 'sha256='.hash_hmac(
            'sha256',
            $body,
            'test-app-secret'
        );

        $this->call(
            'POST',
            '/api/integrations/whatsapp/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $body
        )
            ->assertOk()
            ->assertExactJson(['received' => true]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<string, mixed>
     */
    private function payload(array $messages): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba-test',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '6732000000',
                            'phone_number_id' => 'phone-number-id',
                        ],
                        'contacts' => [[
                            'profile' => ['name' => 'Test Reporter'],
                            'wa_id' => '6738123456',
                        ]],
                        'messages' => $messages,
                    ],
                ]],
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSignedPayload(
        array $payload,
        string $appSecret = 'test-app-secret'
    ): TestResponse {
        config()->set('services.whatsapp.app_secret', $appSecret);
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $body, $appSecret);

        return $this->call(
            'POST',
            '/api/integrations/whatsapp/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $body
        );
    }
}
