<?php

namespace App\Services;

use App\Models\Notificacao;

// registra o que aconteceu com a conta (corrida, pagamento) para aparecer na lista
// de notificações do aplicativo e manda o mesmo aviso por push para o celular;
// o texto nunca leva dados pessoais de terceiros
class NotificarUsuarioService
{
    public function __construct(private readonly EnviarPushService $push) {}

    /**
     * @param  'passageiro'|'motorista'  $perfil  app em que a notificação aparece
     * @param  array<string, string>  $dados  vão junto no push (ex.: corrida_id)
     */
    public function executar(int $userId, string $perfil, string $titulo, string $mensagem, array $dados = []): void
    {
        $titulo = mb_substr($titulo, 0, 120);
        $mensagem = mb_substr($mensagem, 0, 255);

        Notificacao::create([
            'user_id' => $userId,
            'perfil' => $perfil,
            'titulo' => $titulo,
            'mensagem' => $mensagem,
        ]);

        // depois da resposta: o FCM não atrasa quem mudou o status da corrida
        dispatch(fn () => $this->push->paraUsuario($userId, $perfil, $titulo, $mensagem, $dados))->afterResponse();
    }
}
