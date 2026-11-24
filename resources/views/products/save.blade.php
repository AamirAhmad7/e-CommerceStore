@unless($preview ?? false)
<form class="jacket-save" action="{{ route('saved.update', $saveProduct->slug) }}" method="post">
@csrf
@if(in_array($saveProduct->id, session('saved_jackets', [])))
<input type="hidden" name="action" value="remove"><button class="button-secondary" aria-label="Remove {{ $saveProduct->name }} from wishlist">&#9829; Saved � Remove</button>
@else
<input type="hidden" name="action" value="save"><button class="button-secondary" aria-label="Save {{ $saveProduct->name }} to wishlist">&#9825; Save</button>
@endif
</form>
@endunless
