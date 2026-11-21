<details class="size-guide">
    <summary>Size guide</summary>
    <div class="size-guide-content">
    @if($measurementGuide)
        <div data-measurements data-original-unit="{{ $measurementGuide['unit'] }}">
            <h3>{{ $measurementGuide['basis']==='body' ? 'Body measurements' : 'Garment measurements' }}</h3>
            <p>Original values are in {{ $measurementGuide['unit'] }}. Each measurement states whether it is a full circumference, flat width or length.</p>
            <label data-unit-control hidden>Display unit <select data-guide-unit aria-label="Size guide display unit"><option value="cm" @selected($measurementGuide['unit']==='cm')>cm</option><option value="in" @selected($measurementGuide['unit']==='in')>in</option></select></label>
            @php($guideSizes=collect($measurementGuide['measurements'])->flatMap(fn($m)=>array_column($m['values'],'size'))->unique()->values())
            <div class="measurement-scroll" tabindex="0" role="region" aria-label="Size measurements; scroll horizontally for more sizes">
            <table><caption>Measurements (<span data-unit-label>{{ $measurementGuide['unit'] }}</span>)</caption><thead><tr><th scope="col">Measurement</th>@foreach($guideSizes as $size)<th scope="col">{{ $size }}</th>@endforeach</tr></thead>
            <tbody>@foreach($measurementGuide['measurements'] as $measurement)<tr><th scope="row">{{ $measurement['label'] }}<small>{{ ['circumference'=>'Full circumference','flat_width'=>'Flat width (not circumference)','length'=>'Length'][$measurement['method']] ?? $measurement['method'] }}</small></th>
            @foreach($guideSizes as $size)@php($entry=collect($measurement['values'])->firstWhere('size',$size))<td>@if($entry)<span data-measure-value="{{ $entry['value'] }}">{{ $entry['value'] }}</span>@else<span aria-label="Not supplied">—</span>@endif</td>@endforeach</tr>@endforeach</tbody></table>
            </div>
            @if(filled($measurementGuide['note']??null))<h4>How to measure</h4><p class="preserve-lines">{{ $measurementGuide['note'] }}</p>@endif
        </div>
    @endif
    @foreach($guides as $guide)<a href="{{ $guide['original'] }}" target="_blank" rel="noopener" aria-label="Open larger size guide {{ $loop->iteration }}"><img src="{{ $guide['src'] }}" @if($guide['srcset']) srcset="{{ $guide['srcset'] }}" @endif sizes="(min-width:900px) 40vw, 100vw" width="{{ $guide['width'] }}" height="{{ $guide['height'] }}" alt="{{ $guide['alt'] }}" loading="lazy"></a>@endforeach
    </div>
</details>
