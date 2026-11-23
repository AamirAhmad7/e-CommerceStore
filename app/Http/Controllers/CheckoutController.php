<?php

namespace App\Http\Controllers;

use App\Models\AssistedSaleLink;
use App\Models\Order;
use App\Payments\Gateways;
use App\Services\AssistedSales;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\CommerceSettings;
use App\Services\Promotions;
use App\Support\PakistanAddress;
use App\Support\StorefrontEvents;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function show(Request $request, Cart $cart): View
    {
        return $this->review($request, $cart);
    }

    public function review(Request $request, Cart $cart, ?AssistedSaleLink $assistedLink = null): View
    {
        $cart->guest($request);
        $items = $assistedLink?->items ?? $cart->items($request);
        $quote = $cart->quote($items, 'PKR');
        $error = null;
        $totals = null;
        $promotion = null;
        $key = null;
        $settings = CommerceSettings::get();
        $methods = array_filter(Gateways::all(), fn ($g) => $g->enabled($settings) && (! config('store.pakistan_only') || $g->code() === 'cod'));
        try {
            if ($assistedLink) {
                AssistedSales::requireAvailable($quote);
            }
            if (! $quote['valid']) {
                throw ValidationException::withMessages(['checkout' => 'Review your cart: an item may be unavailable or missing a PKR price.']);
            }
            if (! $methods) {
                throw ValidationException::withMessages(['checkout' => 'Checkout is unavailable until a payment method is configured.']);
            }
            $promotion = app(Promotions::class)->resolve($quote, $settings, $request->session()->get('promotion_code'));
            $totals = $promotion['totals'];
            $key = (string) Str::uuid();
            $reviews = $request->session()->get('checkout_reviews', []);
            $reviews = array_slice($reviews, -9, null, true);
            $reviews[$key] = ['promotion_snapshot' => $promotion['snapshot'], 'promotion_code' => $request->session()->get('promotion_code'), 'items' => $items, 'fingerprint' => Cart::fingerprint($quote, $totals), 'assisted_link_id' => $assistedLink?->id, 'revised_total' => $assistedLink && $assistedLink->subtotal_minor !== $quote['subtotal']];
            $request->session()->put('checkout_reviews', $reviews);
        } catch (ValidationException $exception) {
            $error = collect($exception->errors())->flatten()->first();
        }

        $setupBlocked = $error !== null && $quote['valid'] && ! CommerceSettings::quoteReady($quote);
        if ($setupBlocked) {
            $error = 'Online ordering is temporarily unavailable. Please contact the store for help with your order.';
        }
        $noindex = true;
        $storefrontEvents = $totals && ! $error ? [StorefrontEvents::checkout($quote, $totals)] : [];

        return view('shop.checkout', compact('storefrontEvents', 'quote', 'totals', 'key', 'error', 'methods', 'assistedLink', 'noindex', 'promotion', 'setupBlocked'));
    }

    public function store(Request $request, Cart $cart, Checkout $checkout): RedirectResponse
    {
        $reviewKey = $request->input('checkout_key');
        $review = is_string($reviewKey) ? $request->session()->get('checkout_reviews.'.$reviewKey) : null;
        try {
            $rawPhone = $request->input('phone');
            if (is_string($rawPhone)) {
                $request->merge(['phone' => PakistanAddress::phone($rawPhone) ?? $rawPhone]);
            }
            $data = $request->validate(['checkout_key' => 'required|uuid', 'name' => 'required|string|max:120', 'email' => 'nullable|email|max:254', 'phone' => ['required', 'string', 'max:25', 'regex:/^03[0-9]{9}$/'], 'address' => 'required|string|max:500', 'city' => 'required|string|max:100', 'region' => 'required|string|max:100', 'postal_code' => 'nullable|string|max:20', 'country' => 'required|in:PK', 'payment_method' => config('store.pakistan_only') ? 'required|in:cod' : 'required|in:cod,bank', 'order_notes' => 'nullable|string|max:1000', 'reviewed' => 'accepted'], ['phone.regex' => 'Enter a Pakistani mobile number, for example 03XX XXXXXXX.']);
            if (! $review) {
                throw ValidationException::withMessages(['checkout' => 'This review expired. Open checkout again.']);
            }
            if ($review['revised_total'] ?? false) {
                $request->validate(['accept_revised_total' => 'accepted']);
            }
        } catch (ValidationException $exception) {
            if ($review['assisted_link_id'] ?? null) {
                $link = AssistedSaleLink::findOrFail($review['assisted_link_id']);

                return redirect()->route('assisted.show', $link->token)->withErrors($exception->errors())->withInput($request->except(['_token', 'checkout_key']));
            }
            throw $exception;
        }
        $request->session()->put('checkout_input', $request->only(['name', 'email', 'phone', 'address', 'city', 'region', 'postal_code', 'payment_method', 'order_notes']));
        $key = $data['checkout_key'];
        unset($data['checkout_key'],$data['reviewed']);
        $data['email'] = $data['email'] ?? '';
        if ($review['assisted_link_id'] ?? null) {
            $link = AssistedSaleLink::findOrFail($review['assisted_link_id']);
            try {
                $order = app(AssistedSales::class)->convert($link, $cart->guest($request), $key, $data, $review);
            } catch (ValidationException $exception) {
                return redirect()->route('assisted.show', $link->token)->withErrors($exception->errors())->withInput($request->except(['_token', 'checkout_key']));
            }
        } else {
            try {
                $order = $checkout->create($cart->guest($request), $key, $data, $review);
            } catch (ValidationException $exception) {
                return redirect()->route('checkout.show')->withErrors($exception->errors())->withInput($request->except(['_token', 'checkout_key', 'reviewed']));
            }
        }
        if (! ($review['assisted_link_id'] ?? null) && ! $request->session()->get('checkout_completed.'.$key) && $cart->items($request) === $review['items']) {
            $request->session()->forget(['cart', 'cart_snapshot']);
        }
        $request->session()->put('checkout_completed.'.$key, true);
        $request->session()->forget('checkout_input');
        $request->session()->put('received_order', $order->id);

        if (config('store.pakistan_only')) {
            return redirect()->route('orders.received');
        }

        return redirect()->route('orders.show', $order->access_token);
    }

    public function received(Request $request, Cart $cart): View
    {
        $order = Order::whereKey($request->session()->get('received_order'))->where('guest_hash', $cart->guest($request))->with(['lines', 'histories', 'requests.lines.orderLine', 'events' => fn ($query) => $query->where('customer_visible', true)->orderBy('id')])->firstOrFail();
        $storefrontEvents = [StorefrontEvents::purchase($order)];

        return view('shop.order', ['order' => $order, 'noindex' => true, 'storefrontEvents' => $storefrontEvents]);
    }

    public function order(string $token)
    {
        $order = Order::where('access_token', $token)->with(['lines', 'histories', 'requests.lines.orderLine', 'events' => fn ($query) => $query->where('customer_visible', true)->orderBy('id')])->firstOrFail();

        return view('shop.order', compact('order'));
    }

    public function proof(Request $request, string $token)
    {
        $order = Order::where('access_token', $token)->firstOrFail();
        abort_unless($order->payment_method === 'bank' && $order->payment_status !== 'paid' && in_array($order->status, ['pending', 'confirmed'], true), 403);
        $request->validate(['proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120']);
        $path = $request->file('proof')->store('bank-proofs', 'local');
        try {
            DB::transaction(function () use ($order, $path): void {
                $locked = Order::lockForUpdate()->findOrFail($order->id);
                abort_unless($locked->payment_method === 'bank' && $locked->payment_status !== 'paid' && in_array($locked->status, ['pending', 'confirmed'], true), 403);
                $locked->update(['proof_path' => $path, 'payment_status' => 'proof_submitted']);
                $locked->histories()->create(['status' => $locked->status, 'payment_status' => 'proof_submitted', 'note' => 'Transfer proof submitted; funds not yet verified.']);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return back()->with('status', 'Proof received. Payment remains unverified until the store confirms receipt of funds.');
    }

    public function downloadProof(Order $order)
    {
        Gate::authorize('verify-payments');
        Gate::authorize('view', $order);
        abort_unless($order->proof_path, 404);

        return Storage::disk('local')->download($order->proof_path, 'transfer-proof.'.pathinfo($order->proof_path, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
