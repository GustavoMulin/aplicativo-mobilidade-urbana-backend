<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['passageiros', 'motoristas'] as $tabela) {
            if (! Schema::hasColumn($tabela, 'foto')) {
                Schema::table($tabela, fn (Blueprint $table) => $table->string('foto')->nullable()->after('user_id'));
            }

            $this->copiarFotoParaPerfil($tabela);

            if (Schema::hasColumn($tabela, 'foto_thumbnail')) {
                Schema::table($tabela, fn (Blueprint $table) => $table->dropColumn('foto_thumbnail'));
            }
        }

        $colunasGlobais = collect(['foto', 'foto_thumbnail'])
            ->filter(fn (string $coluna): bool => Schema::hasColumn('users', $coluna))
            ->all();

        if ($colunasGlobais !== []) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn($colunasGlobais));
        }
    }

    private function copiarFotoParaPerfil(string $tabela): void
    {
        $perfilTemMiniatura = Schema::hasColumn($tabela, 'foto_thumbnail');
        $usuarioTemFoto = Schema::hasColumn('users', 'foto');
        $usuarioTemMiniatura = Schema::hasColumn('users', 'foto_thumbnail');

        if (! $perfilTemMiniatura && ! $usuarioTemFoto && ! $usuarioTemMiniatura) {
            return;
        }

        DB::table($tabela)
            ->join('users', 'users.id', '=', $tabela.'.user_id')
            ->select($tabela.'.id')
            ->orderBy($tabela.'.id')
            ->chunkById(100, function ($perfis) use ($tabela, $perfilTemMiniatura, $usuarioTemFoto, $usuarioTemMiniatura): void {
                foreach ($perfis as $perfil) {
                    $registro = DB::table($tabela)
                        ->join('users', 'users.id', '=', $tabela.'.user_id')
                        ->where($tabela.'.id', $perfil->id)
                        ->select([
                            $tabela.'.foto as foto_perfil',
                            ...($perfilTemMiniatura ? [$tabela.'.foto_thumbnail as miniatura_perfil'] : []),
                            ...($usuarioTemFoto ? ['users.foto as foto_usuario'] : []),
                            ...($usuarioTemMiniatura ? ['users.foto_thumbnail as miniatura_usuario'] : []),
                        ])
                        ->first();

                    $foto = data_get($registro, 'foto_perfil')
                        ?? data_get($registro, 'miniatura_perfil')
                        ?? data_get($registro, 'foto_usuario')
                        ?? data_get($registro, 'miniatura_usuario');

                    if ($foto !== null) {
                        DB::table($tabela)->where('id', $perfil->id)->update(['foto' => $foto]);
                    }
                }
            }, $tabela.'.id', 'id');
    }

    public function down(): void
    {
        // A coluna única de cada perfil é a fonte definitiva da imagem.
    }
};
