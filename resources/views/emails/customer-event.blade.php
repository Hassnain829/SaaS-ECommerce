# {{ $notification->title }}

{{ $notification->body }}

@if ($actionUrl)
@component('mail::button', ['url' => $actionUrl])
{{ $actionLabel }}
@endcomponent
@endif

@if ($storeName)
— {{ $storeName }}
@endif
