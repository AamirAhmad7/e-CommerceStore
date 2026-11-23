<?php

namespace App\Http\Controllers;

use App\Services\CommerceSettings;
use App\Services\InternationalInquiry;
use App\Services\Pricing;
use App\Support\StoreSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InquiryController extends Controller
{
    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate(['variant_id' => 'required|integer', 'product_id' => 'required|integer', 'quantity' => 'required|integer|min:1|max:100']);
        $items = $request->session()->get('inquiry_items', []);
        $items[$data['variant_id']] = $data['quantity'];
        $quote = InternationalInquiry::quote([$data['variant_id'] => $data['quantity']]);
        if (! $quote['valid'] || $quote['lines'][0]['product']->id !== (int) $data['product_id'] || count($items) > 20) {
            throw ValidationException::withMessages(['inquiry' => 'This exact size is unavailable, its USD price is missing, or the inquiry exceeds 20 sizes. Review your selection.']);
        }
        $request->session()->put(['inquiry_items' => $items, 'delivery_market' => 'international', 'currency' => 'USD']);

        return redirect()->route('inquiry.show');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['destination' => 'nullable|string|max:80', 'quantities' => 'nullable|array|max:20', 'quantities.*' => 'required|integer|min:0|max:100']);
        $items = $request->session()->get('inquiry_items', []);
        foreach ($data['quantities'] ?? [] as $id => $quantity) {
            if (array_key_exists($id, $items)) {
                if ((int) $quantity === 0) {
                    unset($items[$id]);
                } else {
                    $items[$id] = (int) $quantity;
                }
            }
        }
        $request->session()->put(['inquiry_items' => $items, 'inquiry_destination' => trim($data['destination'] ?? '')]);

        return redirect()->route('inquiry.show');
    }

    public function show(Request $request): View
    {
        $quote = InternationalInquiry::quote($request->session()->get('inquiry_items', []));
        $destination = $request->session()->get('inquiry_destination', '');
        $shipping = InternationalInquiry::shipping($quote['subtotal'], array_sum($request->session()->get('inquiry_items', [])), $destination, CommerceSettings::get());
        $message = 'International jacket inquiry - destination: '.$destination."\n";
        foreach ($quote['lines'] as $line) {
            $message .= ($line['product']?->name ?? 'Unavailable product').' / '.($line['variant']?->productColor->name ?? '').' / '.($line['variant']?->size ?? '').' / SKU '.($line['variant']?->sku ?? '').' x '.$line['quantity'].' at '.Pricing::format($line['price'], 'USD')." each\n";
        }
        $message .= 'Merchandise: '.Pricing::format($quote['subtotal'], 'USD')."\n".$shipping['wording'];
        if ($shipping['total'] !== null) {
            $message .= "\nShipping: ".Pricing::format($shipping['shipping'], 'USD')."\nEstimate: ".Pricing::format($shipping['total'], 'USD');
        }
        $number = StoreSettings::whatsappNumber();
        $whatsappUrl = $number && $destination !== '' && $quote['valid'] ? 'https://wa.me/'.$number.'?text='.rawurlencode($message) : null;

        return view('shop.inquiry', compact('quote', 'destination', 'shipping', 'whatsappUrl', 'message') + ['noindex' => true]);
    }
}
