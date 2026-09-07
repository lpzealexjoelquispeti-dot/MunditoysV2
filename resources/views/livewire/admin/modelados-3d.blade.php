<x-admin-shell active="modelos-3d">
    <div class="mx-auto max-w-[1700px] space-y-8" data-modelado-3d data-product-manager wire:poll.10s>
        {{-- ENCABEZADO DEL MÓDULO --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <span class="text-xs font-bold uppercase tracking-[.2em] text-brand-orange">Laboratorio de IA Integrado</span>
                <h1 class="mt-1 text-3xl font-bold text-brand-ink sm:text-4xl">Generador de Modelados 3D</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Transforma de 2 a 4 fotografías multi-ángulo de un juguete en una malla tridimensional (.glb) de alta fidelidad con Tripo AI.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 border border-emerald-200">
                    <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span> Tripo AI Conectado
                </span>
            </div>
        </div>

        @if (session('status'))
            <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800" role="status">
                <span aria-hidden="true" class="text-lg">✓</span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{-- CONTENEDOR PRINCIPAL: ESTUDIO DE GENERACIÓN Y BIBLIOTECA DE MODELOS --}}
        <div class="grid gap-8 lg:grid-cols-12 items-start">
            
            {{-- COLUMNA IZQUIERDA: FORMULARIO DE GENERACIÓN --}}
            <div class="lg:col-span-5 space-y-6">
                <div class="rounded-3xl border border-orange-100 bg-white p-6 shadow-sm sm:p-7">
                    <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                        <div class="grid size-10 place-items-center rounded-2xl bg-brand-orange/10 text-xl text-brand-orange font-black">
                            🧊
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-brand-ink">Nuevo Modelado 3D</h2>
                            <p class="text-xs text-slate-500">Carga entre 2 y 4 fotografías desde distintos ángulos</p>
                        </div>
                    </div>

                    <form wire:submit="procesarModelado" class="mt-6 space-y-6">
                        {{-- 1. ASIGNACIÓN DE PRODUCTO --}}
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                                1. Asociar con Juguete del Catálogo
                            </label>

                            @if ($productoSeleccionado)
                                <div class="flex items-center justify-between rounded-2xl border border-blue-200 bg-blue-50/60 p-3.5 transition">
                                    <div class="flex items-center gap-3 min-w-0">
                                        @if ($productoSeleccionado['imagen'])
                                            <img src="{{ $productoSeleccionado['imagen'] }}" alt="{{ $productoSeleccionado['nombre'] }}" class="size-12 rounded-xl object-cover bg-white shadow-xs">
                                        @else
                                            <div class="grid size-12 place-items-center rounded-xl bg-white text-2xl shadow-xs">🧸</div>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="text-sm font-bold text-brand-ink truncate">{{ $productoSeleccionado['nombre'] }}</p>
                                            <p class="text-xs text-slate-500 font-mono">{{ $productoSeleccionado['codigo'] }}</p>
                                            @if ($productoSeleccionado['tiene_3d'])
                                                <span class="inline-block mt-0.5 text-[10px] font-semibold text-emerald-700 bg-emerald-100/70 px-2 py-0.5 rounded-full">
                                                    Ya tiene modelo 3D
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <button type="button" wire:click="quitarProductoSeleccionado" class="ml-2 rounded-xl bg-white px-3 py-1.5 text-xs font-bold text-slate-600 shadow-xs hover:bg-red-50 hover:text-brand-red transition">
                                        Cambiar
                                    </button>
                                </div>
                            @else
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-slate-400">⌕</span>
                                    <input 
                                        type="search" 
                                        wire:model.live.debounce.350ms="buscarProducto" 
                                        placeholder="Buscar por nombre o código del producto..." 
                                        class="min-h-11 w-full rounded-2xl border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm text-brand-ink placeholder:text-slate-400 focus:border-brand-blue focus:ring-brand-blue"
                                    >
                                    @if (!empty($productosSugeridos))
                                        <div class="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                                            @foreach ($productosSugeridos as $sugerencia)
                                                <button 
                                                    type="button" 
                                                    wire:click="seleccionarProducto({{ $sugerencia['id'] }})" 
                                                    class="flex w-full items-center gap-3 rounded-xl p-2 text-left hover:bg-slate-50 transition"
                                                >
                                                    @if ($sugerencia['imagen'])
                                                        <img src="{{ $sugerencia['imagen'] }}" alt="{{ $sugerencia['nombre'] }}" class="size-9 rounded-lg object-cover bg-slate-100">
                                                    @else
                                                        <div class="grid size-9 place-items-center rounded-lg bg-slate-100 text-lg">🧸</div>
                                                    @endif
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-xs font-bold text-brand-ink truncate">{{ $sugerencia['nombre'] }}</p>
                                                        <p class="text-[11px] text-slate-500 font-mono">{{ $sugerencia['codigo'] }}</p>
                                                    </div>
                                                    @if ($sugerencia['tiene_3d'])
                                                        <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md">3D Activo</span>
                                                    @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <p class="mt-1 text-[11px] text-slate-500">Opcional: puedes asociar o enlazar el modelo más tarde.</p>
                            @endif
                            @error('productoId') <span class="mt-1 block text-xs font-medium text-brand-red">{{ $message }}</span> @enderror
                        </div>

                        {{-- 2. CARGA DE FOTOGRAFÍAS MULTI-ÁNGULO --}}
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    2. Fotografías Multi-Ángulo (2 a 4 fotos)
                                </label>
                                <span class="text-xs font-semibold text-brand-blue">
                                    {{ count($fotos) }} / 4 seleccionadas
                                </span>
                            </div>

                            {{-- ÁREA DROPZONE --}}
                            <div class="relative rounded-3xl border-2 border-dashed border-slate-300 bg-slate-50/60 p-6 text-center transition hover:border-brand-blue hover:bg-blue-50/30">
                                <input 
                                    type="file" 
                                    wire:model="fotos" 
                                    id="input-fotos-3d" 
                                    multiple 
                                    accept="image/png,image/jpeg,image/webp,image/jpg" 
                                    class="absolute inset-0 h-full w-full opacity-0 cursor-pointer"
                                >
                                <div class="grid size-12 mx-auto place-items-center rounded-2xl bg-white shadow-xs text-2xl">
                                    📸
                                </div>
                                <p class="mt-3 text-sm font-bold text-brand-ink">
                                    Haz clic para subir o arrastra las fotos aquí
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    PNG, JPG o WEBP (máx. 3 MB por foto). Recomendado: fondo neutro y buena iluminación.
                                </p>

                                {{-- INDICADOR DE CARGA --}}
                                <div wire:loading wire:target="fotos" class="mt-3 text-xs font-bold text-brand-blue flex items-center justify-center gap-2">
                                    <svg class="size-4 animate-spin text-brand-blue" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                    <span>Cargando imágenes al navegador...</span>
                                </div>
                            </div>

                            @error('fotos') <span class="mt-1.5 block text-xs font-medium text-brand-red">{{ $message }}</span> @enderror
                            @error('fotos.*') <span class="mt-1 block text-xs font-medium text-brand-red">{{ $message }}</span> @enderror

                            {{-- MINIATURAS DE LAS FOTOS SELECCIONADAS --}}
                            @if (!empty($fotos))
                                <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3">
                                    @foreach ($fotos as $index => $foto)
                                        @if (method_exists($foto, 'temporaryUrl'))
                                            <div class="relative group rounded-2xl overflow-hidden border border-slate-200 bg-white aspect-square shadow-xs">
                                                <img src="{{ $foto->temporaryUrl() }}" alt="Vista previa {{ $index + 1 }}" class="h-full w-full object-cover">
                                                <span class="absolute bottom-1 left-1 rounded-md bg-brand-ink/80 px-1.5 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider">
                                                    Ángulo {{ $index + 1 }}
                                                </span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            {{-- GUÍA DE ÁNGULOS --}}
                            <div class="mt-4 rounded-2xl bg-amber-50/80 p-3.5 border border-amber-200/70 text-xs text-amber-900">
                                <p class="font-bold flex items-center gap-1.5">
                                    <span>💡</span> Recomendación para mejores resultados:
                                </p>
                                <ul class="mt-1.5 list-disc list-inside space-y-0.5 text-[11px] text-amber-800">
                                    <li><strong>Foto 1:</strong> Frente del juguete centrado.</li>
                                    <li><strong>Foto 2:</strong> Lateral derecho o perfil.</li>
                                    <li><strong>Foto 3:</strong> Vista posterior (espalda).</li>
                                    <li><strong>Foto 4:</strong> Lateral izquierdo o ángulo superior.</li>
                                </ul>
                            </div>
                        </div>

                        {{-- BOTÓN SUBMIT CON LOADER --}}
                        <div>
                            <button 
                                type="submit" 
                                wire:loading.attr="disabled" 
                                wire:target="procesarModelado, fotos" 
                                @disabled(count($fotos) < 2 || count($fotos) > 4) 
                                class="w-full flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-brand-orange to-brand-red px-6 py-3.5 text-sm font-bold text-white shadow-md transition hover:opacity-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-red disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span wire:loading.remove wire:target="procesarModelado">
                                    ✨ Iniciar Generación 3D con Tripo AI
                                </span>
                                <span wire:loading wire:target="procesarModelado" class="flex items-center gap-2">
                                    <svg class="size-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                    <span>Enviando tarea a Tripo AI...</span>
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- COLUMNA DERECHA: BIBLIOTECA Y COLA DE TAREAS 3D --}}
            <div class="lg:col-span-7 space-y-6">
                
                {{-- BARRA DE FILTROS Y ESTADOS --}}
                <div class="rounded-3xl border border-orange-100 bg-white p-4 sm:p-5 shadow-sm">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <span class="mb-1.5 block text-xs font-semibold text-slate-600">Buscar en biblioteca</span>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-slate-400">⌕</span>
                                <input 
                                    type="search" 
                                    wire:model.live.debounce.350ms="buscar" 
                                    placeholder="Buscar por ID de tarea o producto..." 
                                    class="min-h-11 w-full rounded-2xl border border-slate-300 bg-white py-2 pl-9 pr-3 text-xs text-brand-ink placeholder:text-slate-400 focus:border-brand-blue"
                                >
                            </div>
                        </div>

                        <div>
                            <span class="mb-1.5 block text-xs font-semibold text-slate-600">Filtrar por estado</span>
                            <select wire:model.live="filtroEstado" class="min-h-11 w-full rounded-2xl border border-slate-300 bg-white px-3 py-2 text-xs text-brand-ink focus:border-brand-blue">
                                <option value="">Todos los estados</option>
                                <option value="completed">Completados / Disponibles</option>
                                <option value="running">En proceso (Tripo AI)</option>
                                <option value="queued">En cola de render</option>
                                <option value="failed">Fallidos</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- LISTA DE MODELOS Y TAREAS --}}
                <div class="space-y-4">
                    @forelse ($tasks as $task)
                        <div class="rounded-3xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-sm transition hover:border-slate-300">
                            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                                
                                {{-- INFORMACIÓN DEL MODELO / PRODUCTO --}}
                                <div class="flex items-start gap-4 min-w-0">
                                    <div class="relative size-16 sm:size-20 shrink-0 rounded-2xl overflow-hidden bg-slate-100 border border-slate-200">
                                        @if (!empty($task->original_images) && is_array($task->original_images) && count($task->original_images) > 0)
                                            <img src="{{ $task->original_images[0] }}" alt="Foto base" class="size-full object-cover">
                                        @elseif ($task->producto && $task->producto->imagenes->first())
                                            <img src="{{ asset('storage/' . $task->producto->imagenes->first()->ruta_archivo) }}" alt="{{ $task->producto->nombre }}" class="size-full object-cover">
                                        @else
                                            <div class="grid size-full place-items-center text-3xl">🧊</div>
                                        @endif

                                        <span class="absolute bottom-1 right-1 rounded-md bg-brand-ink/80 px-1 py-0.2 text-[9px] font-bold text-white uppercase">
                                            {{ $task->tipo_generacion === 'carga_manual' ? 'Manual' : 'Tripo AI' }}
                                        </span>
                                    </div>

                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-xs font-mono font-bold text-slate-400">#{{ $task->id }}</span>
                                            
                                            {{-- BADGE DE ESTADO --}}
                                            @if ($task->es_completado)
                                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-200">
                                                    <span class="size-1.5 rounded-full bg-emerald-500"></span> Listo 3D
                                                </span>
                                            @elseif ($task->es_en_proceso)
                                                <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-700 border border-blue-200">
                                                    <svg class="size-3 animate-spin text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                                    </svg>
                                                    Renderizando ({{ $task->progreso }}%)
                                                </span>
                                            @elseif ($task->es_fallido)
                                                <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-bold text-red-700 border border-red-200">
                                                    ✕ Error
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-700 border border-amber-200">
                                                    ⏳ En cola
                                                </span>
                                            @endif

                                            @if ($task->producto_id && $task->producto)
                                                <span class="text-xs font-bold text-brand-blue bg-blue-50 px-2 py-0.5 rounded-md">
                                                    Enlazado a Producto
                                                </span>
                                            @endif
                                        </div>

                                        <h3 class="mt-1 text-base font-bold text-brand-ink truncate">
                                            @if ($task->producto)
                                                {{ $task->producto->nombre }}
                                            @else
                                                <span class="text-slate-500 font-normal">Sin producto vinculado</span>
                                            @endif
                                        </h3>

                                        @if ($task->task_id)
                                            <p class="text-[11px] text-slate-400 font-mono truncate">
                                                Task ID: {{ $task->task_id }}
                                            </p>
                                        @endif

                                        <p class="text-[11px] text-slate-400 mt-0.5">
                                            Creado: {{ $task->created_at->diffForHumans() }}
                                        </p>
                                    </div>
                                </div>

                                {{-- ACCIONES Y BOTONES --}}
                                <div class="flex items-center gap-2 self-end sm:self-start flex-wrap">
                                    {{-- SINCRONIZAR MANUALMENTE CON TRIPO SI ESTÁ EN PROCESO --}}
                                    @if ($task->es_en_proceso || $task->status === 'queued')
                                        <button 
                                            type="button" 
                                            wire:click="actualizarEstado({{ $task->id }})" 
                                            class="rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-bold text-brand-blue hover:bg-blue-100 transition flex items-center gap-1.5"
                                            title="Consultar estado en Tripo AI"
                                        >
                                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                            <span>Sincronizar</span>
                                        </button>
                                    @endif

                                    {{-- VISUALIZAR EN MODAL 3D --}}
                                    @if ($task->es_completado && $task->url_glb)
                                        <button 
                                            type="button" 
                                            wire:click="abrirVisor3D('{{ $task->url_glb }}', '{{ $task->producto?->nombre ?? 'Modelo 3D #' . $task->id }}', {{ $task->id }})" 
                                            class="rounded-xl bg-brand-blue px-3.5 py-2 text-xs font-bold text-white shadow-xs hover:bg-brand-blue-dark transition flex items-center gap-1.5"
                                        >
                                            <span>👁️</span> Ver en Visor 3D
                                        </button>

                                        {{-- DESCARGAR GLB --}}
                                        <a 
                                            href="{{ $task->url_glb }}" 
                                            download 
                                            class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition"
                                            title="Descargar archivo .glb"
                                        >
                                            📥 .GLB
                                        </a>

                                        {{-- ENLAZAR / DESENLAZAR DE PRODUCTO --}}
                                        @if ($task->producto_id)
                                            <button 
                                                type="button" 
                                                wire:click="desenlazarProducto({{ $task->id }})" 
                                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition"
                                                title="Desenlazar este modelo del catálogo del juguete"
                                            >
                                                Desenlazar
                                            </button>
                                        @endif
                                    @endif

                                    {{-- ELIMINAR REGISTRO --}}
                                    <button 
                                        type="button" 
                                        x-on:click="window.confirmarCrud($wire, 'eliminar', {{ $task->id }}, '¿Archivar modelado 3D?', 'El registro será eliminado del listado.')" 
                                        class="rounded-xl bg-red-50 px-2.5 py-2 text-xs font-bold text-brand-red hover:bg-red-100 transition"
                                        title="Eliminar modelado"
                                    >
                                        🗑️
                                    </button>
                                </div>
                            </div>

                            {{-- BARRA DE PROGRESO SI ESTÁ EN RENDER --}}
                            @if ($task->es_en_proceso)
                                <div class="mt-4 pt-3 border-t border-slate-100">
                                    <div class="flex justify-between text-[11px] font-semibold text-slate-500 mb-1">
                                        <span>Procesando geometría y texturas con Tripo AI...</span>
                                        <span>{{ $task->progreso }}%</span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                        <div class="bg-gradient-to-r from-brand-orange to-brand-blue h-2 rounded-full transition-all duration-500" style="width: {{ max(5, $task->progreso) }}%"></div>
                                    </div>
                                </div>
                            @elseif ($task->es_fallido && $task->error_message)
                                <div class="mt-3 rounded-xl bg-red-50/80 p-3 text-xs text-red-700">
                                    <strong>Detalle del error:</strong> {{ $task->error_message }}
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-3xl border-2 border-dashed border-orange-200 bg-white p-12 text-center">
                            <div class="mx-auto grid size-16 place-items-center rounded-2xl bg-orange-50 text-3xl">
                                🧊
                            </div>
                            <h3 class="mt-4 text-base font-bold text-brand-ink">No hay modelados 3D registrados</h3>
                            <p class="mt-1 text-xs text-slate-500 max-w-sm mx-auto">
                                Sube fotografías desde el formulario de la izquierda para comenzar a generar los modelos tridimensionales interactivos.
                            </p>
                        </div>
                    @endforelse
                </div>

                {{-- PAGINACIÓN --}}
                @if ($tasks->hasPages())
                    <div class="rounded-2xl bg-white p-4 shadow-sm">
                        {{ $tasks->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- MODAL INTERACTIVO DE VISOR 3D GOOGLE MODEL-VIEWER --}}
    <div 
        x-data 
        x-show="$wire.mostrarVisor3D" 
        x-cloak 
        x-on:keydown.escape.window="$wire.cerrarVisor3D()" 
        class="fixed inset-0 z-50 overflow-y-auto" 
        role="dialog" 
        aria-modal="true"
    >
        <div class="flex min-h-full items-center justify-center p-4 sm:p-6">
            {{-- BACKDROP --}}
            <div class="fixed inset-0 bg-brand-ink/80 backdrop-blur-md transition-opacity" x-on:click="$wire.cerrarVisor3D()"></div>

            {{-- MODAL BOX --}}
            <div 
                x-show="$wire.mostrarVisor3D" 
                x-transition 
                class="relative w-full max-w-4xl overflow-hidden rounded-3xl bg-white shadow-2xl border border-slate-100"
            >
                {{-- HEADER MODAL --}}
                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-9 place-items-center rounded-xl bg-brand-orange/10 text-lg">🧊</span>
                        <div>
                            <h3 class="text-base font-bold text-brand-ink">{{ $visorTitulo ?: 'Visualizador 3D Interactivo' }}</h3>
                            <p class="text-xs text-slate-500">Malla 3D renderizada con soporte WebXR y Realidad Aumentada</p>
                        </div>
                    </div>
                    <button 
                        type="button" 
                        wire:click="cerrarVisor3D" 
                        class="grid size-9 place-items-center rounded-full bg-white text-slate-400 shadow-xs hover:bg-red-50 hover:text-brand-red transition"
                        aria-label="Cerrar modal"
                    >
                        ✕
                    </button>
                </div>

                {{-- CUERPO CON MODEL-VIEWER --}}
                <div class="relative bg-slate-950 p-6 flex flex-col items-center justify-center min-h-[460px]">
                    @if ($visorGlbUrl)
                        <model-viewer 
                            src="{{ $visorGlbUrl }}" 
                            alt="{{ $visorTitulo }}" 
                            camera-controls 
                            auto-rotate 
                            shadow-intensity="1.5" 
                            exposure="1.1" 
                            interaction-prompt="auto" 
                            ar 
                            ar-modes="webxr scene-viewer quick-look" 
                            class="w-full h-[450px] rounded-2xl outline-none"
                            loading="eager"
                        >
                            <div slot="poster" class="flex flex-col items-center justify-center h-full text-slate-400">
                                <svg class="size-8 animate-spin text-brand-orange mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <span class="text-xs font-semibold">Cargando malla tridimensional...</span>
                            </div>
                        </model-viewer>
                    @endif

                    {{-- INSTRUCCIONES DE INTERACCIÓN --}}
                    <div class="absolute bottom-4 left-6 right-6 flex items-center justify-between text-[11px] text-slate-400 bg-slate-900/80 backdrop-blur-sm rounded-xl px-4 py-2 pointer-events-none">
                        <span class="flex items-center gap-1.5">
                            <span>🖱️</span> Haz clic y arrastra para orbitar
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span>🔍</span> Scroll para acercar o alejar
                        </span>
                        <span class="hidden sm:flex items-center gap-1.5">
                            <span>📱</span> Compatible con Realidad Aumentada (AR)
                        </span>
                    </div>
                </div>

                {{-- FOOTER MODAL --}}
                <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-6 py-4">
                    <div class="text-xs text-slate-500">
                        Formato estándar: <strong>GL Transmission Format (.glb)</strong>
                    </div>
                    <div class="flex items-center gap-3">
                        @if ($visorGlbUrl)
                            <a 
                                href="{{ $visorGlbUrl }}" 
                                download 
                                class="inline-flex items-center gap-1.5 rounded-xl bg-slate-200 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-300 transition"
                            >
                                <span>📥</span> Descargar .GLB
                            </a>
                        @endif
                        <button 
                            type="button" 
                            wire:click="cerrarVisor3D" 
                            class="rounded-xl bg-brand-blue px-4 py-2 text-xs font-bold text-white hover:bg-brand-blue-dark transition"
                        >
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-shell>
