@props([
    'label',
    'name',
    'required' => false,
    'createUrl' => null,
    'createLabel' => 'جديد',
    'canCreate' => true,
    'help' => null,
    'wrapperClass' => '',
])

<div class="form-group reference-picker-field {{ $wrapperClass }}">
    <label @class(['required' => $required])>{{ $label }}</label>
    <div class="reference-picker-control">
        <select {{ $attributes->merge(['class' => 'select', 'name' => $name, 'data-smart-select' => '1']) }} @required($required)>
            {{ $slot }}
        </select>
        @if($createUrl && $canCreate)
            <a class="btn btn-sm btn-outline reference-picker-add" href="{{ $createUrl }}" target="_blank" rel="noopener">+ {{ $createLabel }}</a>
        @endif
    </div>
    @if($help)<div class="help">{{ $help }}</div>@endif
</div>
