@php
    $catalogToolsReturn = $catalogToolsReturn ?? null;
    $catalogToolsReturnProductId = $catalogToolsReturnProductId ?? null;
@endphp
@if (is_string($catalogToolsReturn) && $catalogToolsReturn !== '')
    <input type="hidden" name="_catalog_return" value="{{ $catalogToolsReturn }}">
@endif
@if ($catalogToolsReturn === 'products.edit' && filled($catalogToolsReturnProductId))
    <input type="hidden" name="_catalog_return_product_id" value="{{ (int) $catalogToolsReturnProductId }}">
@endif
