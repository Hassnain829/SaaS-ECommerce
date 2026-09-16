@props(['step' => 1])

@php
    $steps = [
        1 => ['label' => 'Ship from', 'route' => 'settings.delivery.setup.ship-from'],
        2 => ['label' => 'Deliver to', 'route' => 'settings.delivery.setup.deliver-to'],
        3 => ['label' => 'Checkout shipping', 'route' => 'settings.delivery.setup.delivery-option'],
        4 => ['label' => 'Review', 'route' => 'settings.delivery.setup.review'],
    ];
    $wizardIcons = [
        1 => 'store',
        2 => 'pin',
        3 => 'cart',
        4 => 'check',
    ];
    $originName = ($selectedLocation ?? null)?->name
        ?? ($wizardOriginName ?? null);
    $areaName = ($selectedZone ?? null)?->name
        ?? ($wizardAreaName ?? null);
    $optionCount = (int) ($wizardOptionCount ?? 0);
    $optionValue = ($selectedMethod ?? null)?->name
        ?? ($optionCount > 0
            ? $optionCount.' checkout '.($optionCount === 1 ? 'option' : 'options')
            : null);
    $wizardValues = [
        1 => $originName ?: 'Where orders ship from',
        2 => $areaName ?: 'Choose your coverage',
        3 => $optionValue ?: 'Fixed, free, or FedEx',
        4 => ! empty($deliverySetup['is_ready']) ? 'Ready to finish' : 'Confirm and finish',
    ];
    $wizardStages = [];
    foreach ($steps as $number => $meta) {
        $active = $number === $step;
        $complete = $number < $step;
        $status = $complete ? 'ready' : ($active ? 'warning' : 'muted');
        $wizardStages[] = [
            'id' => 'wizard-'.$number,
            'label' => $meta['label'],
            'value' => $wizardValues[$number],
            'status' => $status,
            'statusLabel' => $complete ? 'Saved — edit' : ($active ? 'Current step' : 'Waiting'),
            'icon' => $wizardIcons[$number],
            'optional' => false,
            'current' => $active,
            'asLink' => $complete,
            'asStatic' => ! $complete,
            'href' => $complete ? route($meta['route']) : '',
        ];
    }
@endphp

<nav aria-label="Delivery setup progress" class="delivery-ops dh-setup-progress">
    @include('user_view.shipping.partials.delivery_route_flow', [
        'stages' => $wizardStages,
        'detailRegionId' => 'wizard-route-detail',
        'showDetail' => false,
    ])
</nav>
