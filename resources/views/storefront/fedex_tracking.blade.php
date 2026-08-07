<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Track shipment · {{ $store->name }}</title>
    <style>
        :root { color-scheme: light; --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --bg:#f8fafc; }
        body { margin:0; font-family: Georgia, "Times New Roman", serif; background:linear-gradient(180deg,#eef6ff,#f8fafc 40%,#fff); color:var(--ink); }
        main { max-width: 42rem; margin: 0 auto; padding: 2.5rem 1.25rem 4rem; }
        h1 { font-size: 1.75rem; margin: 0 0 .35rem; letter-spacing: -.02em; }
        .brand { font-size: .8rem; text-transform: uppercase; letter-spacing: .14em; color: var(--muted); margin-bottom: .75rem; }
        .card { margin-top: 1.5rem; border: 1px solid var(--line); background: rgba(255,255,255,.9); padding: 1.25rem 1.35rem; }
        .meta { color: var(--muted); font-size: .95rem; line-height: 1.5; }
        .status { font-size: 1.15rem; margin-top: .75rem; }
        ol { margin: 1rem 0 0; padding-left: 1.1rem; }
        li { margin: .55rem 0; }
        .when { color: var(--muted); font-size: .85rem; }
    </style>
</head>
<body>
<main>
    <div class="brand">{{ $store->name }}</div>
    <h1>Shipment tracking</h1>
    <p class="meta">Tracking number ending in {{ $trackingNumber ? substr($trackingNumber, -4) : '----' }}</p>

    <div class="card">
        <div class="status">{{ $statusText ?: 'Status updating' }}</div>
        @if ($estimatedDelivery)
            <p class="meta">Estimated delivery: {{ $estimatedDelivery }}</p>
        @endif
        @if ($deliveredAt)
            <p class="meta">Delivered: {{ is_string($deliveredAt) ? $deliveredAt : $deliveredAt }}</p>
        @endif
        @if ($exception)
            <p class="meta">Note: {{ $exception }}</p>
        @endif

        @if (count($timeline))
            <ol>
                @foreach ($timeline as $event)
                    <li>
                        <div>{{ $event['description'] ?? 'Update' }}</div>
                        <div class="when">
                            {{ $event['occurred_at'] ?? '' }}
                            @if (!empty($event['city'])) · {{ $event['city'] }}{{ !empty($event['state']) ? ', '.$event['state'] : '' }} @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @else
            <p class="meta" style="margin-top:1rem;">Tracking details will appear after the carrier posts scan events.</p>
        @endif
    </div>
</main>
</body>
</html>
