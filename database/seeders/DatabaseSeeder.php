<?php

namespace Database\Seeders;

use App\Models\Cidade;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        Cidade::firstOrCreate(['id' => 4382], [
            'nome' => 'Porto Velho', 'uf' => 'RO', 'ibge' => 1100205,
        ]);
        Cidade::firstOrCreate(['id' => 4350], [
            'nome' => 'Ariquemes', 'uf' => 'RO', 'ibge' => 1100023,
        ]);

        $this->call([
            PrecificacaoSeeder::class,
            CadastroDesenvolvimentoSeeder::class,
        ]);
    }
}
