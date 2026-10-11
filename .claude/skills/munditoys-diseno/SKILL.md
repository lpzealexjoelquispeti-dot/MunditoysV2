---
name: munditoys-diseno
description: "Identidad visual de MundiToys. Actívala siempre que diseñes o rediseñes vistas de la tienda (welcome, catálogo, detalle de producto, carrito, perfil), el hero, banners, secciones de marketing, el logo o elementos 3D con model-viewer. También cuando el usuario diga que algo 'parece hecho por IA', 'genérico' o 'plantilla'. No aplica al panel administrativo salvo que se pida."
---

# Diseño MundiToys

MundiToys es una juguetería boliviana con sucursales físicas, retiro express con QR, modelos 3D escaneados por fotogrametría y un asistente llamado **Mundi**. Cada decisión visual debe sentirse de *esta* tienda, no de una plantilla.

## Paleta (ya definida en `resources/css/app.css`, no inventar colores nuevos)

| Token Tailwind | Hex | Uso |
|---|---|---|
| `brand-orange` | `#f58220` | Color principal: CTA, acentos, palabra destacada, estrella del logo |
| `brand-orange-soft` | `#fff0df` | Fondos suaves de tarjetas y etiquetas |
| `brand-blue` | `#005ca8` | Marca "Mundi", navegación activa, botones secundarios, enlaces |
| `brand-blue-dark` | `#00467f` | Hover de azul, textos sobre fondos claros |
| `brand-red` | `#e30613` | Letras del logo, ofertas, urgencia y marcadores del mapa. No usarlo en botones ni fondos grandes |
| `brand-ink` | `#183247` | Texto principal (no usar negro puro) |
| `brand-surface` | `#fffaf6` | Fondo de página |

- Proporción aproximada: 60 % surface/blanco, 25 % naranja, 10 % azul, 5 % rojo.
- Naranja y azul nunca como degradado entre sí. Degradados solo dentro del mismo tono.
- El verde del banner de retiro es la única excepción existente; no añadir más colores de sección.
- Utilidad disponible: `toy-dot-grid` (patrón de puntos) para fondos de color.

## Tipografía

- Una sola familia en todo el sistema: **Nunito** (se carga con `bunny('Nunito')` en `vite.config.js`, pesos 400–700). Nunca Inter.
- Grosores: títulos `font-bold`, etiquetas y botones `font-semibold`, texto normal sin clase. **Prohibido `font-black` y `font-extrabold`.**
- Nada de etiquetas en MAYÚSCULAS con `uppercase` + `tracking-wider` ("eyebrows" sobre los títulos).
- Máximo dos tamaños de titular por sección. Nada de titulares de más de 2 líneas en escritorio.

## Íconos

- Lucide vía `mallardduck/blade-lucide-icons`: `<x-lucide-truck />`. Nunca emojis en las vistas web.
- La clase `icono` (en `app.css`) los hace medir 1.1em: toman el tamaño del texto; se ajustan con `text-*` o `size-*`.
- Íconos dinámicos: `<x-dynamic-component :component="'lucide-'.$icono" />`.

## Logo

- Logo oficial: `public/images/marca/logo-munditoys.png` (500×500, fondo transparente). Círculo `brand-blue` con "MUNDI TOYS" en letras rojas (`brand-red`) con borde blanco, un auto rojo dentro de la "O" y la cinta "CONSTRUYENDO SUEÑOS".
- Usarlo siempre con `asset('images/marca/logo-munditoys.png')`; nunca reconstruirlo con texto o emojis.
- El lema real de la marca es "Construyendo sueños": úsalo en vez de frases inventadas.
- Sobre fondo `brand-blue` el logo funciona como "sticker" (su borde blanco lo separa). En el hero de `welcome.blade.php` se inclina hacia el cursor con perspectiva CSS; si más adelante existe `logo-munditoys.glb`, reemplazarlo por `<model-viewer>`.

## Anti-plantilla (lo que hace que una página "parezca IA")

Evitar:
- Hero centrado con píldora arriba + titular gigante con una palabra en color + buscador + chips debajo.
- Emojis como iconos (🧸 📦 🎉 👤). Usar fotos reales de productos, SVG propios o el isotipo.
- Frases genéricas: "el juguete perfecto", "para cada sonrisa", "experiencia única", "descubre la magia".
- Tarjetas idénticas en rejilla de 3 con icono + título + párrafo.
- Degradados morados/azules, glassmorphism, brillos y sombras difusas grandes por todos lados.

Preferir:
- Composición asimétrica (texto a un lado, producto/3D al otro, elementos que se salen del contenedor).
- Datos concretos y locales: nombres de sucursales, ciudades de Bolivia, precios reales en Bs desde la base de datos, stock real.
- Productos reales del catálogo (`Producto`) en vez de ilustraciones de relleno.
- Lo que solo MundiToys tiene: modelos 3D escaneados ("lo escaneamos nosotros"), retiro con QR, Mundi el asistente.
- Detalles de juguetería: bordes gruesos, sombras duras desplazadas (`shadow-[4px_4px_0_...]`), formas de bloque, pegatinas ligeramente rotadas. Con moderación.

## 3D con model-viewer

- `@google/model-viewer` ya está instalado; `resources/js/app.js` lo importa bajo demanda cuando la página tiene un `<model-viewer>`. No añadir three.js ni Spline.
- Modelos `.glb` de menos de 1 MB para el hero, con `poster` mientras carga y `loading="lazy"` fuera del primer pantallazo.
- Efecto "sigue al mouse": actualizar `orientation` con interpolación suave (`requestAnimationFrame`, factor ~0.08), `disable-zoom`, `interaction-prompt="none"`.
- En táctil usar `auto-rotate`. Respetar `prefers-reduced-motion` (sin movimiento).

## Textos (menos es más)

- La interfaz se explica sola: **sin subtítulos que repitan el título**, sin párrafos de ayuda obvios, sin "Ej.:" en los placeholders.
- Títulos de 1 a 3 palabras ("Mis pedidos", "Tu carrito", "Por zona"). Botones con verbo y máximo 3 palabras ("Ver QR", "Continuar al pago", "Cobrar Bs. 120.00").
- Estados vacíos: ícono + una línea ("Sin resultados") + un botón. Nada de disculpas ni explicaciones.
- Mayúsculas del español (solo la primera palabra y nombres propios): "Mis direcciones", no "Mis Direcciones".
- Evitar metadatos unidos con "·", flechas → pegadas a botones y signos de exclamación en textos del sistema.
- La información técnica larga (guías, metodología) va plegada en `<details>`, no visible por defecto.
- Español de Bolivia, tuteo. Moneda siempre "Bs." con dos decimales.
- Un CTA principal por sección.
