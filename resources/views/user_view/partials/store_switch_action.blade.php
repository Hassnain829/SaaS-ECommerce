@if ($isActive ?? false)
    <a href="{{ $href ?? route('dashboard') }}" class="{{ $class ?? 'sd-action' }}">{{ $label ?? 'Open workspace →' }}</a>
@else
    <button
        type="button"
        class="{{ $class ?? 'sd-action' }}"
        data-store-switch-request="1"
        data-store-id="{{ $store->id }}"
        data-store-name="{{ $store->name }}"
        data-redirect-to="{{ $redirectTo ?? 'dashboard' }}"
        @isset($orderId)
            data-order-id="{{ $orderId }}"
        @endisset
    >{{ $label ?? 'Open workspace →' }}</button>
@endif
