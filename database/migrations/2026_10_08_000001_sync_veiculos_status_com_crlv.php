<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $ultimos = DB::table('motorista_documentos_anexos')
                ->where('tipo_documento', 'crlv')->whereNotNull('veiculo_id')
                ->groupBy('veiculo_id')->selectRaw('MAX(id)');
            DB::table('motorista_documentos_anexos')->whereIn('id', $ultimos)
                ->orderBy('id')->each(function ($documento) {
                    DB::table('veiculos')->where('id', $documento->veiculo_id)
                        ->update(['status' => $documento->status, 'updated_at' => now()]);
                });
        });
    }

    public function down(): void
    {
        // A correção dos status existentes não deve voltar a liberar CRLV sem aprovação.
    }
};
