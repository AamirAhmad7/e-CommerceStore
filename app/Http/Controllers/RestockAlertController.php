<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\RestockSubscription;
use App\Services\Inventory;
use App\Services\RestockAlerts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RestockAlertController extends Controller
{
    public function store(Request $request, RestockAlerts $alerts): RedirectResponse
    {
        $data = $request->validate(['variant_id' => 'required|integer', 'email' => 'required|email:rfc|max:254']);
        $waiting = $alerts->subscribe((int) $data['variant_id'], $data['email']);
        $variant = ProductVariant::with('productColor.product.category')->findOrFail($data['variant_id']);
        if (! $waiting) {
            return redirect(RestockAlerts::buyUrl($variant))->with('status', Inventory::purchasable($variant, 'PKR', $variant->productColor->product) ? 'This size is available now. Review it and add it to your cart.' : 'Availability has not been confirmed. Please check this size again later.');
        }

        return back()->with('status', 'If this request needs confirmation, an email will arrive shortly. Open it and press Confirm alert. An alert does not reserve stock.');
    }

    public function show(string $token): Response
    {
        $subscription = RestockSubscription::where('token_hash', hash('sha256', $token))->firstOrFail();
        $variant = $subscription->variant()->with('productColor.product.category')->first();
        $public = $variant && RestockAlerts::publicVariant($variant);
        $canConfirm = $public && ! $subscription->cancelled_at && ! $subscription->completed_at && ! $subscription->confirmed_at && $subscription->confirmation_expires_at->isFuture();

        return response()->view('products.restock-manage', compact('subscription', 'variant', 'public', 'canConfirm', 'token'));
    }

    public function update(Request $request, string $token, RestockAlerts $alerts): RedirectResponse
    {
        $request->validate(['action' => 'required|in:confirm,cancel']);
        RestockSubscription::where('token_hash', hash('sha256', $token))->firstOrFail();
        if ($request->input('action') === 'confirm') {
            $alerts->confirm($token);
        } else {
            $alerts->cancel($token);
        }

        return redirect()->route('restock.manage', $token)->with('status','Your request has been updated. See its status below.');
    }
}
