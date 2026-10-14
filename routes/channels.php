<?php

use App\Models\ChamadoAjuda;
use App\Models\Corrida;
use App\Models\Gestor;
use App\Models\Motorista;
use App\Models\Passageiro;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('corrida.{corridaId}', function (User $user, int $corridaId) {
    $corrida = Corrida::find($corridaId);

    if ($corrida === null) {
        return false;
    }

    $passageiroId = Passageiro::where('user_id', $user->id)->value('id');

    if ($passageiroId !== null && $corrida->passageiro_id === (int) $passageiroId) {
        return true;
    }

    $motoristaId = Motorista::where('user_id', $user->id)->value('id');

    return $motoristaId !== null && $corrida->motorista_id === (int) $motoristaId;
});

Broadcast::channel('corridas-disponiveis', function (User $user) {
    return Motorista::where('user_id', $user->id)->where('status', 'ativo')->exists();
});

Broadcast::channel('ajuda.ticket.{ticketId}.cliente', function (User $user, int $ticketId) {
    return ChamadoAjuda::whereKey($ticketId)->where('user_id', $user->id)->exists();
}, ['guards' => ['jwt']]);

Broadcast::channel('ajuda.ticket.{ticketId}.suporte', function (Gestor $gestor, int $ticketId) {
    return $gestor->pode('ajuda.gerenciar') && ChamadoAjuda::whereKey($ticketId)->exists();
}, ['guards' => ['gestores']]);

Broadcast::channel('ajuda.suporte', fn (Gestor $gestor) => $gestor->pode('ajuda.gerenciar'), ['guards' => ['gestores']]);
