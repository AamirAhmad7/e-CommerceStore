<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Services\OrderCare;
use App\Services\OrderMessages;
use App\Support\PakistanAddress;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

class OrderCareController extends Controller
{
    public function lookupForm(): View
    {
        return view('shop.find-order', ['noindex' => true]);
    }

    public function lookup(Request $request): RedirectResponse
    {
        if ($request->filled('contact')) {
            $request->validate(['contact' => 'required|string|max:254']);
            $contact = trim($request->string('contact')->toString());
            $request->merge(filter_var($contact, FILTER_VALIDATE_EMAIL) ? ['email' => $contact] : ['phone' => $contact]);
        }
        if ($request->filled('phone')) {
            $data = $request->validate(['number' => 'required|string|max:100', 'phone' => 'required|string|max:25']);
            $phone = PakistanAddress::phone($data['phone']);
            $key = 'order-phone-lookup:'.hash('sha256', strtolower(trim($data['number'])));
            if ($phone && ! RateLimiter::tooManyAttempts($key, 5)) {
                RateLimiter::hit($key, 600);
                $order = Order::where('number', trim($data['number']))->first();
                $stored = $order ? PakistanAddress::phone($order->customer['phone'] ?? null) : null;
                if ($stored && hash_equals($stored, $phone)) {
                    return redirect()->route('orders.show', $order->access_token);
                }
            }

            return back()->withErrors(['contact' => 'We could not find a matching order. Check your reference and checkout phone, or contact the store.'])->withInput($request->only('number', 'contact'));
        }
        $data = $request->validate(['number' => 'required|string|max:100', 'email' => 'required|email|max:254']);
        $email = strtolower(trim($data['email']));
        $key = 'order-lookup:'.hash('sha256', $email);
        if (! RateLimiter::tooManyAttempts($key, 3)) {
            RateLimiter::hit($key, 600);
            try {
                DB::transaction(function () use ($data, $email): void {
                    $order = Order::where('number', trim($data['number']))->lockForUpdate()->first();
                    if ($order && hash_equals(strtolower($order->customer['email']), $email)) {
                        OrderMessages::record($order, 'lookup-'.intdiv(now()->timestamp, 600), 'Your private order link', 'Use the private link below to view your order and available next steps.', false);
                    }
                });
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return back()->with('status', 'If those details match an order, we will email its private access link. Please check your inbox.');
    }

    public function submit(Request $request, string $token, OrderCare $care): RedirectResponse
    {
        $order = Order::where('access_token', $token)->firstOrFail();
        $data = $request->validate(['type' => 'required|in:cancellation,return', 'submission_key' => 'required|uuid', 'reason' => 'required|string|max:1000', 'quantities' => 'nullable|array', 'quantities.*' => 'integer|min:0|max:100']);
        $care->request($order->id, $data['type'], $data['submission_key'], $data['reason'], $data['quantities'] ?? []);

        return redirect()->route('orders.show', $token)->with('status', 'Request received. The store will review it; no refund has been made.');
    }

    public function message(OrderEvent $event): Response
    {
        $order = Order::findOrFail($event->order_id);
        Gate::authorize('view', $order);

        return response()->view('mail.preview', ['event' => $event, 'order' => $order, 'noindex' => true])->header('X-Content-Type-Options', 'nosniff');
    }
}
