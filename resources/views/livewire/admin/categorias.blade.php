<x-admin-shell active="categorias">
    @include('livewire.admin.partials.catalogo-listado', ['titulo' => 'Categorías', 'singular' => 'categoría', 'icono' => '🧩'])
    @include('livewire.admin.partials.categoria-form-modal')
    @include('livewire.admin.partials.categoria-detail-modal')
</x-admin-shell>
