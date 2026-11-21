<div>
    @include('filament.assisted-sale-preview', ['lines' => $link->snapshot, 'subtotal' => $link->subtotal_minor, 'totals' => null, 'error' => null])
    <p>Expires: {{ $link->expires_at->timezone(config('app.timezone'))->format('Y-m-d H:i T') }}</p>
    @foreach(['checkout_link' => ['Copy checkout link', $link->url()], 'message' => ['Copy message', $message]] as $key => [$label, $text])
        <div x-data="{ feedback: '' }" style="margin:1rem 0">
            <label>{{ $label }}<textarea x-ref="copyText" readonly rows="3" style="width:100%;border:1px solid;padding:.5rem">{{ $text }}</textarea></label>
            <button type="button" style="text-decoration:underline" x-on:click="try { await navigator.clipboard.writeText($refs.copyText.value); feedback = 'Copied'; } catch (error) { $refs.copyText.focus(); $refs.copyText.select(); feedback = 'Select and copy the text above.'; }">{{ $label }}</button>
            <span role="status" x-text="feedback"></span>
        </div>
    @endforeach
    <p>You choose where to send this. No WhatsApp message or email has been sent.</p>
</div>
