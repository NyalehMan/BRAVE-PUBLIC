<?php

namespace App\Http\Controllers;

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
    public function receive(Request $request): JsonResponse
    {
        Log::info('WhatsApp webhook received', [
            'payload' => $request->all(),
        ]);

        return response()->json([
            'received' => true,
        ]);
    }
}
