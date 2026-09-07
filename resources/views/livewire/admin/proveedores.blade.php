<x-admin-shell active="proveedores">
    @include('livewire.admin.partials.operacion-listado', ['modulo' => 'proveedores', 'titulo' => 'Proveedores', 'singular' => 'proveedor', 'nombreCampo' => 'empresa'])
    @include('livewire.admin.partials.proveedor-form-modal')
    @include('livewire.admin.partials.operacion-detail-modal', ['campos' => ['empresa' => 'Empresa', 'nombre_contacto' => 'Contacto', 'telefono' => 'Teléfono', 'email' => 'Correo electrónico', 'direccion' => 'Dirección', 'nit' => 'NIT', 'asociados' => 'Lotes asociados']])
</x-admin-shell>
