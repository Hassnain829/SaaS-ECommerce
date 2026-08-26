@props([
    'content' => null,
])

@php
    $html = \App\Support\Catalog\ProductRichText::toSafeHtml(is_string($content) || $content === null ? $content : (string) $content);
@endphp

@if ($html !== '')
    <div {{ $attributes->class([
        'product-rich-text max-w-none text-sm leading-relaxed text-ink-secondary',
        '[&_a]:font-semibold [&_a]:text-brand [&_a]:underline',
        '[&_p]:mb-3 [&_p:last-child]:mb-0',
        '[&_ul]:mb-3 [&_ul]:list-disc [&_ul]:pl-5',
        '[&_ol]:mb-3 [&_ol]:list-decimal [&_ol]:pl-5',
        '[&_li]:mb-1',
        '[&_h1]:mb-2 [&_h1]:text-lg [&_h1]:font-semibold [&_h1]:text-ink',
        '[&_h2]:mb-2 [&_h2]:text-base [&_h2]:font-semibold [&_h2]:text-ink',
        '[&_h3]:mb-2 [&_h3]:text-sm [&_h3]:font-semibold [&_h3]:text-ink',
        '[&_strong]:font-semibold [&_b]:font-semibold',
        '[&_blockquote]:border-l-2 [&_blockquote]:border-border [&_blockquote]:pl-3 [&_blockquote]:italic',
        '[&_table]:my-3 [&_table]:w-full [&_table]:border-collapse [&_th]:border [&_th]:border-border [&_th]:px-2 [&_th]:py-1 [&_td]:border [&_td]:border-border [&_td]:px-2 [&_td]:py-1',
        '[&_img]:my-3 [&_img]:max-h-64 [&_img]:rounded-md',
    ]) }}>
        {!! $html !!}
    </div>
@endif
