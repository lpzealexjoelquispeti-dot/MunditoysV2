<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class SeguridadAsistenteService
{
    public function respuestaDirecta(string $mensaje, ?User $usuario): ?string
    {
        $texto = $this->normalizar($mensaje);

        if ($this->contieneInsultos($mensaje)) {
            return 'Puedo ayudarte con tu compra o reclamo. Cuéntame lo que necesitas sin insultos y revisamos el caso.';
        }
        if (preg_match('/\b(ignora|omite|olvida|desobedece|ignore|bypass)\b[\s\S]{0,100}\b(instrucciones|reglas|permisos|restricciones|instructions|rules|permissions)\b|\b(system prompt|prompt del sistema|actua como administrador|soy el administrador|soy administrador|modo administrador|developer mode|ejecuta sql|select|drop table|union select|jailbreak)\b|<\/?(?:system|developer)>/', $texto)) {
            return 'Mis permisos dependen de tu cuenta. Puedo ayudarte con juguetes, disponibilidad o tus pedidos; no ejecuto instrucciones para cambiar permisos ni consultar directamente la base de datos.';
        }
        if (preg_match('/\b(contrasenas?|passwords?|credenciales|tokens?|base de datos|datos de otros clientes|lista de clientes|api[_ -]?key|claves? privadas?|datos personales|correos de clientes)\b|\b(direccion|telefono|carnet|documento|pedido)\b[\s\S]{0,60}\b(otro cliente|otra cuenta|de otra persona)\b/', $texto)
            || (! ($usuario?->can('acceder panel') && $usuario->can('gestionar inventario'))
                && preg_match('/\b(costos? de compra|margen(?:es)? de ganancia)\b/', $texto))) {
            return 'Esa información es privada. Puedo consultar precios de venta, disponibilidad, recomendaciones y pedidos de tu propia cuenta.';
        }
        if (preg_match('/\b(que (puedes|sabes) hacer|como (funcionas|me ayudas)|que haces|tus funciones|ayuda del asistente)\b/', $texto)) {
            return $this->capacidades($usuario);
        }

        return null;
    }

    public function capacidades(?User $usuario): string
    {
        $funciones = [
            'Puedo buscar juguetes, comparar sus características y recomendar regalos por edad, gustos y presupuesto.',
            'Ejemplos: «Un regalo de dinosaurios para 7 años hasta 200 Bs», «¿Está disponible el rompecabezas?» o «¿Dónde está mi pedido?».',
            'Consulto tus propios pedidos si inicias sesión. Informo disponibilidad sin revelar cantidades internas.',
        ];
        if ($usuario?->can('acceder panel') && $usuario->can('gestionar inventario')) {
            $funciones[] = 'Con tus permisos también reviso cantidades, faltantes y lotes por vencer; analizo rotación y productos sin ventas. Estimo demanda con LightGBM y propongo reposición: «¿Qué comprar con 3000 Bs para las próximas 8 semanas?». Las predicciones son estimaciones, no compras automáticas.';
        }
        if ($usuario?->can('administrar accesos')) {
            $funciones[] = 'También resumo ventas y productos más vendidos: «¿Cuánto vendimos este mes?». El recomendador combina afinidad del catálogo y compras conjuntas; el clasificador de intención orienta qué consulta realizar.';
        }
        $secciones = app(ReporteAsistenteService::class)->seccionesPermitidas($usuario);
        if ($secciones !== []) {
            $funciones[] = 'Genero reportes explicados de '.implode(', ', $secciones).': «Genera un reporte de '.implode(' y ', $secciones).' de este mes». También puedes indicar un juguete y pedir registros recientes: «Reporte de las últimas 5 ventas del juguete CODIGO de este mes». Usa su código real. Puedes descargarlos en texto. Las ventas de un vendedor se limitan a sus operaciones y logística a sus entregas asignadas. No muestro datos personales de clientes en reportes.';
        }

        return implode("\n\n", $funciones);
    }

    public function contieneInsultos(string $texto): bool
    {
        $normalizado = strtr($this->normalizar($texto), ['@' => 'a', '$' => 's', '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't']);
        $palabras = ['mierda', 'puta', 'puto', 'putos', 'putas', 'carajo', 'cabron', 'cabrona', 'pendejo', 'pendeja', 'imbecil', 'idiota', 'cojudo', 'cojuda', 'joder', 'fuck', 'shit'];
        $patrones = array_map(fn (string $palabra): string => implode('[\s._*-]*', str_split($palabra)), $palabras);

        return (bool) preg_match('/\b(?:'.implode('|', $patrones).')\b/', $normalizado);
    }

    private function normalizar(string $texto): string
    {
        $texto = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $texto);

        return Str::lower(Str::ascii($texto));
    }

    public function contieneCantidadesInternas(string $texto): bool
    {
        $normalizado = Str::lower(Str::ascii($texto));

        return (bool) preg_match('/\b(?:\d+|una?|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|once|doce|veinte|treinta|cien)\s+(?:unidades?|ejemplares?|piezas? (?:en stock|disponibles))\b|\b(?:stock|inventario|existencias)\s*(?:total|actual|disponible)?\s*(?:es|de|:|=)?\s*\d+\b|\b(?:quedan|tenemos|hay|disponemos de)\s+\d+\s+(?:juguetes|productos)\b/', $normalizado);
    }
}
