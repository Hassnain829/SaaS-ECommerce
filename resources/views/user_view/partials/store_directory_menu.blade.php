@unless ($isActive)
    <button
        type="button"
        class="sd-menu-item"
        role="menuitem"
        data-store-switch-request="1"
        data-store-id="{{ $store->id }}"
        data-store-name="{{ $store->name }}"
        @click="openMenu = null"
    >Set as current</button>
@endunless
@if ($canManageCatalog)
    <a href="{{ route('store.add-product', ['storeId' => $store->id]) }}" class="sd-menu-item" role="menuitem">Add product</a>
@endif
<a href="{{ route('store.products', ['storeId' => $store->id]) }}" class="sd-menu-item" role="menuitem">Catalog</a>
@if ($canManageSettings)
    <button type="button" class="js-open-edit-store-modal sd-menu-item" data-store='@json($storeActionPayload)' role="menuitem" @click="openMenu = null">Edit store</button>
@endif
@if ($canClose)
    <button type="button" class="js-open-edit-store-modal sd-menu-item is-danger" data-store='@json($storeActionPayload)' data-close-store="1" role="menuitem" @click="openMenu = null">Close store</button>
@endif
