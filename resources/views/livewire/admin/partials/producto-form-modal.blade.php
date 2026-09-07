<div x-data="mundiToysGlbPreview" x-effect="if (!$wire.modalAbierto) resetPreview()" x-show="$wire.modalAbierto" x-cloak x-on:keydown.escape.window="$wire.cerrarModal()" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="producto-modal-titulo">
    <div class="flex min-h-full items-center justify-center p-4 sm:p-6">
        <button type="button" class="fixed inset-0 bg-brand-ink/60 backdrop-blur-sm" x-on:click="$wire.cerrarModal()" aria-label="Cerrar formulario"></button>
        <section x-show="$wire.modalAbierto" x-transition class="relative w-full max-w-5xl overflow-hidden rounded-3xl bg-white shadow-2xl">
            <header class="border-b border-slate-100 bg-gradient-to-r from-orange-50 to-blue-50 px-5 py-4 sm:px-7">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.18em] text-brand-orange">Catálogo MundiToys</p>
                        <h2 id="producto-modal-titulo" class="mt-1 text-2xl font-bold text-brand-ink">{{ $productoId ? 'Editar producto' : 'Nuevo producto' }}</h2>
                    </div>
                    <button type="button" wire:click="cerrarModal" class="grid size-10 shrink-0 place-items-center rounded-full bg-white text-xl text-slate-500 shadow-sm hover:text-brand-red" aria-label="Cerrar">×</button>
                </div>

                <ol class="mt-4 grid grid-cols-2 gap-2" aria-label="Progreso del formulario">
                    <li @class(['flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-bold', 'bg-brand-blue text-white' => $paso === 1, 'bg-white/80 text-brand-blue' => $paso !== 1])><span class="grid size-6 place-items-center rounded-full bg-white/20">1</span> Datos del juguete</li>
                    <li @class(['flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-bold', 'bg-brand-blue text-white' => $paso === 2, 'bg-white/80 text-slate-500' => $paso !== 2])><span class="grid size-6 place-items-center rounded-full bg-black/5">2</span> Imágenes y modelo 3D</li>
                </ol>
            </header>

            <form wire:submit="guardar" class="max-h-[calc(100vh-13rem)] overflow-y-auto p-5 sm:p-7">
                @if ($paso === 1)
                    <div class="mb-5 rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-brand-blue">
                        <strong>Códigos automáticos:</strong> el código interno y el identificador QR seguro se generarán al guardar el producto.
                        @unless ($productoId)<span class="mt-1 block">El juguete se creará activo. Los precios se asignarán por lotes en Inventario.</span>@endunless
                    </div>

                    <div class="grid gap-x-5 gap-y-4 md:grid-cols-2">
                        <label class="md:col-span-2">
                            <span class="mb-1.5 block text-sm font-semibold text-brand-ink">Nombre del producto *</span>
                            <input wire:model.blur="nombre" type="text" maxlength="150" autocomplete="off" placeholder="Ej. Tren de madera educativo" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            <span class="mt-1 block text-xs text-slate-400">Solo letras y espacios.</span>
                            @error('nombre') <span class="mt-1 block text-xs font-medium text-brand-red">{{ $message }}</span> @enderror
                        </label>

                        <label>
                            <span class="mb-1.5 block text-sm font-semibold text-brand-ink">Categoría *</span>
                            <select wire:model.blur="categoriaId" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                <option value="">Selecciona una categoría</option>
                                @foreach ($categorias as $categoria)<option value="{{ $categoria['id'] }}">{{ $categoria['nombre'] }}</option>@endforeach
                            </select>
                            @error('categoriaId') <span class="mt-1 block text-xs font-medium text-brand-red">{{ $message }}</span> @enderror
                        </label>

                        <label>
                            <span class="mb-1.5 block text-sm font-semibold text-brand-ink">Marca *</span>
                            <select wire:model.blur="marcaId" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                <option value="">Selecciona una marca</option>
                                @foreach ($marcas as $marca)<option value="{{ $marca['id'] }}">{{ $marca['nombre'] }}</option>@endforeach
                            </select>
                            @error('marcaId') <span class="mt-1 block text-xs font-medium text-brand-red">{{ $message }}</span> @enderror
                        </label>

                        <label>
                            <span class="mb-1.5 block text-sm font-semibold text-brand-ink">Edad recomendada *</span>
                            <select wire:model.blur="edadRecomendada" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                <option value="">Selecciona un rango</option>
                                @foreach ($rangosEdad as $rango)<option value="{{ $rango }}">{{ $rango }} años</option>@endforeach
                            </select>
                            @error('edadRecomendada') <span class="mt-1 block text-xs font-medium text-brand-red">{{ $message }}</span> @enderror
                        </label>

                        <label>
                            <span class="mb-1.5 block text-sm font-semibold text-brand-ink">Dimensiones *</span>
                            <input wire:model.live.debounce.400ms="dimensiones" type="text" maxlength="100" placeholder="Ej. 30 x 20 x 15" aria-describedby="dimensiones-ayuda dimensiones-error" aria-invalid="{{ $errors->has('dimensiones') ? 'true' : 'false' }}" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            <span id="dimensiones-ayuda" class="mt-1 block text-xs text-slate-500">Largo x ancho x alto, en cm. Tres números mayores que cero; hasta 2 decimales.</span>
                            @error('dimensiones') <span id="dimensiones-error" role="status" class="mt-1 block text-xs font-medium text-brand-red">{{ $message }}</span> @enderror
                        </label>

                        <label class="md:col-span-2">
                            <span class="mb-1.5 block text-sm font-semibold text-brand-ink">Descripción *</span>
                            <textarea wire:model.blur="descripcion" rows="4" maxlength="2000" placeholder="Describe materiales, funciones y características principales" class="w-full resize-y rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></textarea>
                            <span class="mt-1 block text-right text-xs text-slate-400">Entre 10 y 2.000 caracteres</span>
                            @error('descripcion') <span class="mt-1 block text-xs font-medium text-brand-red">{{ $message }}</span> @enderror
                        </label>
                    </div>
                @else
                    <div class="grid gap-6 lg:grid-cols-[1.3fr_.7fr]">
                        <section>
                            <div class="flex items-end justify-between gap-3">
                                <div><h3 class="text-lg font-bold text-brand-ink">Galería del juguete *</h3><p class="text-sm text-slate-500">Carga entre 3 y 4 imágenes JPG, PNG o WEBP; máximo 3 MB cada una.</p></div>
                                <span class="rounded-full bg-brand-orange-soft px-3 py-1 text-xs font-bold text-brand-orange">{{ count($imagenesExistentes) + count($imagenes) }}/4</span>
                            </div>

                            <label class="mt-4 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-blue-200 bg-blue-50/50 px-5 py-7 text-center hover:border-brand-blue hover:bg-blue-50">
                                <input wire:model="imagenes" type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only">
                                <span class="text-4xl">📷</span><span class="mt-2 text-sm font-bold text-brand-blue">Seleccionar imágenes</span><span class="mt-1 text-xs text-slate-500">Puedes seleccionar varias a la vez</span>
                            </label>
                            <div wire:loading wire:target="imagenes" class="mt-2 text-xs font-semibold text-brand-blue">Validando y preparando imágenes…</div>
                            @error('imagenes') <span class="mt-2 block text-sm font-medium text-brand-red">{{ $message }}</span> @enderror
                            @error('imagenes.*') <span class="mt-2 block text-sm font-medium text-brand-red">{{ $message }}</span> @enderror

                            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                @foreach ($imagenesExistentes as $imagenExistente)
                                    <figure class="relative overflow-hidden rounded-xl border border-slate-200" wire:key="existente-{{ $imagenExistente['id'] }}">
                                        <img src="{{ $imagenExistente['url'] }}" alt="Imagen guardada" class="aspect-square w-full object-cover">
                                        <button type="button" wire:click="quitarImagenExistente({{ $imagenExistente['id'] }})" class="absolute right-2 top-2 grid size-7 place-items-center rounded-full bg-brand-red text-sm font-bold text-white" aria-label="Quitar imagen">×</button>
                                    </figure>
                                @endforeach
                                @foreach ($imagenes as $indice => $imagenNueva)
                                    <figure class="relative overflow-hidden rounded-xl border border-blue-200" wire:key="nueva-{{ $indice }}-{{ $imagenNueva->getFilename() }}">
                                        @if (!$errors->has('imagenes.'.$indice) && $imagenNueva->isPreviewable())
                                            <img src="{{ $imagenNueva->temporaryUrl() }}" alt="Vista previa de imagen nueva" class="aspect-square w-full object-cover">
                                        @else
                                            <p class="grid aspect-square place-items-center p-2 text-center text-xs text-brand-red">Imagen no válida; quítala y selecciona otra.</p>
                                        @endif
                                        <button type="button" wire:click="quitarImagenNueva({{ $indice }})" class="absolute right-2 top-2 grid size-7 place-items-center rounded-full bg-brand-red text-sm font-bold text-white" aria-label="Quitar imagen">×</button>
                                    </figure>
                                @endforeach
                            </div>
                        </section>

                        <section>
                            <h3 class="text-lg font-bold text-brand-ink">Modelo 3D opcional</h3>
                            <p class="text-sm text-slate-500">Solo formato GLB, máximo 50 MB.</p>
                            <label class="mt-4 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-orange-200 bg-orange-50/60 px-5 py-7 text-center hover:border-brand-orange">
                                <input wire:model="modeloGlb" x-ref="glbInput" x-on:change="selectFile($event.target.files[0])" x-on:livewire-upload-start="uploading = true" x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-error="uploadFailed()" x-on:livewire-upload-cancel="uploading = false" :disabled="uploading" type="file" accept=".glb" class="sr-only">
                                <span class="text-4xl">🧊</span><span class="mt-2 text-sm font-bold text-brand-orange">Seleccionar archivo .glb</span>
                            </label>
                            <div wire:loading wire:target="modeloGlb" class="mt-2 text-xs font-semibold text-brand-orange">Preparando modelo 3D…</div>
                            @error('modeloGlb') <span class="mt-2 block text-sm font-medium text-brand-red">{{ $message }}</span> @enderror

                            <p x-show="previewError" x-text="previewError" role="status" class="mt-2 text-sm font-medium text-brand-red"></p>
                            <div wire:ignore x-show="previewUrl" class="mt-4 overflow-hidden rounded-2xl border border-orange-100 bg-slate-50">
                                <template x-if="previewUrl">
                                    <model-viewer :src="previewUrl" alt="Vista previa del modelo seleccionado" x-on:error="previewError = 'No se pudo visualizar el modelo 3D. Revisa el archivo o selecciona otro.'" camera-controls auto-rotate shadow-intensity="1" class="h-56 w-full"></model-viewer>
                                </template>
                                <p x-text="fileName" class="break-all px-3 py-2 text-xs text-slate-500"></p>
                            </div>
                            <button type="button" x-show="previewUrl || previewError || $wire.modeloGlb" :disabled="uploading" x-on:click="resetPreview(); $wire.quitarModeloGlb()" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold text-brand-red disabled:opacity-50">Quitar modelo seleccionado</button>
                            @if ($modeloActual && !$modeloGlb)
                                <div class="mt-4 overflow-hidden rounded-2xl border border-orange-100 bg-slate-50">
                                    <model-viewer src="{{ asset('storage/'.$modeloActual) }}" camera-controls auto-rotate shadow-intensity="1" class="h-56 w-full"></model-viewer>
                                    <p class="border-t border-slate-200 px-3 py-2 text-center text-xs text-slate-500">Modelo guardado; selecciona otro para reemplazarlo.</p>
                                </div>
                            @endif
                        </section>
                    </div>
                @endif

                <footer class="sticky -bottom-7 mt-7 flex flex-col-reverse gap-3 border-t border-slate-100 bg-white py-4 sm:flex-row sm:justify-end">
                    <button type="button" wire:click="cerrarModal" class="min-h-11 rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Cancelar</button>
                    @if ($paso === 2)
                        <button type="button" wire:click="anteriorPaso" class="min-h-11 rounded-xl border border-brand-blue px-5 py-2.5 text-sm font-bold text-brand-blue hover:bg-blue-50">Anterior</button>
                        <button type="submit" :disabled="uploading || previewError !== ''" wire:loading.attr="disabled" wire:target="guardar,imagenes,modeloGlb" class="min-h-11 rounded-xl bg-brand-red px-6 py-2.5 text-sm font-bold text-white hover:bg-brand-red-dark disabled:cursor-wait disabled:opacity-60"><span wire:loading.remove wire:target="guardar">{{ $productoId ? 'Guardar cambios' : 'Crear producto' }}</span><span wire:loading wire:target="guardar">Guardando…</span></button>
                    @else
                        <button type="button" wire:click="siguientePaso" class="min-h-11 rounded-xl bg-brand-blue px-6 py-2.5 text-sm font-bold text-white hover:bg-brand-blue-dark">Validar y continuar →</button>
                    @endif
                </footer>
            </form>
        </section>
    </div>
</div>
