<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Código IBGE do estado (os 2 primeiros dígitos do código do município)
     * para a sigla.
     *
     * @var array<int, string>
     */
    private const SIGLAS = [
        11 => 'RO', 12 => 'AC', 13 => 'AM', 14 => 'RR', 15 => 'PA', 16 => 'AP', 17 => 'TO',
        21 => 'MA', 22 => 'PI', 23 => 'CE', 24 => 'RN', 25 => 'PB', 26 => 'PE', 27 => 'AL',
        28 => 'SE', 29 => 'BA', 31 => 'MG', 32 => 'ES', 33 => 'RJ', 35 => 'SP', 41 => 'PR',
        42 => 'SC', 43 => 'RS', 50 => 'MS', 51 => 'MT', 52 => 'GO', 53 => 'DF',
    ];

    /**
     * A UF da cidade passa de número (código IBGE do estado) para a sigla, e o
     * banner ganha chave estrangeira para a cidade. A URL do banner deixa de
     * ser gravada: sai do disco onde a imagem está (ver BannerPublicidade).
     */
    public function up(): void
    {
        Schema::table('cidades', function (Blueprint $table) {
            $table->char('uf_sigla', 2)->nullable()->after('uf');
        });

        // a sigla vem do código do município; o número antigo da coluna uf
        // chegou a ser gravado errado no banco de desenvolvimento
        foreach (DB::table('cidades')->get(['id', 'uf', 'ibge']) as $cidade) {
            $estado = intdiv((int) $cidade->ibge, 100000);

            DB::table('cidades')->where('id', $cidade->id)->update([
                'uf_sigla' => self::SIGLAS[$estado] ?? self::SIGLAS[(int) $cidade->uf] ?? 'RO',
            ]);
        }

        Schema::table('cidades', function (Blueprint $table) {
            $table->dropColumn('uf');
        });

        Schema::table('cidades', function (Blueprint $table) {
            $table->renameColumn('uf_sigla', 'uf');
        });

        Schema::table('cidades', function (Blueprint $table) {
            $table->char('uf', 2)->nullable(false)->change();
            $table->index(['uf', 'nome']);
        });

        if (! Schema::hasColumn('banner_publicidades', 'cidade_id')) {
            Schema::table('banner_publicidades', function (Blueprint $table) {
                $table->unsignedBigInteger('cidade_id')->nullable()->after('id');
            });
        }

        $cidadeId = DB::table('cidades')->orderBy('id')->value('id');
        if ($cidadeId !== null) {
            DB::table('banner_publicidades')
                ->whereNull('cidade_id')
                ->update(['cidade_id' => $cidadeId]);
        }

        $possuiBannerSemCidade = DB::table('banner_publicidades')
            ->whereNull('cidade_id')
            ->exists();

        Schema::table('banner_publicidades', function (Blueprint $table) use ($possuiBannerSemCidade) {
            if (! $possuiBannerSemCidade) {
                $table->unsignedBigInteger('cidade_id')->nullable(false)->change();
            }
            $table->foreign('cidade_id')->references('id')->on('cidades')->restrictOnDelete();
            $table->dropColumn('url');
        });
    }

    public function down(): void
    {
        Schema::table('banner_publicidades', function (Blueprint $table) {
            $table->dropForeign(['cidade_id']);
            $table->dropColumn('cidade_id');
            $table->string('url')->default('');
        });

        Schema::table('cidades', function (Blueprint $table) {
            $table->dropIndex(['uf', 'nome']);
            $table->integer('uf_codigo')->nullable()->after('uf');
        });

        $codigos = array_flip(self::SIGLAS);
        foreach (DB::table('cidades')->get(['id', 'uf']) as $cidade) {
            DB::table('cidades')->where('id', $cidade->id)->update([
                'uf_codigo' => $codigos[$cidade->uf] ?? 11,
            ]);
        }

        Schema::table('cidades', function (Blueprint $table) {
            $table->dropColumn('uf');
        });

        Schema::table('cidades', function (Blueprint $table) {
            $table->renameColumn('uf_codigo', 'uf');
        });

        Schema::table('cidades', function (Blueprint $table) {
            $table->integer('uf')->nullable(false)->change();
        });
    }
};
