@aware(['count' => 0])

@props([
    'href' => null,
    'icon' => null,
    'label' => null,
    'color' => 'text-gray-700 hover:bg-gray-50 hover:text-sp-primary',
])

@if($icon && $count > 0 && $count <= 2)
    @if($href)
        <a
            href="{{ $href }}"
            title="{{ $label }}"
            {{ $attributes->merge(['class' => 'inline-flex items-center justify-center w-7 h-7 rounded-md text-sp-navy border border-gray-200 bg-white hover:bg-sp-primary/10 hover:border-sp-primary/30 hover:text-sp-primary transition-colors']) }}
        >
            <i class="bi {{ $icon }}"></i>
        </a>
    @else
        <button
            type="button"
            title="{{ $label }}"
            {{ $attributes->merge(['class' => 'inline-flex items-center justify-center w-7 h-7 rounded-md text-sp-navy border border-gray-200 bg-white hover:bg-sp-primary/10 hover:border-sp-primary/30 hover:text-sp-primary transition-colors']) }}
        >
            <i class="bi {{ $icon }}"></i>
        </button>
    @endif
@else
    @if($href)
        <a href="{{ $href }}" {{ $attributes->merge(['class' => 'flex items-center gap-2 px-3 py-2 text-sm font-medium ' . $color]) }}>
            @if($icon)<i class="bi {{ $icon }} w-4 text-center"></i>@endif
            {{ $label ?? $slot }}
        </a>
    @else
        <button type="button" {{ $attributes->merge(['class' => 'w-full flex items-center gap-2 px-3 py-2 text-sm font-medium ' . $color]) }}>
            @if($icon)<i class="bi {{ $icon }} w-4 text-center"></i>@endif
            {{ $label ?? $slot }}
        </button>
    @endif
@endif
