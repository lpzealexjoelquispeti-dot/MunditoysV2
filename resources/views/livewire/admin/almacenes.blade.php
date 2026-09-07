<x-admin-shell active="almacenes">
    @include('livewire.admin.partials.operacion-listado', ['modulo' => 'almacenes', 'titulo' => 'Almacenes', 'singular' => 'almacén', 'nombreCampo' => 'nombre'])
    @include('livewire.admin.partials.almacen-form-modal')
    @include('livewire.admin.partials.operacion-detail-modal', ['campos' => ['nombre' => 'Nombre', 'sucursal' => 'Sucursal', 'tipo' => 'Tipo', 'asociados' => 'Inventarios asociados']])
</x-admin-shell>
