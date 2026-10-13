<?php

use App\Models\Motorista;
use App\Services\AtualizarSituacaoMotoristaService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Motorista::withTrashed()->select('id')->chunkById(100, function ($motoristas): void {
            foreach ($motoristas as $registro) {
                DB::transaction(function () use ($registro): void {
                    $motorista = Motorista::withTrashed()->lockForUpdate()->findOrFail($registro->id);
                    app(AtualizarSituacaoMotoristaService::class)->executar($motorista);
                });
            }
        });
    }

    public function down(): void
    {
        // Preserva a situação calculada a partir dos documentos atuais.
    }
};
