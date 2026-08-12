@props([
    'label' => null,
    'value' => null,
    'icon' => null,
    'color' => 'bg-sp-primary',
    'link' => null,
])

@if($link)
    <a href="{{ $link }}" {{ $attributes->merge(['class' => 'bg-white rounded-lg border border-gray-200 shadow-sm p-4 flex items-center gap-3 hover:bg-sp-primary/5 hover:border-sp-primary/30 transition-colors']) }}>
@else
    <div {{ $attributes->merge(['class' => 'bg-white rounded-lg border border-gray-200 shadow-sm p-4 flex items-center gap-3']) }}>
@endif
        <div class="w-11 h-11 rounded-lg {{ $color }} text-white flex items-center justify-center flex-shrink-0 shadow-sm">
            <i class="bi {{ $icon }} text-xl"></i>
        </div>
        <div class="min-w-0">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide truncate">{{ $label }}</div>
            <div class="text-2xl font-extrabold text-sp-navy leading-tight truncate">{{ $value }}</div>
        </div>
@if($link)
    </a>
@else
    </div>
@endif