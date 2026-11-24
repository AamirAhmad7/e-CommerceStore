<div class="print-brand">
@if(\App\Support\StoreSettings::printLogoUrl())
<img src="{{ \App\Support\StoreSettings::printLogoUrl() }}" alt="{{ $brand }} logo" width="64" height="64">
@else
<svg viewBox="0 0 64 64" width="64" height="64" role="img" aria-label="Sample SF monogram"><rect x="2" y="2" width="60" height="60" rx="10" fill="white" stroke="#111" stroke-width="2"/><text x="32" y="42" text-anchor="middle" font-family="Georgia, serif" font-size="30" font-weight="bold" fill="#111">SF</text></svg>
@endif
<h1>{{ $brand }}</h1>
</div>
