<?php

namespace Database\Factories;

use App\Models\Cidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cidade>
 */
class CidadeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->unique()->city(),
            'uf' => 'RO',
            'ibge' => fake()->unique()->numberBetween(1100000, 1199999),
        ];
    }
}
