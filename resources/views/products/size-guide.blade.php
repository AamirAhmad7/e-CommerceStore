<details class="size-guide">
    <summary>Size guide</summary>
    <div class="size-guide-content">
    @if($measurementGuide)
        @php($measurements = collect($measurementGuide['measurements'] ?? [])->map(function ($row) { $row['values'] = array_values(array_filter($row['values'] ?? [], fn ($entry) => filled($entry['size'] ?? null) && is_numeric($entry['value'] ?? null) && $entry['value'] > 0)); return $row; })->filter(fn ($row) => count($row['values'])))
        <div data-measurements data-original-unit="{{ $measurementGuide['unit'] }}">
            <h3>{{ ['body' => 'Body measurements', 'garment' => 'Finished jacket measurements'][$measurementGuide['basis'] ?? ''] ?? 'Size measurements' }}</h3>
            <p>Compare the measurements below with your body or a jacket that fits, as indicated. Original values are in {{ $measurementGuide['unit'] }}.</p>
            <label data-unit-control hidden>Display unit <select data-guide-unit aria-label="Size guide display unit"><option value="cm" @selected($measurementGuide['unit']==='cm')>Centimeters (cm)</option><option value="in" @selected($measurementGuide['unit']==='in')>Inches (in)</option></select></label>
            @php($guideSizes = $measurements->flatMap(fn ($row) => array_column($row['values'], 'size'))->unique()->values())
            <div class="measurement-scroll" tabindex="0" role="region" aria-label="Size measurements; scroll horizontally for more sizes">
                <table><caption>Measurements (<span data-unit-label>{{ $measurementGuide['unit'] }}</span>). Highlighted column is your selected size.</caption>
                    <thead><tr><th scope="col">Measurement</th>@foreach($guideSizes as $size)<th scope="col" data-guide-size="{{ \App\Support\ProductPresentation::sizeLabel($size) }}" @if($selectedVariant && \App\Support\ProductPresentation::sizeLabel($selectedVariant['size']) === \App\Support\ProductPresentation::sizeLabel($size)) aria-current="true" @endif>{{ $size }}@php($ageRange = $measurements->flatMap(fn ($row) => $row['values'])->first(fn ($entry) => $entry['size'] === $size && filled($entry['age_range'] ?? null))['age_range'] ?? null)@if($ageRange)<small>{{ $ageRange }}</small>@endif</th>@endforeach</tr></thead>
                    <tbody>@foreach($measurements as $measurement)<tr>
                        <th scope="row">{{ $measurement['label'] }}
                            @if($method = \App\Support\SizeFit::method($measurement, $measurementGuide['basis'] ?? null))<small>{{ $method }}</small>@endif
                            @if($instruction = \App\Support\SizeFit::instruction($measurement, $measurementGuide['basis'] ?? null))<small class="measurement-instruction">{{ $instruction }}</small>@endif
                        </th>
                        @foreach($guideSizes as $size)@php($entry = collect($measurement['values'])->firstWhere('size', $size))
                            <td data-guide-size="{{ \App\Support\ProductPresentation::sizeLabel($size) }}" @if($selectedVariant && \App\Support\ProductPresentation::sizeLabel($selectedVariant['size']) === \App\Support\ProductPresentation::sizeLabel($size)) aria-current="true" @endif>
                                @if($entry)<span data-measure-value="{{ $entry['value'] }}">{{ $entry['value'] }}</span>@else<span aria-label="Not supplied">&mdash;</span>@endif
                            </td>
                        @endforeach
                    </tr>@endforeach</tbody>
                </table>
            </div>
            @if(filled($measurementGuide['note'] ?? null))<h4>How to measure</h4><p class="preserve-lines">{{ $measurementGuide['note'] }}</p>@endif
        </div>
    @endif
    @foreach($guides as $guide)<a href="{{ $guide['original'] }}" target="_blank" rel="noopener" aria-label="Open larger size guide {{ $loop->iteration }}"><img src="{{ $guide['src'] }}" @if($guide['srcset']) srcset="{{ $guide['srcset'] }}" @endif sizes="(min-width:900px) 40vw, 100vw" width="{{ $guide['width'] }}" height="{{ $guide['height'] }}" alt="{{ $guide['alt'] }}" loading="lazy"></a>@endforeach
    </div>
</details>
