<x-admin-shell active="modelos-3d">
    @php
        $analisisPendiente = $sesion && in_array($sesion->status, ['en_cola', 'analizando'], true);
        $analisisInterrumpido = $analisisPendiente && $sesion->updated_at->lte(now()->subSeconds(config('fotogrametria.timeout') + 90));
        $analizando = $analisisPendiente && ! $analisisInterrumpido;
        $procesadorSinResponder = $analizando && $sesion->status === 'en_cola' && $sesion->updated_at->lte(now()->subSeconds(20));
        $estadosFoto = ['pendiente' => 'Pendiente', 'compatible' => 'Compatible', 'review' => 'Revisar', 'rejected' => 'Reemplazar'];
        $estadosSesion = ['borrador' => 'En preparación', 'en_cola' => 'En cola', 'analizando' => 'Analizando', 'revision' => 'Requiere revisión', 'validado' => 'Validación completa', 'error' => 'Análisis interrumpido'];
    @endphp

    <div class="mx-auto max-w-[1700px] space-y-6" data-modelado-3d
        x-data="mundiToysFotogrametriaUpload($wire)"
        data-disponibles="{{ max(0, 48 - $fotografias->count()) }}"
        x-on:fotogrametria-sesion-abierta.window="errores = []; total = 0; procesadas = 0; guardadas = 0">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>

                <h1 class="text-3xl font-semibold text-brand-ink sm:text-4xl">Fotogrametría local</h1>
                <p class="mt-1 text-sm text-slate-500">48 fotos del mismo juguete</p>
            </div>
            <span class="inline-flex shrink-0 items-center gap-2 self-start rounded-full border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700"><span class="size-2 rounded-full bg-emerald-500"></span> Validación local · Sin API externa</span>
        </header>

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                <p class="font-semibold">Revisa estos datos</p>
                <ul class="mt-1 list-inside list-disc">
                    @foreach ($errors->all() as $mensaje)
                        <li wire:key="error-fotogrametria-{{ $loop->index }}">{{ $mensaje }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid items-start gap-6 xl:grid-cols-[300px_minmax(0,1fr)]">
            <aside class="space-y-5">
                <section class="rounded-3xl border border-orange-100 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-semibold text-brand-ink">Nueva colección</h2>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500">Una sesión por juguete</p>
                    <form wire:submit="crearSesion" class="mt-4 space-y-3">
                        <fieldset x-bind:disabled="subiendo" class="space-y-3 disabled:opacity-60">
                            <label for="buscar-juguete-fotogrametria" class="block text-xs font-semibold text-slate-700">Juguete del catálogo</label>
                            @if ($productoSeleccionado)
                                <div class="rounded-xl border border-blue-100 bg-blue-50 p-3">
                                    <p class="text-sm font-semibold text-brand-ink">{{ $productoSeleccionado['nombre'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $productoSeleccionado['codigo'] }}</p>
                                    <button type="button" wire:click="quitarProductoSeleccionado" class="mt-2 text-xs font-semibold text-brand-blue hover:underline">Cambiar juguete</button>
                                </div>
                            @else
                                <input id="buscar-juguete-fotogrametria" type="search" wire:model.live.debounce.350ms="buscarProducto" placeholder="Nombre o código…" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm focus:border-brand-blue focus:ring-brand-blue">
                                @if (count($productosSugeridos))
                                    <div class="max-h-64 space-y-1 overflow-y-auto rounded-xl border border-slate-200 p-1">
                                        @foreach ($productosSugeridos as $producto)
                                            <button type="button" wire:key="producto-foto-{{ $producto['id'] }}" wire:click="seleccionarProducto({{ $producto['id'] }})" class="flex w-full items-center gap-2 rounded-lg p-2 text-left hover:bg-blue-50">
                                                @if ($producto['imagen'])
                                                    <img src="{{ $producto['imagen'] }}" alt="" class="size-10 rounded-lg object-cover" loading="lazy">
                                                @endif
                                                <span class="min-w-0"><span class="block truncate text-xs font-semibold text-brand-ink">{{ $producto['nombre'] }}</span><span class="block text-xs text-slate-500">{{ $producto['codigo'] }}</span></span>
                                            </button>
                                        @endforeach
                                    </div>
                                @elseif (mb_strlen(trim($buscarProducto)) >= 2)
                                    <p class="text-xs text-slate-500">No se encontraron juguetes.</p>
                                @else
                                    <p class="text-xs text-slate-500">Escribe al menos 2 caracteres.</p>
                                @endif
                            @endif
                            <label for="modo-captura-nuevo" class="block text-xs font-semibold text-slate-700">¿Cómo tomarás las fotos?</label>
                            <select id="modo-captura-nuevo" wire:model="modoCaptura" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm">
                                @foreach ($modosCaptura as $valor => $nombre)
                                    <option wire:key="modo-nuevo-{{ $valor }}" value="{{ $valor }}">{{ $nombre }}</option>
                                @endforeach
                            </select>
                            <button type="submit" wire:loading.attr="disabled" @disabled(! $productoSeleccionado) class="min-h-11 w-full rounded-xl bg-brand-blue px-4 py-2 text-sm font-semibold text-white hover:bg-brand-blue-dark disabled:cursor-not-allowed disabled:opacity-40">Crear sesión de 48 fotos</button>
                        </fieldset>
                    </form>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold text-brand-ink">Tus sesiones</h2>
                    <div class="mt-3 max-h-96 space-y-2 overflow-y-auto">
                        @forelse ($sesiones as $captura)
                            <button type="button" wire:key="sesion-foto-{{ $captura->id }}" wire:click="abrirSesion({{ $captura->id }})" x-bind:disabled="subiendo" @class(['w-full rounded-xl border p-3 text-left transition disabled:opacity-50', 'border-brand-blue bg-blue-50' => $sesionId === $captura->id, 'border-slate-100 hover:border-blue-200 hover:bg-slate-50' => $sesionId !== $captura->id])>
                                <span class="block truncate text-sm font-semibold text-brand-ink">{{ $captura->producto?->nombre ?? 'Producto no disponible' }}</span>
                                <span class="mt-1 block text-xs text-slate-500">#{{ $captura->id }} · {{ $estadosSesion[$captura->status] ?? $captura->status }}</span>
                            </button>
                        @empty
                            <p class="text-xs leading-relaxed text-slate-500">Las colecciones que crees aparecerán aquí.</p>
                        @endforelse
                    </div>

                    @if ($sesionesArchivadas->isNotEmpty())
                        <details class="mt-4 border-t border-slate-100 pt-3" wire:ignore.self>
                            <summary class="flex cursor-pointer items-center gap-1.5 text-xs font-semibold text-slate-500">
                                <x-lucide-archive /> Papelera ({{ $sesionesArchivadas->count() }})
                            </summary>
                            <ul class="mt-2 space-y-2">
                                @foreach ($sesionesArchivadas as $archivada)
                                    <li wire:key="sesion-archivada-{{ $archivada->id }}" class="flex items-center gap-2 rounded-xl bg-slate-50 p-2.5">
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-slate-600">{{ $archivada->producto?->nombre ?? 'Producto no disponible' }}</p>
                                            <p class="text-xs text-slate-400">#{{ $archivada->id }}, {{ $archivada->fotografias_count }} fotos</p>
                                        </div>
                                        <button type="button" x-on:click="confirmarSesion3D($wire, 'restaurarSesion', {{ $archivada->id }})" class="grid size-8 place-items-center rounded-lg text-brand-blue transition hover:bg-blue-50" aria-label="Restaurar sesión #{{ $archivada->id }}" title="Restaurar">
                                            <x-lucide-rotate-ccw />
                                        </button>
                                        <button type="button" x-on:click="confirmarSesion3D($wire, 'eliminarSesion', {{ $archivada->id }}, {{ $archivada->fotografias_count }})" class="grid size-8 place-items-center rounded-lg text-red-600 transition hover:bg-red-50" aria-label="Eliminar sesión #{{ $archivada->id }} definitivamente" title="Eliminar definitivamente">
                                            <x-lucide-trash-2 />
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </section>

                <details class="rounded-2xl border border-orange-100 bg-orange-50/60 p-4 text-xs text-slate-600">
                    <summary class="cursor-pointer font-semibold text-brand-ink">Guía para tomar las 48 fotos</summary>
                    <ol class="mt-3 list-inside list-decimal space-y-2 leading-relaxed">
                        <li>Toma 16 fotos a media altura, 16 desde arriba y 16 desde un ángulo bajo, en ese orden.</li>
                        <li><strong>Girando el juguete (cámara fija):</strong> empieza de frente y gíralo unos 22,5° por toma, de modo que su lado derecho vaya quedando frente a la cámara. Cambia la altura de la cámara en cada vuelta. Elige este modo al crear la sesión.</li>
                        <li><strong>Moviéndote alrededor (juguete quieto):</strong> empieza de frente y rodéalo hacia su lado derecho, unos 22,5° por toma.</li>
                        <li>Sube los archivos con los nombres de la cámara (IMG_0001, IMG_0002…): se ordenan por nombre antes de guardarse.</li>
                        <li><strong>No las pases por WhatsApp como foto:</strong> las comprime y cambia sus nombres y orden. Usa cable USB, Google Drive/Fotos en calidad original o WhatsApp «como documento».</li>
                        <li>Conserva distancia, zoom e iluminación en toda la captura. Con el juguete girando, usa luz suave: los brillos cambian de lugar.</li>
                        <li>Las vistas consecutivas deben compartir detalles. Evita desenfoque, reflejos fuertes y zonas quemadas.</li>
                        <li>Encuadra el juguete completo. Un fondo uniforme ayuda a evitar coincidencias ajenas al objeto.</li>
                    </ol>
                    <p class="mt-3">48 es nuestro punto de partida experimental. La cobertura y la textura también determinan la reconstrucción.</p>
                </details>
            </aside>

            <main class="min-w-0 space-y-5">
                @if ($sesion)
                    <section wire:key="coleccion-foto-{{ $sesion->id }}" class="space-y-5" @if ($analizando) wire:poll.5s @endif>
                        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold text-slate-500">Colección #{{ $sesion->id }}</p>
                                    <h2 class="mt-1 text-xl font-semibold text-brand-ink">{{ $sesion->producto?->nombre ?? 'Producto no disponible' }}</h2>
                                    <p class="mt-1 text-xs text-slate-500">{{ $estadosSesion[$sesion->status] ?? $sesion->status }} · Revisión {{ $sesion->revision }}</p>
                                    @can('delete', $sesion)
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            <button type="button" x-on:click="confirmarSesion3D($wire, 'archivarSesion', {{ $sesion->id }}, {{ $fotografias->count() }})" wire:loading.attr="disabled" @disabled($analizando || ($reconstruccion?->esta_activa ?? false)) x-bind:disabled="subiendo" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40">
                                                <x-lucide-archive /> Papelera
                                            </button>
                                            <button type="button" x-on:click="confirmarSesion3D($wire, 'eliminarSesion', {{ $sesion->id }}, {{ $fotografias->count() }})" wire:loading.attr="disabled" @disabled($analizando || ($reconstruccion?->esta_activa ?? false)) x-bind:disabled="subiendo" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40">
                                                <x-lucide-trash-2 /> Eliminar
                                            </button>
                                        </div>
                                    @endcan
                                </div>
                                <button type="button" wire:click="analizarColeccion" wire:loading.attr="disabled" x-bind:disabled="subiendo || {{ $analizando || $fotografias->count() < 3 || ! $sesion->referencia_foto_id ? 'true' : 'false' }}" class="min-h-11 rounded-xl bg-brand-blue px-5 py-2 text-sm font-semibold text-white hover:bg-brand-blue-dark disabled:cursor-not-allowed disabled:opacity-40">{{ $analizando ? 'Análisis en curso…' : 'Validar colección' }}</button>
                            </div>

                            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <div class="rounded-2xl bg-slate-50 p-3"><p class="text-xs text-slate-500">Fotografías</p><p class="mt-1 text-2xl font-semibold text-brand-ink">{{ $resumen['total'] }}<span class="text-sm font-normal text-slate-400"> / 48</span></p></div>
                                <div class="rounded-2xl bg-emerald-50 p-3"><p class="text-xs text-emerald-700">Compatibles</p><p class="mt-1 text-2xl font-semibold text-emerald-700">{{ $resumen['compatibles'] }}</p></div>
                                <div class="rounded-2xl bg-amber-50 p-3"><p class="text-xs text-amber-700">Para revisar</p><p class="mt-1 text-2xl font-semibold text-amber-700">{{ $resumen['revision'] }}</p></div>
                                <div class="rounded-2xl bg-red-50 p-3"><p class="text-xs text-red-700">Para reemplazar</p><p class="mt-1 text-2xl font-semibold text-red-700">{{ $resumen['rechazadas'] }}</p></div>
                            </div>

                            @if ($analizando)
                                <div class="mt-4" role="status" aria-live="polite">
                                    <p class="text-xs font-semibold text-brand-blue">{{ $sesion->status === 'en_cola' ? 'Esperando al procesador local' : 'Comprobando calidad y correspondencias' }}</p>
                                    <progress aria-label="Análisis en curso" class="mt-2 h-2 w-full accent-brand-blue"></progress>
                                    @if ($procesadorSinResponder)
                                        <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800" role="alert">
                                            <p class="font-semibold">El procesador local todavía no tomó esta validación</p>
                                            <p class="mt-1 leading-relaxed">Inicia el proyecto con <code class="rounded bg-white px-1 font-mono">composer run dev</code>, o ejecuta en otra terminal <code class="rounded bg-white px-1 font-mono">php artisan queue:work fotogrametria --queue=fotogrametria --timeout=330</code>. La validación comenzará sola al iniciarlo.</p>
                                        </div>
                                    @endif
                                </div>
                            @elseif ($analisisInterrumpido)
                                <p class="mt-4 rounded-xl bg-amber-50 p-3 text-xs text-amber-800" role="alert">El análisis tardó demasiado. Tus fotos están guardadas: vuelve a validar.</p>
                            @elseif ($resumen['listo'])
                                <p class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">Colección validada</p>
                            @elseif ($sesion->revision_analizada !== null && $sesion->revision_analizada !== $sesion->revision)
                                <p class="mt-4 rounded-xl bg-amber-50 p-3 text-xs text-amber-800">La colección cambió. Vuelve a validarla.</p>
                            @elseif (! $sesion->referencia_foto_id || $fotografias->count() < 3)
                                <p class="mt-4 text-xs text-slate-500">Sube al menos 3 fotos para validar.</p>
                            @elseif ($resumen['pendientes'] > 0)
                                <p class="mt-4 text-xs text-slate-500">{{ $resumen['pendientes'] }} fotografías pendientes de análisis. Pulsa «Validar colección» cuando termines de subir o reemplazar tus tomas.</p>
                            @endif

                            @if ($sesion->error_message)
                                <p class="mt-3 rounded-xl bg-red-50 p-3 text-xs text-red-700" role="alert">{{ $sesion->error_message }}</p>
                            @endif

                            <fieldset @disabled($analizando) x-bind:disabled="subiendo || {{ $analizando ? 'true' : 'false' }}" class="mt-5 rounded-2xl border-2 border-dashed border-blue-200 bg-blue-50/40 p-4 disabled:opacity-50">
                                <label for="fotografias-{{ $sesion->id }}" class="block text-sm font-semibold text-brand-ink">{{ $fotografias->count() >= 48 ? 'La colección tiene sus 48 posiciones ocupadas' : 'Añadir fotografías desde tu laptop' }}</label>
                                <p class="mt-1 text-xs text-slate-500">JPG, PNG o WEBP · Hasta 10 MB y 20 megapíxeles por imagen · Se guardan de una en una.</p>
                                <input id="fotografias-{{ $sesion->id }}" type="file" accept="image/jpeg,image/png,image/webp" multiple x-on:change="subir($event.target)" @disabled($fotografias->count() >= 48) class="mt-3 block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-blue file:px-4 file:py-2.5 file:font-semibold file:text-white disabled:opacity-50">
                                @if ($fotografias->count() >= 48)
                                @endif
                            </fieldset>

                            <div x-cloak x-show="subiendo || total > 0" class="mt-3 rounded-xl border border-slate-200 p-3 text-xs" aria-live="polite">
                                <div class="flex flex-wrap justify-between gap-2"><p class="font-semibold text-brand-ink"><span x-text="guardadas"></span> guardadas · <span x-text="procesadas"></span> de <span x-text="total"></span> archivos procesados</p><button type="button" x-show="subiendo" x-on:click="detener()" x-bind:disabled="detenerSolicitado" class="font-semibold text-brand-red disabled:opacity-50" x-text="detenerSolicitado ? 'Deteniendo…' : 'Detener después de esta foto'"></button></div>
                                <p x-show="subiendo" class="mt-2 truncate text-slate-500" x-text="nombreActual"></p>
                                <progress x-show="subiendo" x-bind:value="progresoArchivo" max="100" class="mt-2 h-2 w-full accent-brand-blue"></progress>
                            </div>
                            <div x-cloak x-show="errores.length > 0" class="mt-3 rounded-xl border border-red-200 bg-red-50 p-3" role="alert">
                                <p class="text-xs font-semibold text-red-800">Estos archivos necesitan atención</p>
                                <template x-for="(error, indice) in errores" x-bind:key="indice"><p class="mt-2 break-words text-xs text-red-700"><strong x-text="error.nombre"></strong><span>: </span><span x-text="error.mensaje"></span></p></template>
                            </div>
                        </div>

                        @if ($fotosPorCorregir->isNotEmpty())
                            <details class="group rounded-3xl border border-red-200 bg-white p-5 shadow-sm sm:p-6" wire:ignore.self>
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                                    <span>
                                        <span class="block text-base font-semibold text-brand-ink">Fotografías que necesitan otra toma</span>
                                        <span class="text-xs text-slate-500">{{ $fotosPorCorregir->where('estado', 'rejected')->count() }} por reemplazar, {{ $fotosPorCorregir->where('estado', 'review')->count() }} por revisar</span>
                                    </span>
                                    <x-lucide-chevron-down class="text-xl text-slate-400 transition group-open:rotate-180" />
                                </summary>
                                <p class="mt-3 text-xs text-slate-500">Conserva su posición. Vuelve a validar al terminar.</p>
                                @php $revisarSinConfirmar = $fotosPorCorregir->where('estado', 'review')->whereNull('etiqueta_humana')->count(); @endphp
                                @if ($revisarSinConfirmar > 0 && $sesion->revision_analizada === $sesion->revision)
                                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3">
                                        <p class="max-w-2xl text-xs leading-relaxed text-amber-800">«Revisar» no significa que la foto sea mala: en juguetes lisos o brillantes el validador puede no encontrar suficientes detalles. Si revisaste las fotos y todas muestran este mismo juguete, confírmalas. Las marcadas «Reemplazar» siguen necesitando otra toma.</p>
                                        <button type="button" wire:click="confirmarFotosRevisadas" wire:confirm="¿Revisaste visualmente estas {{ $revisarSinConfirmar }} fotografías y todas muestran este mismo juguete?" wire:loading.attr="disabled" @disabled($analizando) class="min-h-10 shrink-0 rounded-xl bg-amber-600 px-4 py-2 text-xs font-semibold text-white hover:bg-amber-700 disabled:opacity-50">Confirmar {{ $revisarSinConfirmar }} como aptas</button>
                                    </div>
                                @endif
                                <div class="mt-4 grid gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                                    @foreach ($fotosPorCorregir as $fotografia)
                                        <article wire:key="foto-por-corregir-{{ $fotografia->id }}" @class(['flex gap-3 rounded-2xl border p-3', 'border-red-200 bg-red-50/50' => $fotografia->estado === 'rejected', 'border-amber-200 bg-amber-50/50' => $fotografia->estado === 'review'])>
                                            <button type="button" x-on:click="$dispatch('ver-foto', { url: @js(route('admin.fotogrametria.foto', ['modelado' => $sesion->id, 'foto' => $fotografia->id, 'original' => 1])), titulo: @js('#'.str_pad((string) $fotografia->ranura, 2, '0', STR_PAD_LEFT).' · '.$fotografia->nombre_original) })" class="shrink-0 rounded-xl transition hover:opacity-80" aria-label="Ampliar fotografía {{ $fotografia->ranura }}">
                                                <img src="{{ $fotografia->url }}" alt="{{ $fotografia->nombre_original }}" class="size-20 rounded-xl bg-white object-contain" loading="lazy">
                                            </button>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-xs font-semibold text-brand-ink">#{{ str_pad((string) $fotografia->ranura, 2, '0', STR_PAD_LEFT) }} · {{ $vistas[$fotografia->vista] ?? $fotografia->vista }} · <span @class(['text-red-700' => $fotografia->estado === 'rejected', 'text-amber-700' => $fotografia->estado === 'review'])>{{ $estadosFoto[$fotografia->estado] }}</span></p>
                                                <p class="mt-1 line-clamp-3 text-[11px] leading-relaxed text-slate-600">{{ $fotografia->motivos[0] ?? '' }}</p>
                                                <label for="corregir-{{ $fotografia->id }}" class="mt-2 block text-[11px] font-semibold text-slate-600">Subir nueva toma<input id="corregir-{{ $fotografia->id }}" type="file" accept="image/jpeg,image/png,image/webp" @disabled($analizando) x-bind:disabled="subiendo" x-on:change="subir($event.target, {{ $fotografia->id }})" class="mt-1 block w-full text-[10px] text-slate-500 file:mr-1 file:rounded-lg file:border-0 file:bg-white file:px-2 file:py-1.5 file:text-xs file:font-semibold file:text-slate-700"></label>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            </details>
                        @endif

                        @php
                            $reconstruyendo = $reconstruccion?->esta_activa ?? false;
                            $reconstruccionInterrumpida = $reconstruccion && in_array($reconstruccion->estado, ['en_cola', 'procesando'], true) && ! $reconstruyendo;
                            $modeloGenerado = $reconstruccion?->estado === 'completado' ? $reconstruccion->modeloGenerado : null;
                            $datosModelo = $reconstruccion?->resultado ?? [];
                        @endphp
                        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="titulo-reconstruccion" @if ($reconstruyendo) wire:poll.5s @endif>
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 id="titulo-reconstruccion" class="text-base font-semibold text-brand-ink">Reconstrucción 3D local</h3>
                                    <p class="mt-1 max-w-2xl text-xs leading-relaxed text-slate-500">COLMAP calcula la posición de cada foto con la GPU y OpenMVS genera la malla texturizada. Resultado: un archivo GLB listo para el catálogo. Tarda alrededor de 5 minutos con 48 fotos.</p>
                                    <label class="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-600">Modo de captura
                                        <select wire:change="cambiarModoCaptura($event.target.value)" @disabled($analizando || ($reconstruccion?->esta_activa ?? false)) class="min-h-9 rounded-lg border border-slate-300 bg-white py-1 pl-2 pr-7 text-xs font-normal">
                                            @foreach ($modosCaptura as $valor => $nombre)
                                                <option wire:key="modo-sesion-{{ $valor }}" value="{{ $valor }}" @selected($sesion->modo_captura === $valor)>{{ $nombre }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    @if ($sesion->modo_captura === 'plato_giratorio')
                                        <p class="mt-1 text-[11px] text-slate-500">Las cámaras se ubican usando solo el juguete (máscaras de IA): el fondo quieto no se toma en cuenta.</p>
                                    @endif
                                    <p class="mt-2 text-xs font-semibold {{ $resumen['mascaras'] === $resumen['total'] && $resumen['total'] > 0 ? 'text-emerald-700' : 'text-slate-500' }}">Máscaras de IA (BiRefNet): {{ $resumen['mascaras'] }} / {{ $resumen['total'] }} listas{{ $resumen['mascaras'] < $resumen['total'] ? ' · se calculan en segundo plano; las que falten se generarán al reconstruir' : ' · el fondo se excluirá del modelo' }}</p>
                                </div>
                                @if (! $reconstruyendo)
                                    <button type="button" wire:click="reconstruirModelo" wire:loading.attr="disabled" @disabled(! $resumen['listo'] || $analizando) x-bind:disabled="subiendo" class="min-h-11 rounded-xl bg-brand-orange px-5 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40">{{ $modeloGenerado || $reconstruccion?->estado === 'error' ? 'Volver a generar modelo' : 'Generar modelo 3D (GLB)' }}</button>
                                @endif
                            </div>

                            @if (! $resumen['listo'] && ! $reconstruccion)
                                <p class="mt-4 rounded-xl bg-slate-50 p-3 text-xs text-slate-600">Disponible cuando la colección esté validada: 48 fotografías compatibles o confirmadas como aptas, con vista asignada.</p>
                            @endif

                            @if ($reconstruyendo)
                                <div class="mt-4" role="status" aria-live="polite">
                                    <div class="flex justify-between gap-3 text-xs font-semibold text-brand-blue">
                                        <span>{{ $reconstruccion->estado === 'en_cola' ? 'Esperando al procesador de reconstrucción' : ($etapasReconstruccion[$reconstruccion->etapa] ?? 'Procesando') }}</span>
                                        <span>{{ $reconstruccion->progreso }} %</span>
                                    </div>
                                    <progress value="{{ $reconstruccion->progreso }}" max="100" aria-label="Progreso de la reconstrucción" class="mt-2 h-2 w-full accent-brand-orange"></progress>
                                    <p class="mt-1 text-xs text-slate-500">Reconstruyendo en segundo plano</p>
                                    <button type="button" wire:click="cancelarReconstruccion" wire:confirm="¿Cancelar la reconstrucción en curso? Úsalo si se quedó detenida, por ejemplo tras reiniciar el equipo." class="mt-2 text-xs font-semibold text-red-600 hover:underline">Cancelar reconstrucción</button>
                                    @if ($reconstruccion->estado === 'en_cola' && $reconstruccion->updated_at->lte(now()->subSeconds(20)))
                                        <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800" role="alert">
                                            <p class="font-semibold">El procesador de reconstrucción todavía no tomó este trabajo</p>
                                            <p class="mt-1 leading-relaxed">Inicia el proyecto con <code class="rounded bg-white px-1 font-mono">composer run dev</code>, o ejecuta <code class="rounded bg-white px-1 font-mono">php artisan queue:work fotogrametria_reconstruccion --queue=reconstruccion --timeout=3700</code>.</p>
                                        </div>
                                    @endif
                                </div>
                            @elseif ($reconstruccionInterrumpida)
                                <p class="mt-4 rounded-xl bg-amber-50 p-3 text-xs text-amber-800" role="alert">La reconstrucción tardó demasiado. Vuelve a generar el modelo.</p>
                            @elseif ($reconstruccion?->estado === 'error')
                                <p class="mt-4 rounded-xl bg-red-50 p-3 text-xs text-red-700" role="alert">{{ $reconstruccion->mensaje_error }}</p>
                            @elseif ($modeloGenerado)
                                <div class="mt-4 grid gap-5 lg:grid-cols-[minmax(0,1fr)_280px]">
                                    <div class="overflow-hidden rounded-2xl bg-slate-950">
                                        <model-viewer wire:key="vista-previa-{{ $modeloGenerado->id }}" src="{{ $modeloGenerado->url_glb }}" alt="Modelo 3D reconstruido de {{ $sesion->producto?->nombre }}" camera-controls auto-rotate shadow-intensity="1" class="h-[380px] w-full"></model-viewer>
                                    </div>
                                    <div class="space-y-3">
                                        <dl class="grid grid-cols-2 gap-2 text-xs">
                                            <div class="rounded-xl bg-slate-50 p-3"><dt class="text-slate-500">Cámaras ubicadas</dt><dd class="mt-1 font-semibold text-brand-ink">{{ $datosModelo['registered_images'] ?? '—' }} / {{ $datosModelo['total_images'] ?? '—' }}</dd></div>
                                            <div class="rounded-xl bg-slate-50 p-3"><dt class="text-slate-500">Tiempo total</dt><dd class="mt-1 font-semibold text-brand-ink">{{ $reconstruccion->duracion_ms ? gmdate('i:s', intdiv($reconstruccion->duracion_ms, 1000)).' min' : '—' }}</dd></div>
                                            <div class="rounded-xl bg-slate-50 p-3"><dt class="text-slate-500">Triángulos</dt><dd class="mt-1 font-semibold text-brand-ink">{{ isset($datosModelo['faces']) ? number_format($datosModelo['faces']) : '—' }}</dd></div>
                                            <div class="rounded-xl bg-slate-50 p-3"><dt class="text-slate-500">Archivo GLB</dt><dd class="mt-1 font-semibold text-brand-ink">{{ isset($datosModelo['glb_bytes']) ? number_format($datosModelo['glb_bytes'] / 1048576, 1).' MB' : '—' }}</dd></div>
                                            <div class="rounded-xl bg-slate-50 p-3"><dt class="text-slate-500">Error de reproyección</dt><dd class="mt-1 font-semibold text-brand-ink">{{ isset($datosModelo['reprojection_error']) ? number_format($datosModelo['reprojection_error'], 2).' px' : '—' }}</dd></div>
                                            <div class="rounded-xl bg-slate-50 p-3"><dt class="text-slate-500">Aceleración</dt><dd class="mt-1 font-semibold text-brand-ink">{{ ($datosModelo['gpu_used'] ?? false) ? 'GPU (CUDA)' : 'CPU' }}</dd></div>
                                        </dl>
                                        @if (! empty($datosModelo['unregistered_ids']))
                                            <p class="rounded-xl bg-amber-50 p-3 text-xs text-amber-800">{{ count($datosModelo['unregistered_ids']) }} fotografías no se pudieron ubicar y no aportaron al modelo.</p>
                                        @endif
                                        @if ($reconstruccion->revision !== $sesion->revision)
                                            <p class="rounded-xl bg-amber-50 p-3 text-xs text-amber-800">La colección cambió. Valida y genera un modelo nuevo.</p>
                                        @endif
                                        <a href="{{ $modeloGenerado->url_glb }}" download class="flex min-h-11 items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-brand-blue hover:bg-slate-50">Descargar GLB</a>
                                        @if ($modeloGenerado->producto_id === $sesion->producto_id)
                                            <p class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-center text-xs font-semibold text-emerald-700">Publicado en el catálogo de este juguete</p>
                                        @else
                                            <button type="button" wire:click="publicarModelo" wire:loading.attr="disabled" class="min-h-11 w-full rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50">Publicar en el catálogo</button>
                                            <p class="text-[11px] leading-relaxed text-slate-500">Reemplaza el modelo 3D actual del juguete.</p>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </section>

                        <details class="group rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" wire:ignore.self>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                                <span>
                                    <span class="block text-base font-semibold text-brand-ink">Inspección de fotografías</span>
                                    <span class="text-xs text-slate-500">{{ $fotografias->count() }} de 48 fotos</span>
                                </span>
                                <x-lucide-chevron-down class="text-xl text-slate-400 transition group-open:rotate-180" />
                            </summary>
                            <div class="mt-4 flex flex-wrap items-end justify-end gap-3">
                                <div class="flex flex-wrap gap-2">
                                    <label class="text-xs text-slate-500">Estado<select wire:model.live="filtroFoto" class="mt-1 block min-h-10 rounded-lg border border-slate-300 bg-white py-1.5 pl-2 pr-7 text-xs text-slate-700"><option value="">Todos los estados</option>@foreach ($estadosFoto as $valor => $nombre)<option wire:key="filtro-estado-{{ $valor }}" value="{{ $valor }}">{{ $nombre }}</option>@endforeach</select></label>
                                    <label class="text-xs text-slate-500">Vista<select wire:model.live="filtroVista" class="mt-1 block min-h-10 rounded-lg border border-slate-300 bg-white py-1.5 pl-2 pr-7 text-xs text-slate-700"><option value="">Todas las vistas</option>@foreach ($vistas as $valor => $nombre)<option wire:key="filtro-vista-{{ $valor }}" value="{{ $valor }}">{{ $nombre }}</option>@endforeach</select></label>
                                    <label class="text-xs text-slate-500">Orden<select wire:model.live="ordenFotos" class="mt-1 block min-h-10 rounded-lg border border-slate-300 bg-white py-1.5 pl-2 pr-7 text-xs text-slate-700"><option value="ranura">Posición de captura</option><option value="similitud">Relaciones visuales</option></select></label>
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 p-3">
                                <p class="max-w-2xl text-xs leading-relaxed text-slate-600">Las vistas se asignan por la posición de captura: #01–16 a media altura (frente, derecha, trasera, izquierda), #17–32 superior y #33–48 inferior. Solo completa las fotos sin clasificar; puedes corregir cada una a mano. «Revisar» puede indicar poco solapamiento; no demuestra que sea otro juguete.</p>
                                <button type="button" wire:click="sugerirVistas" wire:loading.attr="disabled" @disabled($analizando || ($resumen['vistas']['sin_clasificar'] ?? 0) === 0) x-bind:disabled="subiendo" class="min-h-10 shrink-0 rounded-xl border border-blue-200 bg-white px-4 py-2 text-xs font-semibold text-brand-blue hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-40">Asignar vistas según protocolo</button>
                            </div>
                            @if (! empty($resumen['saltos']))
                                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800" role="status">
                                    <p class="font-semibold">Posibles saltos en la secuencia de captura</p>
                                    <p class="mt-1 leading-relaxed">Estas tomas consecutivas no comparten correspondencias verificadas. Puede faltar una toma intermedia o estar fuera de orden:
                                        @foreach ($resumen['saltos'] as $salto)<span wire:key="salto-{{ $salto['desde'] }}-{{ $salto['hasta'] }}" class="ml-1 inline-block rounded bg-white px-1.5 py-0.5 font-semibold">#{{ str_pad((string) $salto['desde'], 2, '0', STR_PAD_LEFT) }} → #{{ str_pad((string) $salto['hasta'], 2, '0', STR_PAD_LEFT) }}</span>@endforeach
                                    </p>
                                </div>
                            @endif
                            <p class="mt-2 text-xs text-slate-500">Para completar esta fase: 48 fotografías compatibles (o «Revisar» confirmadas como aptas), con vista asignada y sin etiquetas «No apta». Vistas por asignar: <strong>{{ $resumen['vistas']['sin_clasificar'] ?? 0 }}</strong>.</p>

                            <div class="mt-4 grid max-h-[900px] grid-cols-1 gap-4 overflow-y-auto overscroll-contain pr-1 sm:grid-cols-2 2xl:grid-cols-3">
                                @foreach ($fotosVisibles as $fotografia)
                                    @php
                                        $esReferencia = $sesion->referencia_foto_id === $fotografia->id;
                                        $diagnostico = $fotografia->metricas ?? [];
                                    @endphp
                                    <article wire:key="foto-fotogrametria-{{ $fotografia->id }}" @class(['overflow-hidden rounded-2xl border', 'border-brand-blue ring-1 ring-brand-blue/20' => $esReferencia, 'border-red-200' => ! $esReferencia && $fotografia->estado === 'rejected', 'border-slate-200' => ! $esReferencia && $fotografia->estado !== 'rejected'])>
                                        <button type="button" x-on:click="$dispatch('ver-foto', { url: @js(route('admin.fotogrametria.foto', ['modelado' => $sesion->id, 'foto' => $fotografia->id, 'original' => 1])), titulo: @js('#'.str_pad((string) $fotografia->ranura, 2, '0', STR_PAD_LEFT).' · '.$fotografia->nombre_original) })" class="relative block w-full bg-slate-100 transition hover:opacity-90" aria-label="Ampliar fotografía {{ $fotografia->ranura }}: {{ $fotografia->nombre_original }}">
                                            <img src="{{ $fotografia->url }}" alt="{{ $fotografia->nombre_original }}" class="aspect-[4/3] w-full object-contain" loading="lazy">
                                            <span class="absolute left-2 top-2 rounded-lg bg-white/95 px-2 py-1 text-xs font-semibold text-brand-ink">#{{ str_pad((string) $fotografia->ranura, 2, '0', STR_PAD_LEFT) }}</span>
                                            @if ($esReferencia)<span class="absolute bottom-2 left-2 rounded-lg bg-brand-blue px-2 py-1 text-xs font-semibold text-white">Foto de referencia</span>@endif
                                        </button>
                                        <div class="space-y-3 p-3">
                                            <div><p class="truncate text-xs font-semibold text-brand-ink" title="{{ $fotografia->nombre_original }}">{{ $fotografia->nombre_original }}</p><p class="mt-1 text-[11px] text-slate-500">{{ $fotografia->ancho }} × {{ $fotografia->alto }} px · {{ number_format($fotografia->bytes / 1048576, 2) }} MB</p></div>
                                            <span @class(['inline-flex rounded-full px-2 py-1 text-[11px] font-semibold', 'bg-slate-100 text-slate-600' => $fotografia->estado === 'pendiente', 'bg-emerald-50 text-emerald-700' => $fotografia->estado === 'compatible', 'bg-amber-50 text-amber-700' => $fotografia->estado === 'review', 'bg-red-50 text-red-700' => $fotografia->estado === 'rejected'])>{{ $estadosFoto[$fotografia->estado] ?? $fotografia->estado }}</span>
                                            @if ($fotografia->motivos)
                                                <ul class="space-y-1 text-xs leading-relaxed text-slate-600">@foreach ($fotografia->motivos as $motivo)<li wire:key="motivo-{{ $fotografia->id }}-{{ $loop->index }}">{{ $motivo }}</li>@endforeach</ul>
                                            @endif
                                            <details class="text-xs text-slate-500">
                                                <summary class="cursor-pointer font-semibold text-brand-blue">Ver diagnóstico</summary>
                                                @if ($fotografia->mascara)
                                                    <button type="button" x-on:click="$dispatch('ver-foto', { url: @js(route('admin.fotogrametria.foto', ['modelado' => $sesion->id, 'foto' => $fotografia->id, 'mascara' => 1])), titulo: @js('Máscara de IA #'.str_pad((string) $fotografia->ranura, 2, '0', STR_PAD_LEFT)) })" class="mt-2 inline-block font-semibold text-brand-blue hover:underline">Ver máscara de IA ({{ number_format(($fotografia->fraccion_mascara ?? 0) * 100, 1) }} % juguete)</button>
                                                @endif
                                                <dl class="mt-2 grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-2">
                                                    <div><dt>Nitidez</dt><dd class="font-semibold text-slate-700">{{ isset($diagnostico['sharpness']) ? number_format($diagnostico['sharpness'], 1) : '—' }}</dd></div>
                                                    <div><dt>Conexiones</dt><dd class="font-semibold text-slate-700">{{ $diagnostico['connections'] ?? '—' }}</dd></div>
                                                    <div><dt>Puntos visuales</dt><dd class="font-semibold text-slate-700">{{ $diagnostico['features'] ?? '—' }}</dd></div>
                                                    <div><dt>Píxeles oscuros</dt><dd class="font-semibold text-slate-700">{{ isset($diagnostico['dark_fraction']) ? number_format($diagnostico['dark_fraction'] * 100, 1).' %' : '—' }}</dd></div>
                                                    <div><dt>Píxeles quemados</dt><dd class="font-semibold text-slate-700">{{ isset($diagnostico['bright_fraction']) ? number_format($diagnostico['bright_fraction'] * 100, 1).' %' : '—' }}</dd></div>
                                                </dl>
                                            </details>
                                            <fieldset @disabled($analizando) x-bind:disabled="subiendo || {{ $analizando ? 'true' : 'false' }}" class="space-y-2 disabled:opacity-50">
                                                <label class="block text-[11px] font-semibold text-slate-600">Vista manual<select wire:change="clasificarVista({{ $fotografia->id }}, $event.target.value)" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white text-xs">@foreach ($vistas as $valor => $nombre)<option wire:key="vista-foto-{{ $fotografia->id }}-{{ $valor }}" value="{{ $valor }}" @selected($fotografia->vista === $valor)>{{ $nombre }}</option>@endforeach</select></label>
                                                <label class="block text-[11px] font-semibold text-slate-600">Evaluación humana<select wire:change="etiquetarFoto({{ $fotografia->id }}, $event.target.value)" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white text-xs"><option value="sin_etiqueta" @selected($fotografia->etiqueta_humana === null)>Sin evaluar</option><option value="apta" @selected($fotografia->etiqueta_humana === true)>Apta para esta colección</option><option value="no_apta" @selected($fotografia->etiqueta_humana === false)>No apta para esta colección</option></select></label>
                                                @if (! $esReferencia)<button type="button" wire:click="seleccionarReferencia({{ $fotografia->id }})" wire:loading.attr="disabled" class="min-h-9 w-full rounded-lg border border-blue-200 px-2 py-1.5 text-xs font-semibold text-brand-blue hover:bg-blue-50">Usar como referencia</button>@endif
                                                <label for="reemplazo-{{ $fotografia->id }}" class="block text-[11px] font-semibold text-slate-600">Reemplazar fotografía<input id="reemplazo-{{ $fotografia->id }}" type="file" accept="image/jpeg,image/png,image/webp" x-on:change="subir($event.target, {{ $fotografia->id }})" class="mt-1 block w-full text-[10px] text-slate-500 file:mr-1 file:rounded-lg file:border-0 file:bg-slate-100 file:px-2 file:py-2 file:text-xs file:font-semibold file:text-slate-700"></label>
                                            </fieldset>
                                        </div>
                                    </article>
                                @endforeach
                                @if ($filtroFoto === '' && $filtroVista === '')
                                    @for ($posicion = $fotografias->count() + 1; $posicion <= 48; $posicion++)
                                        <div wire:key="posicion-vacia-{{ $sesion->id }}-{{ $posicion }}" class="flex min-h-28 items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 p-4 text-center"><div><p class="text-lg font-semibold text-slate-300">{{ str_pad((string) $posicion, 2, '0', STR_PAD_LEFT) }}</p><p class="mt-1 text-xs text-slate-400">Esperando fotografía</p></div></div>
                                    @endfor
                                @elseif ($fotosVisibles->isEmpty())
                                    <p class="rounded-xl bg-slate-50 p-5 text-sm text-slate-500 sm:col-span-2 2xl:col-span-3">Sin fotos con estos filtros</p>
                                @endif
                            </div>
                        </details>

                        <details class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" wire:ignore.self>
                            <summary class="cursor-pointer text-sm font-semibold text-brand-ink">Evaluación del aporte</summary>
                            <div class="mt-3 flex flex-wrap items-start justify-between gap-3"><p class="max-w-2xl text-xs leading-relaxed text-slate-500">Compara la decisión automática con tus etiquetas humanas.</p><div class="flex flex-wrap gap-2"><a href="{{ route('admin.fotogrametria.exportar', ['modelado' => $sesion->id]) }}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-brand-blue">Exportar evidencia JSON</a><a href="{{ route('admin.fotogrametria.dataset', ['modelado' => $sesion->id]) }}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-brand-blue">Dataset CSV</a></div></div>
                            <p class="mt-3 rounded-xl bg-amber-50 p-3 text-xs leading-relaxed text-amber-800">Versión inicial basada en reglas y correspondencias visuales; todavía no es un modelo de aprendizaje automático entrenado. La etiqueta humana es independiente y no cambia automáticamente el diagnóstico.</p>
                            <div class="mt-4 grid gap-5 lg:grid-cols-2">
                                <div class="overflow-x-auto"><table class="w-full text-left text-xs"><caption class="mb-2 text-left font-semibold text-slate-700">Matriz de confusión · Positivo = imagen no apta</caption><thead><tr class="bg-slate-50"><th scope="col" class="p-3">Sistema / Evaluación humana</th><th scope="col" class="p-3">No apta</th><th scope="col" class="p-3">Apta</th></tr></thead><tbody><tr class="border-b border-slate-100"><th scope="row" class="p-3 font-semibold">Rechazada</th><td class="p-3 text-emerald-700">VP: {{ $resumen['matriz']['vp'] }}</td><td class="p-3 text-red-700">FP: {{ $resumen['matriz']['fp'] }}</td></tr><tr><th scope="row" class="p-3 font-semibold">Compatible</th><td class="p-3 text-amber-700">FN: {{ $resumen['matriz']['fn'] }}</td><td class="p-3 text-emerald-700">VN: {{ $resumen['matriz']['vn'] }}</td></tr></tbody></table><p class="mt-2 text-[11px] leading-relaxed text-slate-500">Solo cuentan fotos con etiqueta humana y decisión automática concluyente. Pendientes y casos para revisar quedan fuera de la matriz. La cobertura es la proporción de fotos etiquetadas que recibieron una decisión concluyente.</p></div>
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    @foreach (['precision' => 'Precisión', 'recall' => 'Sensibilidad', 'f1' => 'F1', 'exactitud' => 'Exactitud', 'cobertura' => 'Cobertura de decisión'] as $clave => $nombre)
                                        <div wire:key="metrica-{{ $clave }}" class="rounded-xl bg-slate-50 p-3"><p class="text-[11px] text-slate-500">{{ $nombre }}</p><p class="mt-1 text-lg font-semibold text-brand-ink">{{ isset($resumen['metricas'][$clave]) ? number_format($resumen['metricas'][$clave] * 100, 1).' %' : '—' }}</p></div>
                                    @endforeach
                                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-[11px] text-slate-500">Etiquetas humanas</p><p class="mt-1 text-lg font-semibold text-brand-ink">{{ $resumen['etiquetadas'] }} / {{ $resumen['total'] }}</p></div>
                                </div>
                            </div>
                            <p class="mt-3 text-[11px] text-slate-500">«—» indica que faltan datos o que el cálculo tiene denominador cero. Una métrica alta con pocas etiquetas no basta para evaluar el aporte.</p>
                            @if (! empty($resumen['pares']))
                                <details class="mt-4 rounded-xl border border-slate-200 p-3"><summary class="cursor-pointer text-xs font-semibold text-brand-blue">Relaciones entre fotografías</summary><div class="mt-3 max-h-60 overflow-auto"><table class="w-full text-left text-xs"><thead class="bg-slate-50"><tr><th class="p-2">Foto A</th><th class="p-2">Foto B</th><th class="p-2">Coincidencias verificadas</th><th class="p-2">Proporción</th><th class="p-2">Relación</th></tr></thead><tbody>@foreach ($resumen['pares'] as $par)<tr wire:key="par-fotos-{{ $loop->index }}" class="border-b border-slate-100"><td class="p-2">#{{ $fotografias->firstWhere('id', $par['a'])?->ranura ?? $par['a'] }}</td><td class="p-2">#{{ $fotografias->firstWhere('id', $par['b'])?->ranura ?? $par['b'] }}</td><td class="p-2">{{ $par['inliers'] }}</td><td class="p-2">{{ number_format($par['ratio'] * 100, 1) }} %</td><td class="p-2">{{ $par['compatible'] ? 'Compatible' : 'No concluyente' }}</td></tr>@endforeach</tbody></table></div></details>
                            @endif
                        </details>
                    </section>
                @else
                    <section class="rounded-3xl border border-dashed border-blue-200 bg-white p-8 text-center sm:p-14">
                        <span class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-blue-50 text-2xl font-semibold text-brand-blue">48</span>
                        <h2 class="mt-5 text-xl font-semibold text-brand-ink">Primero, una colección consistente</h2>
                        <p class="mx-auto mt-2 max-w-xl text-sm leading-relaxed text-slate-500">Elige un juguete para empezar.</p>
                        <div class="mx-auto mt-7 grid max-w-2xl gap-3 text-left sm:grid-cols-3"><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold text-brand-blue">01 · Subir</p><p class="mt-1 text-xs text-slate-500">Desde tu laptop, en uno o varios grupos.</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold text-brand-blue">02 · Validar</p><p class="mt-1 text-xs text-slate-500">Calidad, duplicados y relaciones visuales.</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold text-brand-blue">03 · Corregir</p><p class="mt-1 text-xs text-slate-500">Reemplaza una toma sin perder las demás.</p></div></div>
                    </section>
                @endif
            </main>
        </div>

        <details class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm" data-product-manager>
            <summary class="cursor-pointer text-sm font-semibold text-brand-ink">Biblioteca de modelos 3D existentes</summary>
            <div class="mt-4 flex flex-wrap gap-3"><label class="text-xs text-slate-500">Buscar modelo<input type="search" wire:model.live.debounce.350ms="buscar" placeholder="Producto…" class="mt-1 block rounded-xl border border-slate-300 px-3 py-2 text-sm"></label><label class="text-xs text-slate-500">Estado<select wire:model.live="filtroEstado" class="mt-1 block rounded-xl border border-slate-300 py-2 pl-3 pr-8 text-sm"><option value="">Todos</option><option value="completados">Completados</option><option value="papelera">Papelera</option></select></label></div>
            <div class="mt-4 space-y-2">
                @forelse ($tasks as $task)
                    <div wire:key="modelo-existente-{{ $task->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-100 p-3"><div><p class="text-sm font-semibold text-brand-ink">#{{ $task->id }} · {{ $task->producto?->nombre ?? 'Sin producto enlazado' }}</p><p class="mt-1 text-xs text-slate-500">{{ $task->trashed() ? 'En papelera' : ($task->url_glb ? 'Modelo GLB disponible' : 'Registro histórico sin archivo GLB') }}</p></div><div class="flex flex-wrap gap-2">@if ($task->url_glb && ! $task->trashed())<button type="button" x-on:click="$wire.abrirVisor3D(@js($task->url_glb), @js($task->producto?->nombre ?? 'Modelo 3D'), {{ $task->id }})" class="rounded-lg bg-brand-blue px-3 py-2 text-xs font-semibold text-white">Ver modelo</button><a href="{{ $task->url_glb }}" download class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700">Descargar GLB</a>@if ($task->producto_id)<button type="button" wire:click="desenlazarProducto({{ $task->id }})" class="rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-600">Desenlazar</button>@endif @endif @if ($task->trashed())<button type="button" wire:click="restaurar({{ $task->id }})" class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">Restaurar</button>@else<button type="button" x-on:click="window.confirmarCrud($wire, 'eliminar', {{ $task->id }}, '¿Archivar modelado 3D?', 'El registro será enviado a la papelera.')" class="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">Archivar</button>@endif</div></div>
                @empty
                    <p class="py-4 text-sm text-slate-500">Sin modelos</p>
                @endforelse
            </div>
            @if ($tasks->hasPages())<div class="mt-4">{{ $tasks->links() }}</div>@endif
        </details>

        <div x-show="$wire.mostrarVisor3D" x-cloak x-on:keydown.escape.window="$wire.cerrarVisor3D()" class="fixed inset-0 z-50 flex items-center justify-center bg-brand-ink/80 p-4" role="dialog" aria-modal="true" aria-labelledby="titulo-visor-modelado" x-on:click.self="$wire.cerrarVisor3D()">
            <div class="w-full max-w-4xl overflow-hidden rounded-3xl bg-white shadow-xl"><div class="flex items-center justify-between gap-3 border-b border-slate-100 p-4"><h2 id="titulo-visor-modelado" class="font-semibold text-brand-ink">{{ $visorTitulo ?: 'Modelo 3D' }}</h2><button type="button" wire:click="cerrarVisor3D" class="rounded-xl bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-600">Cerrar</button></div><div class="bg-slate-950 p-4">@if ($visorGlbUrl)<model-viewer src="{{ $visorGlbUrl }}" alt="{{ $visorTitulo }}" camera-controls auto-rotate shadow-intensity="1" class="h-[450px] w-full" loading="eager"></model-viewer>@endif</div></div>
        </div>

        <!-- MODAL PARA AMPLIAR FOTOS Y MÁSCARAS (sin abrir otra pestaña) -->
        <div x-data="{ foto: null }" x-on:ver-foto.window="foto = $event.detail" x-on:keydown.escape.window="foto = null" wire:ignore>
            <template x-if="foto">
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4" x-on:click.self="foto = null" role="dialog" aria-modal="true" x-bind:aria-label="foto.titulo">
                    <div class="w-full max-w-5xl">
                        <div class="mb-3 flex items-center justify-between gap-3 text-white">
                            <p class="truncate text-sm font-semibold" x-text="foto.titulo"></p>
                            <button type="button" x-on:click="foto = null" class="grid size-9 shrink-0 place-items-center rounded-full bg-white/10 transition hover:bg-white/20" aria-label="Cerrar">
                                <x-lucide-x class="text-lg" />
                            </button>
                        </div>
                        <img x-bind:src="foto.url" x-bind:alt="foto.titulo" class="mx-auto max-h-[80vh] w-auto rounded-2xl bg-white object-contain">
                    </div>
                </div>
            </template>
        </div>
    </div>
</x-admin-shell>
