<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\PlataformaLucro;
use App\Services\PlataformaLucrosService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlataformaLucrosController extends Controller
{
    private function periodo(Request $request): array
    {
        $dados = $request->validate([
            'periodo' => ['required', Rule::in(['hoje', 'semanal', 'mensal', 'personalizado'])],
            'data' => 'required|date_format:Y-m-d', 'data_inicio' => 'required_if:periodo,personalizado|nullable|date_format:Y-m-d',
            'data_fim' => 'required_if:periodo,personalizado|nullable|date_format:Y-m-d|after_or_equal:data_inicio',
            'tipo_servico' => ['nullable', Rule::in(['todos', 'corrida', 'entrega'])],
            'status_custos' => ['nullable', Rule::in(['consolidado', 'custos_pendentes'])],
            'incluir_demonstracao' => 'nullable|boolean', 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:50',
        ]);
        $zona = config('ganhos.timezone', 'America/Porto_Velho');
        $ref = CarbonImmutable::parse($dados['data'], $zona)->startOfDay();
        $inicio = match ($dados['periodo']) {
            'semanal' => $ref->startOfWeek(CarbonImmutable::MONDAY), 'mensal' => $ref->startOfMonth(),
            'personalizado' => CarbonImmutable::parse($dados['data_inicio'], $zona), default => $ref,
        };
        $fim = match ($dados['periodo']) {
            'semanal' => $inicio->addWeek(), 'mensal' => $inicio->addMonth(),
            'personalizado' => CarbonImmutable::parse($dados['data_fim'], $zona)->addDay(), default => $inicio->addDay(),
        };
        abort_if($inicio->diffInDays($fim) > 366, 422, 'Selecione um período de até 366 dias.');

        return [$dados, $inicio, $fim];
    }

    public function sincronizar(Request $request, PlataformaLucrosService $service)
    {
        [, $inicio, $fim] = $this->periodo($request);

        return response()->json(['data' => ['atualizados' => $service->sincronizar($inicio->utc(), $fim->utc())]]);
    }

    public function index(Request $request, PlataformaLucrosService $service)
    {
        [$dados, $inicio, $fim] = $this->periodo($request);
        $query = PlataformaLucro::query()->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('ocorrido_em', '>=', $inicio->utc())->where('ocorrido_em', '<', $fim->utc()))
            ->orWhere(fn ($q) => $q->where('recebido_em', '>=', $inicio->utc())->where('recebido_em', '<', $fim->utc())));
        if (! ($dados['incluir_demonstracao'] ?? false)) {
            $query->where('demonstracao', false);
        }
        if (($dados['tipo_servico'] ?? 'todos') !== 'todos') {
            $query->where('tipo_servico', $dados['tipo_servico']);
        }
        if (! empty($dados['status_custos'])) {
            $query->where('status_custos', $dados['status_custos']);
        }
        $totais = ['lucro_realizado' => 0, 'lucro_estimado_recebido' => 0, 'comissoes_a_receber' => 0, 'online_aguardando_liquidacao' => 0, 'gmv_realizado' => 0, 'custos_pendentes' => 0, 'quantidade' => 0];
        $porHora = $dados['periodo'] === 'hoje';
        $grafico = [];
        if ($porHora) {
            for ($hora = 0; $hora < 24; $hora++) {
                $grafico[sprintf('%02d:00', $hora)] = ['centavos' => 0, 'quantidade' => 0];
            }
        } else {
            for ($dia = $inicio; $dia->lt($fim); $dia = $dia->addDay()) {
                $grafico[$dia->toDateString()] = ['centavos' => 0, 'quantidade' => 0];
            }
        }
        foreach ((clone $query)->cursor() as $registro) {
            $totais['quantidade']++;
            $totais['custos_pendentes'] += $registro->status_custos === 'custos_pendentes' ? 1 : 0;
            if ($registro->status_caixa === 'a_receber_motorista') {
                $totais['comissoes_a_receber'] += max(0, $service::centavos($registro->comissao_prevista) ?? 0);
            }
            if ($registro->status_caixa === 'aguardando_liquidacao') {
                $totais['online_aguardando_liquidacao'] += max(0, $service::centavos($registro->comissao_prevista) ?? 0);
            }
            if ($registro->status_caixa !== 'realizado_em_conta' || ! $registro->recebido_em || $registro->recebido_em->lt($inicio) || ! $registro->recebido_em->lt($fim)) {
                continue;
            }
            $lucro = $service::centavos($registro->lucro_liquido) ?? 0;
            if ($registro->status_custos !== 'consolidado') {
                $totais['lucro_estimado_recebido'] += $lucro;

                continue;
            }
            $totais['lucro_realizado'] += $lucro;
            $totais['gmv_realizado'] += $service::centavos($registro->valor_bruto_passageiro) ?? 0;
            $recebimentoLocal = $registro->recebido_em->setTimezone($inicio->timezone);
            $chave = $porHora ? $recebimentoLocal->format('H:00') : $recebimentoLocal->toDateString();
            $grafico[$chave]['centavos'] += $lucro;
            $grafico[$chave]['quantidade']++;
        }
        $take = $totais['gmv_realizado'] > 0 ? number_format($totais['lucro_realizado'] * 100 / $totais['gmv_realizado'], 2, '.', '') : null;
        foreach (['lucro_realizado', 'lucro_estimado_recebido', 'comissoes_a_receber', 'online_aguardando_liquidacao', 'gmv_realizado'] as $campo) {
            $totais[$campo] = $service::moeda($totais[$campo]);
        }
        $pagina = $query->with('corrida:id,codigo_corrida,status_corrida')->orderByDesc('ocorrido_em')->orderByDesc('id')->paginate($dados['per_page'] ?? 15);

        return response()->json(['data' => $pagina->getCollection()->map(fn ($r) => $service->apresentar($r)),
            'meta' => ['total' => $pagina->total(), 'current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage()],
            'resumo' => [...$totais, 'take_rate' => $take],
            'periodo' => ['inicio' => $inicio->toDateString(), 'fim' => $fim->subDay()->toDateString(), 'timezone' => $inicio->timezoneName],
            'grafico_granularidade' => $porHora ? 'hora' : 'dia',
            'grafico' => collect($grafico)->map(fn ($ponto, $chave) => ['data' => $chave, 'valor' => $service::moeda($ponto['centavos']), 'quantidade' => $ponto['quantidade']])->values()]);
    }

    public function show(int $lucro, PlataformaLucrosService $service)
    {
        return response()->json(['data' => $service->apresentar(PlataformaLucro::with(['corrida.motorista'])->findOrFail($lucro), true)]);
    }

    public function conciliar(Request $request, int $lucro, PlataformaLucrosService $service)
    {
        $dados = $request->validate($this->auditoria() + [
            ...array_fill_keys(PlataformaLucrosService::CUSTOS, ['present', 'nullable', 'numeric', 'min:0', 'max:99999999.99', 'regex:/^\d+(\.\d{1,2})?$/']),
            'custos_confirmados' => 'present|array', 'custos_confirmados.*' => ['distinct', Rule::in(PlataformaLucrosService::CUSTOS)],
            'custos_incluidos_repasse' => 'present|array', 'custos_incluidos_repasse.*' => ['distinct', Rule::in(['subsidio_aplicado', 'bonus_alocado', 'compensacao_paga'])],
            'composicao_conferida' => 'required|boolean', 'status_caixa' => 'prohibited', 'lucro_liquido' => 'prohibited',
        ]);
        $registro = $service->conciliar($lucro, $dados, $request->user('gestores'));

        return response()->json(['data' => $service->apresentar($registro->load('corrida.motorista'), true)]);
    }

    public function recebimento(Request $request, int $lucro, PlataformaLucrosService $service)
    {
        $dados = $request->validate($this->auditoria() + ['valor' => 'required|numeric|min:0.01|max:99999999.99|regex:/^\d+(\.\d{1,2})?$/', 'recebido_em' => 'required|date|before_or_equal:now']);
        $registro = $service->conciliar($lucro, $dados, $request->user('gestores'), true);

        return response()->json(['data' => $service->apresentar($registro->load('corrida.motorista'), true)]);
    }

    public function registrarCusto(Request $request, PlataformaLucrosService $service)
    {
        $dados = $request->validate([
            'client_request_id' => 'required|uuid', 'descricao' => 'required|string|min:5|max:255',
            'referencia' => 'required|string|min:3|max:255', 'motivo' => 'required|string|min:10|max:1000',
            'tipo_operacao' => ['required', Rule::in(['subsidio_tarifa', 'recompensa_bonus', 'compensacao_reembolso'])],
            'tipo_servico' => ['required', Rule::in(['corrida', 'entrega', 'outro'])],
            'origem_pagamento' => ['required', Rule::in(['online_pix', 'online_cartao', 'nao_identificada'])],
            'valor' => 'required|numeric|min:0.01|max:99999999.99|regex:/^\d+(\.\d{1,2})?$/',
            'pago_em' => 'required|date|before_or_equal:now', 'nao_alocado' => 'required|accepted',
        ]);
        $registro = $service->registrarCusto($dados, $request->user('gestores'));

        return response()->json(['data' => $service->apresentar($registro, true)], 201);
    }

    private function auditoria(): array
    {
        return ['versao' => 'required|integer|min:1', 'client_request_id' => 'required|uuid', 'motivo' => 'required|string|min:10|max:1000', 'referencia' => 'required|string|min:3|max:255'];
    }
}
