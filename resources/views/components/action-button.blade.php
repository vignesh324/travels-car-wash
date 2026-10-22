@props([
    'action',
    'label' => null,
    'ariaLabel' => null,
    'icon' => null,
    'variant' => null,
    'size' => 'sm',
])

@php
    $actionType = last(explode('.', $action));
    $definitionKey = str_starts_with($actionType, 'add-')
        ? 'add'
        : (str_ends_with($actionType, '-edit') ? 'edit' : (str_ends_with($actionType, '-view') ? 'view' : $actionType));
    $definitions = [
        'add' => ['label' => 'Add', 'icon' => 'bi-plus-lg', 'variant' => 'primary'],
        'view' => ['label' => 'View', 'icon' => 'bi-eye', 'variant' => 'outline-secondary'],
        'edit' => ['label' => 'Edit', 'icon' => 'bi-pencil', 'variant' => 'outline-secondary'],
        'delete' => ['label' => 'Delete', 'icon' => 'bi-trash', 'variant' => 'outline-danger'],
        'statement' => ['label' => 'Statement', 'icon' => 'bi-file-earmark-text', 'variant' => 'outline-success'],
        'pdf' => ['label' => 'PDF', 'icon' => 'bi-filetype-pdf', 'variant' => 'outline-secondary'],
        'cancel' => ['label' => 'Cancel', 'icon' => 'bi-x-circle', 'variant' => 'outline-danger'],
        'receipt' => ['label' => 'Receipt', 'icon' => 'bi-receipt', 'variant' => 'outline-secondary'],
        'reverse' => ['label' => 'Reverse', 'icon' => 'bi-arrow-counterclockwise', 'variant' => 'outline-danger'],
        'download' => ['label' => 'Download', 'icon' => 'bi-download', 'variant' => 'outline-secondary'],
        'print' => ['label' => 'Print', 'icon' => 'bi-printer', 'variant' => 'outline-secondary'],
    ];
    $definition = $definitions[$definitionKey] ?? ['label' => ucfirst($actionType), 'icon' => 'bi-gear', 'variant' => 'outline-secondary'];
    $buttonLabel = $label ?? $definition['label'];
    $buttonIcon = $icon ?? $definition['icon'];
    $buttonVariant = $variant ?? $definition['variant'];
    $buttonClasses = 'btn btn-' . $buttonVariant . ($size ? ' btn-' . $size : '') . ' d-inline-flex align-items-center justify-content-center gap-1 text-nowrap';
@endphp

<button
    data-action="{{ $action }}"
    aria-label="{{ $ariaLabel ?? $buttonLabel }}"
    title="{{ $buttonLabel }}"
    {{ $attributes->merge(['type' => 'button', 'class' => $buttonClasses]) }}
>
    <i class="bi {{ $buttonIcon }}" aria-hidden="true"></i>
    <span>{{ $buttonLabel }}</span>
</button>
