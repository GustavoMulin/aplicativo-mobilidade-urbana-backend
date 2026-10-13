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
        DB::transaction(function (): void {
            $this->validarCredenciaisUnicas();
            $this->migrarDadosExistentes();
        });
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
        });

        Schema::table('passageiros', function (Blueprint $table): void {
            if (! Schema::hasColumn('passageiros', 'foto')) {
                $table->string('foto')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('passageiros', 'status')) {
                $table->string('status')->default('ativo')->after('foto');
            }
        });
    }

    private function credenciaisParaMigrar(object $usuario, ?object $origem): array
    {
        $dados = [];
        foreach (['telefone', 'email', 'email_verified_at', 'password', 'remember_token'] as $campo) {
            if (($usuario->{$campo} ?? null) === null && ($origem->{$campo} ?? null) !== null) {
                $dados[$campo] = $origem->{$campo};
            }
        }

        return $dados;
    }

    private function validarCredenciaisUnicas(): void
    {
        $ocupados = ['telefone' => [], 'email' => []];
        // Reserva primeiro os valores já existentes, inclusive de usuários arquivados.
        DB::table('users')->select('id', 'telefone', 'email')->chunkById(100, function ($usuarios) use (&$ocupados): void {
            foreach ($usuarios as $usuario) {
                foreach (['telefone', 'email'] as $campo) {
                    if ($usuario->{$campo} !== null) {
                        $chave = mb_strtolower(rtrim((string) $usuario->{$campo}));
                        $ocupados[$campo][$chave] = (int) $usuario->id;
                    }
                }
            }
        });

        DB::table('users')->chunkById(100, function ($usuarios) use (&$ocupados): void {
            foreach ($usuarios as $usuario) {
                $passageiro = DB::table('passageiros')->where('user_id', $usuario->id)->first();
                $motorista = DB::table('motoristas')->where('user_id', $usuario->id)->first();
                $dados = $this->credenciaisParaMigrar($usuario, $passageiro ?? $motorista);
                foreach (['telefone', 'email'] as $campo) {
                    if (! array_key_exists($campo, $dados)) {
                        continue;
                    }
                    $chave = mb_strtolower(rtrim((string) $dados[$campo]));
                    $dono = $ocupados[$campo][$chave] ?? null;
                    if ($dono !== null && $dono !== (int) $usuario->id) {
                        throw new RuntimeException("Conflito de {$campo} entre os usuários {$dono} e {$usuario->id}. Corrija os dados de origem antes de executar esta migration.");
                    }
                    $ocupados[$campo][$chave] = (int) $usuario->id;
                }
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

                $dadosUsuario = $this->credenciaisParaMigrar($usuario, $origemCredenciais);

                if ($dadosUsuario !== []) {
                    DB::table('users')->where('id', $usuario->id)->update($dadosUsuario);
                }

                $foto = $usuario->foto ?? $usuario->foto_thumbnail ?? null;
                $status = $usuario->status ?? 'ativo';

                if ($passageiro !== null) {
                    DB::table('passageiros')->where('id', $passageiro->id)->update([
                        'foto' => $passageiro->foto ?? $passageiro->foto_thumbnail ?? $foto,
                        'status' => $passageiro->status ?? $status,
                    ]);
                }

                if ($motorista !== null) {
                    DB::table('motoristas')->where('id', $motorista->id)->update([
                        'foto' => $motorista->foto ?? $motorista->foto_thumbnail ?? $foto,
                    ]);
                }
            }
        });
    }

    private function removerCredenciaisDuplicadasDosPerfis(): void
    {
        foreach (['motoristas', 'passageiros'] as $tabela) {
            $colunas = collect(['telefone', 'email', 'email_verified_at', 'remember_token', 'password', 'foto_thumbnail'])
                ->filter(fn (string $coluna): bool => Schema::hasColumn($tabela, $coluna))
                ->all();

            if ($colunas !== []) {
                Schema::table($tabela, fn (Blueprint $table) => $table->dropColumn($colunas));
            }
        }

        $colunasLegadas = collect(['foto', 'foto_thumbnail'])
            ->filter(fn (string $coluna): bool => Schema::hasColumn('users', $coluna))
            ->all();

        if ($colunasLegadas !== []) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn($colunasLegadas));
        }
    }

    public function down(): void
    {
        // A reversão preserva os dados globais em users. Remover as colunas dos
        // perfis aqui apagaria fotos diferentes que já tenham sido cadastradas.
    }
};
