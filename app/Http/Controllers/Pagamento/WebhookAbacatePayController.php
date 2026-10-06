<?php

namespace App\Http\Controllers\Pagamento;

use App\Http\Controllers\Controller;
use App\Models\CobrancaCartao;
use App\Models\CobrancaPix;
use App\Services\CobrancaCartaoService;
use App\Services\CobrancaPixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Entrega de eventos da AbacatePay. O corpo não é usado como fonte da verdade:
 * só identifica a cobrança, que é reconsultada na API antes de qualquer
 * mudança. O segredo vem no parâmetro webhookSecret da URL.
 */
class WebhookAbacatePayController extends Controller
{
    public function __invoke(Request $request, CobrancaPixService $pix, CobrancaCartaoService $cartao): JsonResponse
    {
        $segredo = (string) config('abacatepay.webhook_secret');
        $recebido = (string) $request->query('webhookSecret', '');

        if ($segredo === '' || ! hash_equals($segredo, $recebido)) {
            return response()->json(['message' => 'Não autorizado.'], 401);
        }

        $ids = collect([
            $request->input('data.id'),
            $request->input('data.billing.id'),
            $request->input('data.pixQrCode.id'),
            $request->input('data.checkout.id'),
            $request->input('data.transparent.id'),
        ])->filter(fn ($id) => is_string($id) && $id !== '')->unique();

        foreach ($ids as $id) {
            try {
                $cobrancaPix = CobrancaPix::where('charge_id', $id)->first();
                if ($cobrancaPix !== null) {
                    $pix->sincronizar($cobrancaPix);
                }

                $cobrancaCartao = CobrancaCartao::where('checkout_id', $id)->first();
                if ($cobrancaCartao !== null) {
                    $cartao->sincronizar($cobrancaCartao);
                }
            } catch (Throwable $erro) {
                Log::warning('Falha ao processar webhook da AbacatePay.', ['id' => $id, 'erro' => $erro->getMessage()]);
            }
        }

        return response()->json(['ok' => true]);
    }
}
