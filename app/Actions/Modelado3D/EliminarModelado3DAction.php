<?php

namespace App\Actions\Modelado3D;

use App\Models\Modelado3D;

class EliminarModelado3DAction
{
    public function ejecutar(Modelado3D $modelado): bool
    {
        return (bool) $modelado->delete();
    }
}
