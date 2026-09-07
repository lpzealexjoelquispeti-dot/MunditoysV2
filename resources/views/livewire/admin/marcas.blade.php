<x-admin-shell active="marcas">
    @include('livewire.admin.partials.catalogo-listado', ['titulo' => 'Marcas', 'singular' => 'marca', 'icono' => '🏷️'])
    @include('livewire.admin.partials.marca-form-modal')
    @include('livewire.admin.partials.marca-detail-modal')
</x-admin-shell>
