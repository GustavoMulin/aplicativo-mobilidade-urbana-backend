<?php

namespace Database\Factories;

use App\Models\BannerPublicidade;
use App\Models\Cidade;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BannerPublicidade>
 */
class BannerPublicidadeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cidade_id' => Cidade::factory(),
            'titulo' => fake()->sentence(4),
            'name' => 'banner.png',
            'type' => 'png',
            'mime_type' => 'image/png',
            'size' => 1024,
            'path' => 'banners/'.Str::uuid().'.png',
        ];
    }
}
