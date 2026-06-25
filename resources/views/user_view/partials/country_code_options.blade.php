@php
    $countryCodes = [
        'US' => 'United States (US)',
        'CA' => 'Canada (CA)',
        'GB' => 'United Kingdom (GB)',
        'AU' => 'Australia (AU)',
        'DE' => 'Germany (DE)',
        'FR' => 'France (FR)',
        'PK' => 'Pakistan (PK)',
        'IN' => 'India (IN)',
        'MX' => 'Mexico (MX)',
    ];
@endphp
@foreach ($countryCodes as $code => $label)
    <option value="{{ $code }}">{{ $label }}</option>
@endforeach
