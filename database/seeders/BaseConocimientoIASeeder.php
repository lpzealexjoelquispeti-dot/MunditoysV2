<?php

namespace Database\Seeders;

use App\Models\BaseConocimientoIA;
use Illuminate\Database\Seeder;

class BaseConocimientoIASeeder extends Seeder
{
    /**
     * Información de la tienda que el asistente consulta (RAG). Describe lo que el sistema ya hace;
     * las entradas de categoría 'por_confirmar' deben revisarse con MundiToys antes de publicarse.
     */
    public function run(): void
    {
        $entradas = [
            ['Envíos a domicilio (delivery)', 'envios', 'Hacemos entregas a domicilio dentro del municipio de La Paz, Bolivia. El Alto todavía no está incluido. El costo del envío se calcula automáticamente en el checkout según la distancia en kilómetros desde la sucursal que tiene el stock hasta tu dirección, con un monto mínimo por envío; se muestra en bolivianos antes de pagar. Puedes guardar hasta 3 direcciones de entrega en tu perfil.'],
            ['Recojo en sucursal', 'envios', 'También puedes elegir recoger tu pedido en la sucursal, sin costo de envío. Cuando el pedido esté listo, en la sección Seguimiento y Registro encontrarás un código QR del pedido: muéstralo en la sucursal y el personal lo escanea para entregarte tus juguetes.'],
            ['Código QR de entrega', 'envios', 'Cada pedido tiene un código QR que solo ve el cliente en Seguimiento y Registro. Sirve para confirmar en la sucursal que el pedido se entrega a la persona correcta. No compartas ese código con otras personas.'],
            ['Formas de pago', 'pagos', 'Puedes pagar con transferencia por código QR o con tarjeta de débito o crédito. Al confirmar el pago recibes tu comprobante y puedes descargar la factura en PDF desde Seguimiento y Registro.'],
            ['Seguimiento de pedidos', 'pedidos', 'En Seguimiento y Registro (menú de tu perfil) ves el estado de cada compra: pagado, pendiente de entrega, en camino, entregado o cancelado, junto con tus notificaciones, el QR del pedido y la factura. Para consultar pedidos con el asistente debes iniciar sesión.'],
            ['Cuenta y registro', 'cuenta', 'Para comprar necesitas una cuenta. Regístrate con tu correo, activa la cuenta y después podrás guardar direcciones, pagar, seguir tus pedidos y recibir notificaciones.'],
            ['Modelos 3D de los juguetes', 'catalogo', 'Algunos juguetes tienen un modelo 3D en su página: puedes girarlo y acercarlo para ver el juguete desde todos los ángulos antes de comprar. En el catálogo puedes filtrar solo los productos con vista 3D.'],
            ['Cambios, devoluciones y reclamos', 'por_confirmar', 'Si tu juguete llegó dañado, incompleto o no es el que pediste, comunícate con la sucursal de MundiToys indicando tu número de pedido para que revisen tu caso. Los plazos y condiciones de cambio los confirma la tienda.'],
        ];

        foreach ($entradas as [$titulo, $categoria, $contenido]) {
            BaseConocimientoIA::query()->updateOrCreate(['titulo' => $titulo], ['categoria' => $categoria, 'contenido' => $contenido, 'estado' => true]);
        }
    }
}
