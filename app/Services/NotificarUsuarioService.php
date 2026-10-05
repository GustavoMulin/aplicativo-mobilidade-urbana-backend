<?php

namespace App\Services;

use App\Models\Notificacao;

// registra o que aconteceu com a conta (corrida, pagamento) para aparecer na lista
// de notificações do aplicativo; o texto nunca leva dados pessoais de terceiros
class NotificarUsuarioService
{
    public function executar(int $userId, string $titulo, string $mensagem): void
    {
        Notificacao::create([
            'user_id' => $userId,
            'titulo' => mb_substr($titulo, 0, 120),
            'mensagem' => mb_substr($mensagem, 0, 255),
        ]);
    }
}
