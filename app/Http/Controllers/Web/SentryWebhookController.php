<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\SendSentryAlertToTelegram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SentryWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('services.sentry_webhook.client_secret');

        if ($secret === '') {
            return response()->json(['ok' => false, 'message' => 'Webhook não configurado.'], 503);
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, (string) $request->header('Sentry-Hook-Signature'))) {
            return response()->json(['ok' => false, 'message' => 'Assinatura inválida.'], 401);
        }

        if ($request->header('Sentry-Hook-Resource') !== 'event_alert') {
            return response()->json(['ok' => true]);
        }

        $data = $request->input('data');

        if (is_array($data)) {
            SendSentryAlertToTelegram::dispatchAfterResponse($data);
        }

        return response()->json(['ok' => true]);
    }
}
