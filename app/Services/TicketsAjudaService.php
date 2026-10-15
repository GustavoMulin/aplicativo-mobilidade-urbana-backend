<?php

namespace App\Services;

use App\Enums\StatusTicket;
use App\Events\TicketAtualizado;
use App\Events\TicketMensagemEnviada;
use App\Events\TicketTransferredEvent;
use App\Models\ChamadoAjuda;
use App\Models\ChamadoMensagem;
use App\Models\Corrida;
use App\Models\Gestor;
use App\Models\TicketTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketsAjudaService
{
    public function __construct(private FilaTicketsService $fila) {}

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

    private function acesso(ChamadoAjuda $ticket, User|Gestor $autor): void
    {
        // Reavalia após lockForUpdate: uma transferência pode ter ocorrido depois do controller.
        abort_unless($autor instanceof Gestor ? $autor->podeAcessarTicket($ticket) : $ticket->user_id === $autor->id, 404);
    }

    private function atribuir(ChamadoAjuda $ticket, ?Gestor $destino, Gestor $autor, string $tipo, ?string $nota = null): void
    {
        if ($ticket->gestor_responsavel_id === $destino?->id) {
            return;
        }
        $anteriorId = $ticket->gestor_responsavel_id;
        $anterior = $anteriorId ? Gestor::withTrashed()->find($anteriorId) : null;
        $ticket->gestor_responsavel_id = $destino?->id;
        $ticket->forceFill(['fila_posicao' => null, 'fila_entrada_em' => null]);
        $ticket->save();
        if ($destino) {
            $this->fila->entrar($ticket);
        }
        $transferencia = TicketTransfer::create([
            'ticket_id' => $ticket->id, 'transferred_by_user_id' => $autor->id,
            'from_user_id' => $anteriorId, 'to_user_id' => $destino?->id,
            'transferred_by_name' => $autor->name, 'from_user_name' => $anterior?->name,
            'to_user_name' => $destino?->name, 'type' => $tipo, 'note' => $nota, 'created_at' => now(),
        ]);
        $texto = match (true) {
            $destino === null => $autor->name.' devolveu o atendimento à fila geral.',
            $destino->id === $autor->id => $autor->name.' assumiu o atendimento'.($anterior ? ', antes com '.$anterior->name : '').'.',
            $anterior !== null => $autor->name.' transferiu o atendimento de '.$anterior->name.' para '.$destino->name.'.',
            default => $autor->name.' atribuiu o atendimento para '.$destino->name.'.',
        };
        $this->registrar($ticket, 'sistema', $texto);
        event(new TicketTransferredEvent($ticket->id, $transferencia->id, $anteriorId, $destino?->id));
    }

    public function transferir(ChamadoAjuda $registro, Gestor $autor, int $destinoId, ?string $nota): ChamadoAjuda
    {
        return $this->fila->executar($registro, [$destinoId], function (ChamadoAjuda $ticket) use ($autor, $destinoId, $nota) {
            $this->acesso($ticket, $autor);
            abort_if(in_array($ticket->status, ['resolved', 'closed'], true), 409, 'Reabra o ticket antes de transferir.');
            $destino = Gestor::whereKey($destinoId)->lockForUpdate()->first();
            if (! $destino || ! $destino->pode('ajuda.gerenciar')) {
                throw ValidationException::withMessages(['to_user_id' => 'Selecione um operador ativo com acesso à Central de ajuda.']);
            }
            if ($ticket->gestor_responsavel_id === $destino->id) {
                throw ValidationException::withMessages(['to_user_id' => 'Este operador já é responsável pelo atendimento.']);
            }
            $tipo = $ticket->gestor_responsavel_id === null ? 'queue_distribution' : 'manual_transfer';
            $this->atribuir($ticket, $destino, $autor, $tipo, $nota);
            event(new TicketAtualizado($ticket->id));

            return $ticket;
        });
    }

    public function enviar(ChamadoAjuda $registro, User|Gestor $autor, string $texto, string $uuid): ChamadoMensagem
    {
        return $this->fila->executar($registro, $autor instanceof Gestor ? [$autor->id] : [], function (ChamadoAjuda $ticket) use ($autor, $texto, $uuid) {
            $this->acesso($ticket, $autor);
            $tipo = $autor instanceof Gestor ? 'gestor' : 'usuario';
            $anterior = $ticket->mensagens()->where('client_message_id', $uuid)->first();
            if ($anterior) {
                abort_unless($anterior->autor_tipo === $tipo && ($tipo === 'gestor' ? $anterior->gestor_id : $anterior->user_id) === $autor->id && $anterior->conteudo === $texto, 409, 'Identificador de mensagem já utilizado.');

                return $anterior;
            }
            abort_if($ticket->status === 'closed', 409, 'Reabra o ticket antes de enviar mensagens.');
            if ($autor instanceof Gestor) {
                abort_if($ticket->gestor_responsavel_id && $ticket->gestor_responsavel_id !== $autor->id, 409, 'Assuma o atendimento antes de enviar uma mensagem.');
                abort_if($ticket->status === 'resolved', 409, 'Reabra o ticket antes de enviar mensagens.');
                abort_unless($ticket->gestor_responsavel_id === $autor->id && $ticket->status === 'in_progress',
                    409, 'Inicie o atendimento antes de responder este ticket.');
            } elseif (in_array($ticket->status, ['pending', 'resolved'], true)) {
                if ($ticket->gestor_responsavel_id) {
                    $this->fila->entrar($ticket);
                } else {
                    $ticket->status = 'open';
                }
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
        return $this->fila->executar($registro, [$gestor->id], function (ChamadoAjuda $ticket) use ($gestor) {
            $this->acesso($ticket, $gestor);
            abort_if(in_array($ticket->status, ['resolved', 'closed'], true), 409, 'Reabra o ticket antes de assumir.');
            if ($ticket->gestor_responsavel_id !== $gestor->id) {
                $tipo = $ticket->gestor_responsavel_id ? 'takeover_by_manager' : 'queue_distribution';
                $this->atribuir($ticket, $gestor, $gestor, $tipo);
                event(new TicketAtualizado($ticket->id));
            }

            $this->fila->iniciar($ticket);
            event(new TicketAtualizado($ticket->id));

            return $ticket;
        });
    }

    public function status(ChamadoAjuda $registro, Gestor $gestor, StatusTicket $status): ChamadoAjuda
    {
        return $this->fila->executar($registro, [$gestor->id], function (ChamadoAjuda $ticket) use ($gestor, $status) {
            $this->acesso($ticket, $gestor);
            abort_if($status === StatusTicket::Waiting, 422, 'A posição na fila é definida automaticamente.');
            abort_if($ticket->status === 'waiting' && ! in_array($status, [StatusTicket::Open, StatusTicket::InProgress], true), 409, 'Aguarde sua vez na fila antes de alterar o atendimento.');
            if ($ticket->status === $status->value) {
                return $ticket;
            }
            abort_if($status === StatusTicket::Closed && $ticket->status !== 'resolved', 422, 'Resolva o ticket antes de fechá-lo.');
            abort_if($ticket->status === 'closed' && ! in_array($status, [StatusTicket::Open, StatusTicket::InProgress], true), 422, 'Reabra o ticket para continuar.');
            if ($status === StatusTicket::InProgress) {
                abort_if($ticket->gestor_responsavel_id && $ticket->gestor_responsavel_id !== $gestor->id, 409, 'Assuma o atendimento antes de iniciá-lo.');
                if (! $ticket->gestor_responsavel_id) {
                    $this->atribuir($ticket, $gestor, $gestor, 'queue_distribution');
                } elseif ($ticket->status !== 'waiting') {
                    $this->fila->entrar($ticket);
                }
                $this->fila->iniciar($ticket);
            } else {
                $ticket->status = $status->value;
            }
            $ticket->resolvido_em = $status === StatusTicket::Resolved ? now() : ($status === StatusTicket::Closed ? $ticket->resolvido_em : null);
            $ticket->fechado_em = $status === StatusTicket::Closed ? now() : null;
            if ($status === StatusTicket::Open) {
                $this->atribuir($ticket, null, $gestor, 'queue_return');
            } elseif (! $ticket->gestor_responsavel_id) {
                $this->atribuir($ticket, $gestor, $gestor, 'queue_distribution');
                if ($status !== StatusTicket::InProgress) {
                    $ticket->status = $status->value;
                }
            }
            $this->registrar($ticket, 'sistema', $gestor->name.' alterou o status para '.$status->nome().'.');
            event(new TicketAtualizado($ticket->id));

            return $ticket;
        });
    }
}
