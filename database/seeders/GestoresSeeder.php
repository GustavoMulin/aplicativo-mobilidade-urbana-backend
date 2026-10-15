<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class GestoresSeeder extends Seeder
{
    public function run(): void
    {
        $gestores = [
            [
                'name' => 'Gestor Um',
                'perfil_acesso' => 'admin',
                'cpf' => '52998224725',
                'data_nascimento' => '1990-01-15',
                'email' => 'gestor1@example.com',
            ],
            [
                'name' => 'Gestor Dois',
                'perfil_acesso' => 'gestao',
                'cpf' => '11144477735',
                'data_nascimento' => '1992-05-20',
                'email' => 'gestor2@example.com',
            ],
            [
                'name' => 'Gestor Três',
                'perfil_acesso' => 'atendimento',
                'cpf' => '12345678909',
                'data_nascimento' => '1994-09-10',
                'email' => 'gestor3@example.com',
            ],
            [
                'name' => 'Suporte técnico de teste',
                'perfil_acesso' => 'suporte_tecnico',
                'cpf' => '39053344705',
                'data_nascimento' => '1993-03-15',
                'email' => 'suporte.tecnico@example.com',
            ],
        ];

        DB::transaction(function () use ($gestores): void {
            foreach ($gestores as $gestor) {
                if (DB::table('gestores')->where('email', $gestor['email'])->exists()) {
                    continue;
                }

                DB::table('gestores')->insert([
                    ...$gestor,
                    'password' => Hash::make('password'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
}
