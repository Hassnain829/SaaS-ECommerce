@props([
    'label' => 'Sign out',
])

<form method="POST" action="{{ route('logout') }}" {{ $attributes->merge(['class' => 'mt-1.5', 'data-turbo' => 'false']) }}>
    @csrf
    <button type="submit" class="sidebar-footer-logout w-full">
        {{ $label }}
    </button>
</form>
