<div class="sf-gallery-navigation">
    <p>Each color has one ordered gallery shared by all its sizes. The first photo is its cover. Gallery uploads and ordering are staged until Save changes; alt-text and photo-use dialogs state when they save.</p>
    @foreach($colors as $color)
        <a href="#color-{{ $color->id }}-photos" x-on:click.prevent="const target = document.getElementById('color-{{ $color->id }}-photos'); if (target) { for (let node = target; node; node = node.parentElement) node.dispatchEvent(new CustomEvent('expand')); $nextTick(() => { target.scrollIntoView({block:'center'}); target.querySelector('button, input')?.focus({preventScroll:true}); }); }">{{ $color->name }} gallery</a>
    @endforeach
    <p>To move an existing shared image, use Product tools &rarr; Move shared photo. This saves the product first.</p>
</div>
