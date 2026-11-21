<div>
    <p>Guidance from saved data. Stock and price actions refresh this summary immediately. This does not change publication.</p>
    @if(!$findings)<p>Save this product to check its storefront readiness.</p>
    @else
        <p>{{ $findings[0]['text'] }}</p>
        <p><button type="button" wire:click="mountAction('manageStock')">Enter stock</button> ? <button type="button" wire:click="mountAction('managePrices')">Set PKR price</button></p>
        @foreach($findings as $finding)
            @if($finding['kind'] === 'Color summary')<p>{{ $finding['text'] }}. <a href="#product-colors">Open color/size rows below for PKR price and Inventory → Adjust stock.</a></p>@endif
        @endforeach
        <details><summary>{{ count($findings) - 1 }} catalog findings — configuration and normal stock states</summary>
        <ul style="list-style:disc;padding-left:1.5rem">
        @foreach(array_slice($findings,1) as $finding)<li><strong>{{ $finding['kind'] }}:</strong> {{ $finding['text'] }} @if(isset($finding['action']))<button type="button" wire:click="mountAction('{{ $finding['action'] }}', { variant: {{ $finding['variant'] }} })">{{ $finding['label'] }}</button>@endif</li>@endforeach
        </ul></details>
    @endif
</div>
