@php($photos = $color['photos'] ?? [])
@if(count($photos))
    @php($first = $photos[0])
    <figure class="gallery-main">
        <a class="large-photo" href="{{ $first['original'] }}" target="_blank" rel="noopener" aria-label="Open larger image of {{ $color['name'] }} in a new tab">
            <img class="main-photo" src="{{ $first['src'] }}" @if($first['srcset']) srcset="{{ $first['srcset'] }}" @endif sizes="(min-width: 900px) 55vw, 100vw" width="{{ $first['width'] }}" height="{{ $first['height'] }}" alt="{{ $first['alt'] }}" fetchpriority="high" loading="eager">
        </a>
        <figcaption><span class="photo-count">1 / {{ count($photos) }}</span><span>Open image for a closer look</span></figcaption>
    </figure>
    <nav class="thumbnails" aria-label="{{ $color['name'] }} photos">
        @foreach($photos as $photo)
            <a href="{{ $photo['original'] }}" target="_blank" rel="noopener" data-photo="{{ $loop->index }}" aria-label="View {{ $photo['purpose'] ?? $color['name'].' photo '.$loop->iteration }}{{ $photo['alt'] ? ': '.$photo['alt'] : '' }}" @if($loop->first) aria-current="true" @endif>
                @if($photo['thumbnail'])
                    <img src="{{ $photo['thumbnail'] }}" width="{{ $photo['width'] }}" height="{{ $photo['height'] }}" alt="{{ $photo['alt'] }}" loading="lazy" decoding="async">
                @else
                    <span>Photo {{ $loop->iteration }}</span>
                @endif
            </a>
        @endforeach
    </nav>
@else
    <div class="gallery-empty"><h2>No {{ $color['name'] ?? 'product' }} photos yet</h2><p>Photos for this color have not been added.</p></div>
@endif
