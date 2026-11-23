@auth
<div class="admin-price-view" x-data="{ view: 'Both', key: 'sf-price-view-{{ auth()->id() }}', apply() { document.documentElement.dataset.priceView = this.view; try { localStorage.setItem(this.key, this.view); } catch {} } }" x-init="try { view = localStorage.getItem(key) || 'Both'; } catch {} apply()" x-on:form-validation-error.window="view = 'Both'; apply()">
<label>Price view <select x-model="view" x-on:change="apply()" aria-label="Admin price viewing preference"><option>Both</option><option>PKR</option><option>USD</option></select></label>
</div>
@endauth
