<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\Billing\AsaasGateway;
use App\Services\Billing\AsaasWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    /**
     * Webhook do Asaas. Valida o header `asaas-access-token` contra o token
     * configurado no painel, persiste o evento e responde 200 rapidamente.
     */
    public function asaas(Request $request, AsaasGateway $gateway, AsaasWebhookHandler $handler, AuditService $audit): JsonResponse
    {
        if (! $gateway->validateWebhook($request->header('asaas-access-token'))) {
            $audit->alert('WARN', 'asaas.webhook', 'Webhook rejeitado: token inválido', ['ip' => $request->ip()]);

            return response()->json(['error' => 'unauthorized'], 401);
        }
        $result = $handler->handle($request->all());

        return response()->json($result);
    }
}
