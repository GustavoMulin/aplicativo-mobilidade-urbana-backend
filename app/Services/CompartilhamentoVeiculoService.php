<?php

namespace App\Services;

use App\Models\Corrida;
use App\Models\Motorista;
use App\Models\MotoristaVeiculo;
use App\Models\StatusBusca;
use App\Models\Veiculo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CompartilhamentoVeiculoService
{
    public const LIMITE_MOTORISTAS = 3;

    public const CORRIDAS_ATIVAS = ['aguardando_pagamento', 'aceita', 'motorista_chegou', 'em_andamento'];

    public function vincular(Motorista $motorista, Veiculo $veiculo): MotoristaVeiculo
    {
        return DB::transaction(function () use ($motorista, $veiculo): MotoristaVeiculo {
            $veiculo = Veiculo::lockForUpdate()->findOrFail($veiculo->id);
            $dados = ['motorista_id' => $motorista->id, 'veiculo_id' => $veiculo->id];
            $existente = MotoristaVeiculo::where($dados)->first();
            if ($existente !== null) {
                return $existente;
            }
            if (MotoristaVeiculo::where('veiculo_id', $veiculo->id)->lockForUpdate()->get()->count() >= self::LIMITE_MOTORISTAS) {
                throw ValidationException::withMessages(['veiculo_id' => 'Este veículo já possui três motoristas vinculados.']);
            }
            if (! preg_match('/^(?:\d{11}|\d{14})$/', (string) $veiculo->cpf_cnpj_proprietario)) {
                throw ValidationException::withMessages(['veiculo_id' => 'Informe no CRLV o CPF ou CNPJ do proprietário antes de compartilhar o veículo.']);
            }

            return MotoristaVeiculo::create($dados);
        }, 5);
    }

    public function atualizarDisponibilidade(Motorista $motorista, bool $disponivel, ?float $latitude, ?float $longitude, ?int $veiculoId): StatusBusca
    {
        return DB::transaction(function () use ($motorista, $disponivel, $latitude, $longitude, $veiculoId): StatusBusca {
            $motorista = Motorista::whereKey($motorista->id)->lockForUpdate()->firstOrFail();
            if ($disponivel && $motorista->status !== 'ativo') {
                throw new RuntimeException('Seu cadastro de motorista ainda não foi aprovado.', 403);
            }
            $atual = StatusBusca::where('motorista_id', $motorista->id)->first();
            $corrida = Corrida::where('motorista_id', $motorista->id)->whereIn('status_corrida', self::CORRIDAS_ATIVAS)->first();
            if ($corrida !== null && $disponivel) {
                throw new RuntimeException('Você já está em uma corrida.', 409);
            }
            $veiculoId = $corrida?->veiculo_id ?? $veiculoId ?? $atual?->veiculo_id
                ?? MotoristaVeiculo::where('motorista_id', $motorista->id)->orderBy('id')->value('veiculo_id');
            if ($disponivel) {
                $veiculo = $this->veiculoLiberado($motorista, $veiculoId);
                $this->validarUsoExclusivo($motorista->id, $veiculo);
            }

            return StatusBusca::updateOrCreate(['motorista_id' => $motorista->id], [
                'disponivel' => $disponivel, 'latitude' => $latitude, 'longitude' => $longitude,
                'veiculo_id' => $veiculoId, 'visto_em' => now(),
            ]);
        }, 5);
    }

    public function validarParaCorrida(Motorista $motorista, ?int $veiculoId): void
    {
        $this->validarUsoExclusivo($motorista->id, $this->veiculoLiberado($motorista, $veiculoId));
    }

    public function retomarDisponibilidade(int $motoristaId): void
    {
        $motorista = Motorista::find($motoristaId);
        $status = StatusBusca::where('motorista_id', $motoristaId)->first();
        if ($motorista === null || $status === null) {
            return;
        }
        try {
            $this->atualizarDisponibilidade($motorista, true, $status->latitude, $status->longitude, $status->veiculo_id);
        } catch (RuntimeException $erro) {
            if (! in_array($erro->getCode(), [403, 409, 422], true)) {
                throw $erro;
            }
            StatusBusca::whereKey($status->id)->update(['disponivel' => false]);
        }
    }

    public function atualizarPosicao(Motorista $motorista, float $latitude, float $longitude): StatusBusca
    {
        return DB::transaction(function () use ($motorista, $latitude, $longitude): StatusBusca {
            Motorista::whereKey($motorista->id)->lockForUpdate()->firstOrFail();
            $status = StatusBusca::firstOrCreate(['motorista_id' => $motorista->id], ['disponivel' => false]);
            if ($status->disponivel) {
                if ($this->expirou($status)) {
                    $status->disponivel = false;
                } else {
                    try {
                        $this->validarParaCorrida($motorista, $status->veiculo_id);
                    } catch (RuntimeException $erro) {
                        if (! in_array($erro->getCode(), [409, 422], true)) {
                            throw $erro;
                        }
                        $status->disponivel = false;
                    }
                }
            }
            $status->fill(['latitude' => $latitude, 'longitude' => $longitude, 'visto_em' => now()])->save();

            return $status;
        }, 5);
    }

    private function veiculoLiberado(Motorista $motorista, ?int $veiculoId): Veiculo
    {
        $vinculado = $veiculoId !== null && MotoristaVeiculo::where('motorista_id', $motorista->id)->where('veiculo_id', $veiculoId)->exists();
        $veiculo = $vinculado ? Veiculo::liberados()->whereKey($veiculoId)->lockForUpdate()->first() : null;
        if ($veiculo === null) {
            throw new RuntimeException('Selecione um veículo vinculado e com CRLV aprovado para ficar online.', 422);
        }

        return $veiculo;
    }

    private function validarUsoExclusivo(int $motoristaId, Veiculo $veiculo): void
    {
        $ocupado = Corrida::where('veiculo_id', $veiculo->id)->where('motorista_id', '!=', $motoristaId)
            ->whereIn('status_corrida', self::CORRIDAS_ATIVAS)->lockForUpdate()->first(['id']);
        if ($ocupado !== null) {
            throw new RuntimeException('Este veículo está em uma corrida com outro motorista. Aguarde para ficar online.', 409);
        }
        $outros = StatusBusca::where('veiculo_id', $veiculo->id)->where('motorista_id', '!=', $motoristaId)
            ->where('disponivel', true)->lockForUpdate()->get();
        foreach ($outros as $outro) {
            if (! $this->expirou($outro)) {
                throw new RuntimeException('Outro motorista está online com este veículo. Aguarde ele ficar offline.', 409);
            }
            $outro->update(['disponivel' => false]);
        }
    }

    private function expirou(StatusBusca $status): bool
    {
        $limite = max((int) config('precificacao.motorista_online_expira_segundos', 90), 30);

        return $status->visto_em !== null && $status->visto_em->lt(now()->subSeconds($limite));
    }
}
