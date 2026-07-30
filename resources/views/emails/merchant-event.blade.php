# {{ $storeName ?? 'Your store' }}

{{ $notification->body }}

@if ($actionUrl)
@component('mail::button', ['url' => $actionUrl])
{{ $actionLabel }}
@endcomponent
@endif

Thanks,<br>
{{ config('app.name') }}
