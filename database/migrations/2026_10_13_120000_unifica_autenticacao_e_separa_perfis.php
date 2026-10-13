<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->garantirCredenciaisNoUsuario();
        $this->garantirDadosDosPerfis();
        $this->migrarDadosExistentes();
        $this->removerCredenciaisDuplicadasDosPerfis();
    }

    private function garantirCredenciaisNoUsuario(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'telefone')) {
                $table->string('telefone')->nullable()->unique()->after('name');
            }
            if (! Schema::hasColumn('users', 'email')) {
                $table->string('email')->nullable()->unique()->after('data_nascimento');
            }
            if (! Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'foto')) {
                $table->string('foto')->nullable()->after('email_verified_at');
            }
            if (! Schema::hasColumn('users', 'foto_thumbnail')) {
                $table->string('foto_thumbnail')->nullable()->after('foto');
            }
            if (! Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken();
            }
            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('ativo')->after('remember_token');
            }
            if (! Schema::hasColumn('users', 'password')) {
                $table->string('password')->nullable()->after('status');
            }
        });
    }

    private function garantirDadosDosPerfis(): void
    {
        Schema::table('motoristas', function (Blueprint $table): void {
            if (! Schema::hasColumn('motoristas', 'foto')) {
                $table->string('foto')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('motoristas', 'foto_thumbnail')) {
                $table->string('foto_thumbnail')->nullable()->after('foto');
            }
        });

        Schema::table('passageiros', function (Blueprint $table): void {
            if (! Schema::hasColumn('passageiros', 'foto')) {
                $table->string('foto')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('passageiros', 'foto_thumbnail')) {
                $table->string('foto_thumbnail')->nullable()->after('foto');
            }
            if (! Schema::hasColumn('passageiros', 'status')) {
                $table->string('status')->default('ativo')->after('foto_thumbnail');
            }
        });
    }

    private function migrarDadosExistentes(): void
    {
        DB::table('users')->orderBy('id')->chunkById(100, function ($usuarios): void {
            foreach ($usuarios as $usuario) {
                $passageiro = DB::table('passageiros')->where('user_id', $usuario->id)->first();
                $motorista = DB::table('motoristas')->where('user_id', $usuario->id)->first();
                $origemCredenciais = $passageiro ?? $motorista;

                $dadosUsuario = [];
                foreach (['telefone', 'email', 'email_verified_at', 'password', 'remember_token'] as $campo) {
                    if (($usuario->{$campo} ?? null) === null && ($origemCredenciais->{$campo} ?? null) !== null) {
                        $dadosUsuario[$campo] = $origemCredenciais->{$campo};
                    }
                }

                if ($dadosUsuario !== []) {
                    DB::table('users')->where('id', $usuario->id)->update($dadosUsuario);
                }

                $foto = $usuario->foto ?? null;
                $miniatura = $usuario->foto_thumbnail ?? null;
                $status = $usuario->status ?? 'ativo';

                if ($passageiro !== null) {
                    DB::table('passageiros')->where('id', $passageiro->id)->update([
                        'foto' => $passageiro->foto ?? $foto,
                        'foto_thumbnail' => $passageiro->foto_thumbnail ?? $miniatura,
                        'status' => $passageiro->status ?? $status,
                    ]);
                }

                if ($motorista !== null) {
                    DB::table('motoristas')->where('id', $motorista->id)->update([
                        'foto' => $motorista->foto ?? $foto,
                        'foto_thumbnail' => $motorista->foto_thumbnail ?? $miniatura,
                    ]);
                }
            }
        });
    }

    private function removerCredenciaisDuplicadasDosPerfis(): void
    {
        foreach (['motoristas', 'passageiros'] as $tabela) {
            $colunas = collect(['telefone', 'email', 'email_verified_at', 'remember_token', 'password'])
                ->filter(fn (string $coluna): bool => Schema::hasColumn($tabela, $coluna))
                ->all();

            if ($colunas !== []) {
                Schema::table($tabela, fn (Blueprint $table) => $table->dropColumn($colunas));
            }
        }
    }

    public function down(): void
    {
        // A reversão preserva os dados globais em users. Remover as colunas dos
        // perfis aqui apagaria fotos diferentes que já tenham sido cadastradas.
    }
};
