<?php

namespace App\Services;

use App\Enums\StatusTicket;
use App\Events\TicketAtualizado;
use App\Events\TicketMensagemEnviada;
use App\Models\ChamadoAjuda;
use App\Models\ChamadoMensagem;
use App\Models\Corrida;
use App\Models\Gestor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketsAjudaService
{
    public function validarContexto(User $user, array $dados): string
    {
        $tipo = $dados['solicitante_tipo'] ?? ($user->motorista()->exists() && ! $user->passageiro()->exists() ? 'motorista' : 'passageiro');
        $perfil = $user->{$tipo}()->first();
        if (! $perfil) {
            throw ValidationException::withMessages(['solicitante_tipo' => 'Este usuário não possui o perfil selecionado.']);
        }
        if (! empty($dados['corrida_id'])) {
            abort_unless(Corrida::whereKey($dados['corrida_id'])->where($tipo.'_id', $perfil->id)->exists(), 404, 'Corrida não encontrada para este solicitante.');
        }

        return $tipo;
    }

    public function criar(User $user, array $dados, ?Gestor $gestor = null): ChamadoAjuda
    {
        $tipo = $this->validarContexto($user, $dados);

        return DB::transaction(function () use ($user, $dados, $tipo, $gestor) {
            $ticket = ChamadoAjuda::create([
                'user_id' => $user->id, 'solicitante_tipo' => $tipo, 'corrida_id' => $dados['corrida_id'] ?? null,
                'motivo' => $dados['motivo'], 'descricao' => $dados['descricao'], 'status' => 'open',
            ]);
            $this->registrar($ticket, $gestor ? 'gestor' : 'usuario', $dados['descricao'], $gestor ?? $user);
            event(new TicketAtualizado($ticket->id));

            return $ticket;
        });
    }

    private function registrar(ChamadoAjuda $ticket, string $tipo, string $texto, User|Gestor|null $autor = null, ?string $uuid = null): ChamadoMensagem
    {
        $message = $ticket->mensagens()->create([
            'autor_tipo' => $tipo, 'user_id' => $autor instanceof User ? $autor->id : null,
            'gestor_id' => $autor instanceof Gestor ? $autor->id : null, 'autor_nome' => $autor?->name ?? 'Sistema',
            'conteudo' => $texto, 'client_message_id' => $uuid,
        ]);
        $ticket->ultima_mensagem_em = $message->created_at;
        $ticket->save();
        event(new TicketMensagemEnviada($ticket->id, $message->dados()));

        return $message;
    }

    private function controlarResponsavel(ChamadoAjuda $ticket, Gestor $gestor): void
    {
        // Responsáveis arquivados não impedem que a equipe assuma o atendimento.
        if ($ticket->gestor_responsavel_id && ! Gestor::whereKey($ticket->gestor_responsavel_id)->exists()) {
            $ticket->gestor_responsavel_id = null;
        }
        abort_if($ticket->gestor_responsavel_id && $ticket->gestor_responsavel_id !== $gestor->id && $gestor->perfil_acesso !== 'admin', 409, 'Este ticket está sendo atendido por outro operador.');
    }

    public function enviar(ChamadoAjuda $registro, User|Gestor $autor, string $texto, string $uuid): ChamadoMensagem
    {
        return DB::transaction(function () use ($registro, $autor, $texto, $uuid) {
            $ticket = ChamadoAjuda::lockForUpdate()->findOrFail($registro->id);
            $tipo = $autor instanceof Gestor ? 'gestor' : 'usuario';
            $anterior = $ticket->mensagens()->where('client_message_id', $uuid)->first();
            if ($anterior) {
                abort_unless($anterior->autor_tipo === $tipo && ($tipo === 'gestor' ? $anterior->gestor_id : $anterior->user_id) === $autor->id && $anterior->conteudo === $texto, 409, 'Identificador de mensagem já utilizado.');

                return $anterior;
            }
            abort_if($ticket->status === 'closed', 409, 'Reabra o ticket antes de enviar mensagens.');
            if ($autor instanceof Gestor) {
                $this->controlarResponsavel($ticket, $autor);
                if (! $ticket->gestor_responsavel_id) {
                    $ticket->gestor_responsavel_id = $autor->id;
                    $this->registrar($ticket, 'sistema', $autor->name.' assumiu o atendimento.');
                }
                abort_if($ticket->status === 'resolved', 409, 'Reabra o ticket antes de enviar mensagens.');
                $ticket->status = 'in_progress';
            } elseif (in_array($ticket->status, ['pending', 'resolved'], true)) {
                $ticket->status = $ticket->gestor_responsavel_id ? 'in_progress' : 'open';
                $ticket->resolvido_em = null;
                $this->registrar($ticket, 'sistema', 'O solicitante retomou a conversa.');
            }
            $message = $this->registrar($ticket, $tipo, $texto, $autor, $uuid);
            event(new TicketAtualizado($ticket->id));

            return $message;
        });
    }

    public function assumir(ChamadoAjuda $registro, Gestor $gestor): ChamadoAjuda
    {
        return DB::transaction(function () use ($registro, $gestor) {
            $ticket = ChamadoAjuda::lockForUpdate()->findOrFail($registro->id);
            if ($ticket->gestor_responsavel_id && ! Gestor::whereKey($ticket->gestor_responsavel_id)->exists()) {
                $ticket->gestor_responsavel_id = null;
            }
            abort_if($ticket->gestor_responsavel_id && $ticket->gestor_responsavel_id !== $gestor->id, 409, 'Outro operador já assumiu este ticket.');
            abort_if(in_array($ticket->status, ['resolved', 'closed'], true), 409, 'Reabra o ticket antes de assumir.');
            if ($ticket->gestor_responsavel_id !== $gestor->id) {
                $ticket->gestor_responsavel_id = $gestor->id;
                $ticket->status = 'in_progress';
                $this->registrar($ticket, 'sistema', $gestor->name.' assumiu o atendimento.');
                event(new TicketAtualizado($ticket->id));
            }

            return $ticket;
        });
    }

    public function status(ChamadoAjuda $registro, Gestor $gestor, StatusTicket $status): ChamadoAjuda
    {
        return DB::transaction(function () use ($registro, $gestor, $status) {
            $ticket = ChamadoAjuda::lockForUpdate()->findOrFail($registro->id);
            $this->controlarResponsavel($ticket, $gestor);
            if ($ticket->status === $status->value) {
                return $ticket;
            }
            abort_if($status === StatusTicket::Closed && $ticket->status !== 'resolved', 422, 'Resolva o ticket antes de fechá-lo.');
            abort_if($ticket->status === 'closed' && ! in_array($status, [StatusTicket::Open, StatusTicket::InProgress], true), 422, 'Reabra o ticket para continuar.');
            $ticket->status = $status->value;
            $ticket->resolvido_em = $status === StatusTicket::Resolved ? now() : ($status === StatusTicket::Closed ? $ticket->resolvido_em : null);
            $ticket->fechado_em = $status === StatusTicket::Closed ? now() : null;
            if ($status === StatusTicket::Open) {
                $ticket->gestor_responsavel_id = null;
            } elseif (! $ticket->gestor_responsavel_id) {
                $ticket->gestor_responsavel_id = $gestor->id;
            }
            $this->registrar($ticket, 'sistema', $gestor->name.' alterou o status para '.$status->nome().'.');
            event(new TicketAtualizado($ticket->id));

            return $ticket;
        });
    }
}
