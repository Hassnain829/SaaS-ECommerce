@php
    use App\Support\Tax\TaxCountryCatalog;
@endphp
@foreach (TaxCountryCatalog::all() as $code => $label)
    <option value="{{ $code }}">{{ $label }} ({{ $code }})</option>
@endforeach
