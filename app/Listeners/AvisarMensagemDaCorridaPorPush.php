<?php

namespace App\Listeners;

use App\Events\CorridaMensagemEnviada;
use App\Models\Corrida;
use App\Models\CorridaMensagem;
use App\Models\Motorista;
use App\Models\Passageiro;
use App\Services\EnviarPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;

/**
 * Push da conversa da corrida para o outro participante (motorista ↔
 * passageiro). Fica fora do ChatCorridaService e dos canais de broadcast:
 * o evento só traz os ids, e a mensagem é lida do banco já gravada.
 * Não passa pela tabela notificacoes, para não lotar a tela de avisos.
 */
class AvisarMensagemDaCorridaPorPush implements ShouldQueue
{
    public string $queue = 'push';

    public bool $afterCommit = true;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 30];

    public function __construct(private readonly EnviarPushService $push) {}

    // sem Firebase ou sem aparelho do outro lado, nem entra na fila
    public function shouldQueue(CorridaMensagemEnviada $evento): bool
    {
        $destino = $this->destino($evento);

        return $destino !== null
            && $this->push->configurado()
            && $this->push->temAparelho($destino['user_id'], $destino['perfil']);
    }

    public function handle(CorridaMensagemEnviada $evento): void
    {
        $destino = $this->destino($evento);

        if ($destino === null) {
            return;
        }

        $mensagem = $destino['mensagem'];
        $nome = Str::before(trim((string) $mensagem->autor_nome), ' ');
        $papel = $mensagem->autor_tipo === 'motorista' ? 'Motorista' : 'Passageiro';

        $this->push->paraUsuario(
            $destino['user_id'],
            $destino['perfil'],
            $nome !== '' ? "{$nome} · {$papel}" : $papel,
            Str::limit((string) $mensagem->conteudo, 140),
            [
                'tipo' => 'chat_corrida',
                'corrida_id' => (string) $mensagem->corrida_id,
                'mensagem_id' => (string) $mensagem->id,
            ],
            // reentrega da fila substitui a notificação em vez de repetir
            "chat_corrida:{$mensagem->id}",
        );
    }

    /**
     * Quem recebe é sempre o outro lado da corrida, pelo users.id dele.
     *
     * @return array{user_id: int, perfil: string, mensagem: CorridaMensagem}|null
     */
    private function destino(CorridaMensagemEnviada $evento): ?array
    {
        $mensagem = CorridaMensagem::find($evento->mensagemId);

        if ($mensagem === null || (int) $mensagem->corrida_id !== $evento->corridaId) {
            return null;
        }

        $corrida = Corrida::find($mensagem->corrida_id);

        if ($corrida === null) {
            return null;
        }

        [$perfil, $userId] = match ($mensagem->autor_tipo) {
            'motorista' => ['passageiro', Passageiro::whereKey($corrida->passageiro_id)->value('user_id')],
            'passageiro' => ['motorista', Motorista::whereKey($corrida->motorista_id)->value('user_id')],
            default => [null, null],
        };

        // mesma conta nos dois papéis: não avisa o próprio autor
        if ($perfil === null || $userId === null || (int) $userId === (int) $mensagem->user_id) {
            return null;
        }

        return ['user_id' => (int) $userId, 'perfil' => $perfil, 'mensagem' => $mensagem];
    }
}
