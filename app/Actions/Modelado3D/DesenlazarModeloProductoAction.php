<?php

namespace App\Actions\Modelado3D;

use App\Models\Modelado3D;

class DesenlazarModeloProductoAction
{
    public function ejecutar(Modelado3D $modelado): Modelado3D
    {
        $modelado->update(['producto_id' => null]);

        return $modelado->fresh();
    }
}
