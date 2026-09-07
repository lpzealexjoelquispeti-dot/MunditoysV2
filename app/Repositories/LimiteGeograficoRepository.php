<?php

namespace App\Repositories;

use Illuminate\Support\Facades\File;

class LimiteGeograficoRepository
{
    private ?array $limite = null;

    /** @return array<string, mixed> */
    public function laPaz(): array
    {
        return $this->limite ??= File::json(resource_path('geo/la-paz.json'));
    }
}
