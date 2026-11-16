@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => true, 'autocomplete' => null, 'help' => null])

<div class="mb-3">
    <label for="{{ $name }}" class="form-label">{{ $label }}@if($required) <span class="text-danger" aria-hidden="true">*</span>@endif</label>
    <input
        id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        @if($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if($required) required @endif
        @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        class="form-control @error($name) is-invalid @enderror"
        aria-describedby="{{ $name }}-feedback"
        {{ $attributes }}>
    @if($help)<div class="form-text">{{ $help }}</div>@endif
    @error($name)<div id="{{ $name }}-feedback" class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>