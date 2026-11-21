@if(!$preview && $colors->contains(fn($color)=>collect($color['variants'])->contains('can_notify',true)))
@php($alertVariants=collect($selected['variants'] ?? [])->where('can_notify',true))
<details id="restock-disclosure" @if($alertVariants->isEmpty()) hidden @endif @if(old('email')) open @endif>
<summary>Notify me when available</summary>
<form action="{{ route('restock.store') }}" method="post" id="restock-form" data-product-name="{{ $product->name }}">@csrf
<label for="restock-variant">Sold-out size in <span id="restock-color">{{ $selected['name'] ?? '' }}</span></label>
<select id="restock-variant" name="variant_id" aria-describedby="restock-label" required>@foreach($alertVariants as $variant)<option value="{{ $variant['id'] }}" @selected((string)old('variant_id')===(string)$variant['id'] || request('size')===$variant['size'])>{{ $variant['label'] }}</option>@endforeach</select>
<p id="restock-label">Notify me for {{ $product->name }} / {{ $selected['name'] ?? '' }} / <span id="restock-size">{{ $alertVariants->firstWhere('id',(int)old('variant_id'))['label'] ?? $alertVariants->firstWhere('size',request('size'))['label'] ?? $alertVariants->first()['label'] ?? '' }}</span>.</p>
<label for="restock-email">Email address</label><input id="restock-email" name="email" type="email" aria-describedby="restock-help @error('email') restock-email-error @enderror" @error('email') aria-invalid="true" @enderror autocomplete="email" maxlength="254" value="{{ old('email') }}" required>
@error('email')<span class="field-error" id="restock-email-error">{{ $message }}</span>@enderror
<p id="restock-help">One alert for this size. Confirm your email first. No marketing or stock reservation.</p>
<button class="button">Email me a confirmation link</button>
</form></details>
@endif
