<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppIncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Meta calls this endpoint to verify the webhook.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode')
            ?? $request->query('hub.mode');

        $receivedToken = $request->query('hub_verify_token')
            ?? $request->query('hub.verify_token');

        $challenge = $request->query('hub_challenge')
            ?? $request->query('hub.challenge');

        $expectedToken = (string) config(
            'services.whatsapp.verify_token'
        );

        if (
            $mode === 'subscribe' &&
            $expectedToken !== '' &&
            hash_equals($expectedToken, (string) $receivedToken)
        ) {
            return response((string) $challenge, 200)
                ->header('Content-Type', 'text/plain');
        }

        return response('Webhook verification failed.', 403);
    }

    /**
     * Receive incoming WhatsApp messages.
     */
    public function receive(
        Request $request,
        WhatsAppIncidentService $incidents
    ): JsonResponse {
        $appSecret = trim((string) config(
            'services.whatsapp.app_secret',
            ''
        ));

        if ($appSecret === '') {
            Log::error(
                'WhatsApp webhook delivery is disabled because the app secret is not configured.'
            );

            return response()->json([
                'received' => false,
            ], 503);
        }

        if (! $this->hasValidSignature($request, $appSecret)) {
            Log::warning('WhatsApp webhook signature was rejected.');

            return response()->json([
                'received' => false,
            ], 401);
        }

        $result = $incidents->process($request->all());

        Log::info('WhatsApp webhook processed', [
            'messages_processed' => $result['messages_processed'],
            'reports_created' => $result['reports_created'],
        ]);

        return response()->json([
            'received' => true,
        ]);
    }

    private function hasValidSignature(
        Request $request,
        string $appSecret
    ): bool
    {
        $receivedSignature = trim((string) $request->header(
            'X-Hub-Signature-256',
            ''
        ));
        $expectedSignature = 'sha256='.hash_hmac(
            'sha256',
            $request->getContent(),
            $appSecret
        );

        return $receivedSignature !== ''
            && hash_equals($expectedSignature, $receivedSignature);
    }
}
