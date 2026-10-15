<?php

namespace App\Services;

use App\Events\CorridaMensagemEnviada;
use App\Models\ChamadoAjuda;
use App\Models\Corrida;
use App\Models\CorridaMensagem;
use App\Models\Gestor;
use App\Models\Motorista;
use App\Models\Passageiro;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChatCorridaService
{
    public const STATUS_ENVIO = ['aceita', 'motorista_chegou', 'em_andamento'];

    public function perfilParticipante(Corrida $corrida, User $usuario): ?string
    {
        if (Passageiro::whereKey($corrida->passageiro_id)->where('user_id', $usuario->id)->exists()) {
            return 'passageiro';
        }
        if ($corrida->motorista_id && Motorista::whereKey($corrida->motorista_id)->where('user_id', $usuario->id)->exists()) {
            return 'motorista';
        }

        return null;
    }

    public function gestorPodeConsultar(Gestor $gestor, int $corridaId): bool
    {
        return ChamadoAjuda::where('corrida_id', $corridaId)->get(['id', 'gestor_responsavel_id'])
            ->contains(fn ($ticket) => $gestor->podeAcessarTicket($ticket));
    }

    public function listar(Corrida $corrida, array $dados, bool $podeEnviar = false): array
    {
        $limite = $dados['limit'] ?? 50;
        $depois = isset($dados['after_id']);
        $consulta = CorridaMensagem::where('corrida_id', $corrida->id);
        if ($depois) {
            $consulta->where('id', '>', $dados['after_id']);
        }
        if (isset($dados['before_id'])) {
            $consulta->where('id', '<', $dados['before_id']);
        }
        $itens = $consulta->orderBy('id', $depois ? 'asc' : 'desc')->limit($limite + 1)->get();
        $mais = $itens->count() > $limite;
        $itens = $itens->take($limite)->sortBy('id')->values();
        $corrida->loadMissing(['passageiro.user', 'motorista.user']);

        return [
            'corrida' => [
                'id' => $corrida->id, 'codigo_corrida' => $corrida->codigo_corrida, 'status' => $corrida->status_corrida,
                'passageiro' => ['nome' => $corrida->passageiro?->user?->name ?? 'Passageiro'],
                'motorista' => ['nome' => $corrida->motorista?->user?->name ?? 'Motorista não designado'],
            ],
            'pode_enviar' => $podeEnviar && in_array($corrida->status_corrida, self::STATUS_ENVIO, true) && $corrida->motorista_id !== null,
            'data' => $itens->map->dados(), 'has_more' => $mais,
            'next_before_id' => $itens->first()?->id, 'next_after_id' => $itens->last()?->id,
        ];
    }

    public function enviar(Corrida $registro, User $usuario, string $texto, string $uuid): CorridaMensagem
    {
        return DB::transaction(function () use ($registro, $usuario, $texto, $uuid) {
            $corrida = Corrida::whereKey($registro->id)->lockForUpdate()->firstOrFail();
            $perfil = $this->perfilParticipante($corrida, $usuario);
            abort_unless($perfil, 404, 'Corrida não encontrada para este usuário.');
            $anterior = CorridaMensagem::where('corrida_id', $corrida->id)->where('client_message_id', $uuid)->first();
            if ($anterior) {
                abort_unless($anterior->user_id === $usuario->id && $anterior->conteudo === $texto, 409, 'Identificador de mensagem já utilizado.');

                return $anterior;
            }
            abort_unless(in_array($corrida->status_corrida, self::STATUS_ENVIO, true) && $corrida->motorista_id !== null,
                409, 'O chat permite mensagens do aceite até a finalização ou cancelamento da corrida.');
            $mensagem = CorridaMensagem::create([
                'corrida_id' => $corrida->id, 'user_id' => $usuario->id,
                'autor_tipo' => $perfil, 'autor_nome' => $usuario->name,
                'conteudo' => $texto, 'client_message_id' => $uuid,
            ]);
            event(new CorridaMensagemEnviada($corrida->id, $mensagem->id));

            return $mensagem;
        }, 3);
    }
}
