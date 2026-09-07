<x-admin-shell active="sucursales">
    @include('livewire.admin.partials.operacion-listado', ['modulo' => 'sucursales', 'titulo' => 'Sucursales', 'singular' => 'sucursal', 'nombreCampo' => 'nombre'])
    @include('livewire.admin.partials.sucursal-form-modal')
    @include('livewire.admin.partials.operacion-detail-modal', ['campos' => ['nombre' => 'Nombre', 'direccion' => 'Dirección', 'zona' => 'Zona', 'ciudad' => 'Ciudad', 'telefono' => 'Teléfono', 'latitud' => 'Latitud', 'longitud' => 'Longitud', 'asociados' => 'Almacenes asociados']])
</x-admin-shell>
