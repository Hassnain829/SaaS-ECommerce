@php
    $catalogToolsReturn = $catalogToolsReturn ?? null;
@endphp
@if (is_string($catalogToolsReturn) && $catalogToolsReturn !== '')
    <input type="hidden" name="_catalog_return" value="{{ $catalogToolsReturn }}">
@endif
