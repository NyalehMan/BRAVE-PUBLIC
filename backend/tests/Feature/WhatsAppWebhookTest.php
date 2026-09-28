<?php

namespace Tests\Feature;

use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
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

    public function test_meta_can_post_a_webhook_without_authentication(): void
    {
        $this->postJson('/api/integrations/whatsapp/webhook', [
            'object' => 'whatsapp_business_account',
            'entry' => [],
        ])
            ->assertOk()
            ->assertExactJson(['received' => true]);
    }
}
