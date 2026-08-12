@aware(['count' => 0])

@props([
    'action' => null,
    'method' => 'DELETE',
    'icon' => null,
    'label' => null,
    'color' => 'text-gray-700 hover:bg-gray-50 hover:text-red-600',
    'confirm' => null,
])

@if($icon && $count > 0 && $count <= 2)
    <form action="{{ $action }}" method="POST" class="inline">
        @csrf
        @if(strtoupper($method) !== 'POST')
            @method($method)
        @endif
        <button
            type="submit"
            title="{{ $label }}"
            data-confirm="{{ $confirm }}"
            onclick="return !this.dataset.confirm || confirm(this.dataset.confirm)"
            {{ $attributes->merge(['class' => 'inline-flex items-center justify-center w-7 h-7 rounded-md text-sp-navy border border-gray-200 bg-white hover:bg-red-50 hover:border-red-300 hover:text-red-600 transition-colors']) }}
        >
            <i class="bi {{ $icon }}"></i>
        </button>
    </form>
@else
    <form action="{{ $action }}" method="POST" class="block">
        @csrf
        @if(strtoupper($method) !== 'POST')
            @method($method)
        @endif
        <button
            type="submit"
            data-confirm="{{ $confirm }}"
            onclick="return !this.dataset.confirm || confirm(this.dataset.confirm)"
            {{ $attributes->merge(['class' => 'w-full flex items-center gap-2 px-3 py-2 text-sm font-medium ' . $color]) }}
        >
            @if($icon)<i class="bi {{ $icon }} w-4 text-center"></i>@endif
            {{ $label ?? $slot }}
        </button>
    </form>
@endif
