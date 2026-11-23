<dialog id="quick-add" class="quick-add-dialog" aria-labelledby="quick-add-title">
    <div class="quick-add-heading"><h2 id="quick-add-title">Choose your size</h2><button type="button" class="text-button" data-quick-close aria-label="Close quick add">Close</button></div>
    <p data-quick-status role="status" aria-live="polite"></p>
    <form method="post" action="{{ route('cart.add') }}" data-quick-form hidden>@csrf
        <input type="hidden" name="product_id"><input type="hidden" name="variant_id"><input type="hidden" name="currency"><input type="hidden" name="expected_price">
        <label>Color<select data-quick-colors></select></label>
        <fieldset><legend>Size</legend><div class="sizes" data-quick-sizes></div></fieldset>
        <p class="card-price" data-quick-price></p><p>Offers are calculated in your cart and checkout.</p>
        <label>Quantity<input name="quantity" type="number" value="1" min="1" max="100" required></label>
        <button class="button" type="submit" data-quick-submit disabled>Add to cart</button>
    </form>
    <p><a class="text-button" href="{{ route('cart.show') }}" data-quick-cart hidden>View cart</a> <a class="text-button" data-quick-product>Full product details &amp; size guide</a></p>
</dialog>
