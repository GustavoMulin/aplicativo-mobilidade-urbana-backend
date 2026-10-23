<?php

namespace App\Services;

use App\Events\TicketAtualizado;
use App\Events\TicketMensagemEnviada;
use App\Models\ChamadoAjuda;
use App\Models\Gestor;
use Illuminate\Support\Facades\DB;

class FilaTicketsService
{
    /** Bloqueia os operadores antes dos tickets, sempre na mesma ordem. */
    public function executar(ChamadoAjuda $registro, array $destinos, callable $acao): mixed
    {
        for ($tentativa = 0; $tentativa < 5; $tentativa++) {
            $resultado = DB::transaction(function () use ($registro, $destinos, $acao) {
                $anterior = ChamadoAjuda::whereKey($registro->id)->value('gestor_responsavel_id');
                $operadores = array_values(array_unique(array_filter([$anterior, ...$destinos])));
                sort($operadores);
                Gestor::withTrashed()->whereIn('id', $operadores)->orderBy('id')->lockForUpdate()->get();
                $ticket = ChamadoAjuda::lockForUpdate()->findOrFail($registro->id);
                // A transferência pode ter ocorrido enquanto esperávamos o operador.
                if ($ticket->gestor_responsavel_id !== $anterior) {
                    return null;
                }
                $retorno = $acao($ticket);
                $this->sincronizar($operadores);
                $ticket->refresh();

                return ['retorno' => $retorno];
            }, 3);
            if ($resultado !== null) {
                return $resultado['retorno'];
            }
        }
        abort(409, 'A fila mudou durante a operação. Tente novamente.');
    }

    public function entrar(ChamadoAjuda $ticket): void
    {
        $ticket->forceFill(['status' => 'waiting', 'fila_entrada_em' => now(), 'fila_posicao' => null])->save();
    }

    /** Chamado apenas dentro da transação que já bloqueou os operadores. */
    public function sincronizar(array $operadores): void
    {
        foreach (array_unique(array_filter($operadores)) as $operador) {
            $tickets = ChamadoAjuda::where('gestor_responsavel_id', $operador)
                ->whereIn('status', ['in_progress', 'waiting'])
                ->orderBy('fila_entrada_em')->orderBy('id')->lockForUpdate()->get();
            $espera = $tickets->where('status', 'in_progress')->count();
            foreach ($tickets->sortBy(fn ($ticket) => $ticket->status === 'in_progress' ? 0 : 1) as $ticket) {
                $emAtendimento = $ticket->status === 'in_progress';
                $posicao = $emAtendimento ? 0 : $espera++;
                $status = $emAtendimento ? 'in_progress' : 'waiting';
                $mudou = $ticket->status !== $status || $ticket->fila_posicao !== $posicao;
                $ticket->forceFill([
                    'status' => $status, 'fila_posicao' => $posicao,
                    'fila_entrada_em' => $ticket->fila_entrada_em ?? now(),
                ]);
                if ($ticket->isDirty()) {
                    $ticket->save();
                }
                if ($mudou) {
                    $this->avisar($ticket, $status === 'in_progress'
                        ? 'Chegou sua vez! Seu atendimento foi iniciado.'
                        : self::mensagemEspera($posicao));
                }
            }
            ChamadoAjuda::where('gestor_responsavel_id', $operador)
                ->whereNotIn('status', ['in_progress', 'waiting'])
                ->where(fn ($query) => $query->whereNotNull('fila_posicao')->orWhereNotNull('fila_entrada_em'))
                ->update(['fila_posicao' => null, 'fila_entrada_em' => null]);
        }
    }

    /** Ninguém assumiu ainda: quantos pedidos sem atendente chegaram antes deste. */
    public static function aFrenteNaFilaGeral(ChamadoAjuda $ticket): int
    {
        return ChamadoAjuda::where('status', 'open')
            ->whereNull('gestor_responsavel_id')
            ->where(fn ($query) => $query->where('created_at', '<', $ticket->created_at)
                ->orWhere(fn ($empate) => $empate->where('created_at', $ticket->created_at)->where('id', '<', $ticket->id)))
            ->count();
    }

    public static function mensagemEspera(int $posicao): string
    {
        if ($posicao === 0) {
            return 'Você é o próximo da fila. Aguarde o operador iniciar seu atendimento.';
        }

        return $posicao === 1
            ? 'Tem 1 pessoa na sua frente. Aguarde para ser atendido.'
            : 'Tem '.$posicao.' pessoas na sua frente. Aguarde para ser atendido.';
    }

    public function iniciar(ChamadoAjuda $ticket): void
    {
        $this->sincronizar([$ticket->gestor_responsavel_id]);
        $ticket->refresh();
        if ($ticket->status === 'in_progress') {
            return;
        }
        abort_unless($ticket->status === 'waiting', 409,
            'Este chamado precisa estar aguardando para ser iniciado.');
        $ticket->forceFill(['status' => 'in_progress'])->save();
        // Atualiza o aviso mesmo quando a posição permanece zero.
        $this->avisar($ticket, 'Chegou sua vez! Seu atendimento foi iniciado.');
    }

    private function avisar(ChamadoAjuda $ticket, string $texto): void
    {
        $mensagem = $ticket->mensagens()->updateOrCreate(['tipo_sistema' => 'fila_atendimento'], [
            'autor_tipo' => 'sistema', 'autor_nome' => 'Sistema', 'conteudo' => $texto,
        ]);
        $ticket->forceFill(['ultima_mensagem_em' => $mensagem->updated_at])->save();
        event(new TicketMensagemEnviada($ticket->id, $mensagem->dados()));
        event(new TicketAtualizado($ticket->id));
    }
}
