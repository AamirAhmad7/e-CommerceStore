<?php

namespace App\Services;

use App\Models\AssistedSale;
use App\Models\AssistedSaleLink;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Support\ProductPhoto;
use App\Support\StoreSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AssistedSales
{
    /** @param array<int, array{variant_id: mixed, quantity: mixed}> $lines
     * @return array<int, int>
     */
    public static function items(array $lines): array
    {
        Validator::make(['lines' => $lines], ['lines' => 'required|array|min:1|max:50', 'lines.*.variant_id' => 'required|integer|min:1', 'lines.*.quantity' => 'required|integer|min:1|max:100'])->validate();
        $items = [];
        foreach ($lines as $line) {
            $id = (int) $line['variant_id'];
            $items[$id] = ($items[$id] ?? 0) + (int) $line['quantity'];
            if ($items[$id] > 100) {
                throw ValidationException::withMessages(['lines' => 'The combined quantity for a SKU cannot exceed 100.']);
            }
        }
        ksort($items);

        return $items;
    }

    /** @return array<int, array<string, mixed>> */
    public static function snapshot(array $quote): array
    {
        return array_map(function (array $line): array {
            $variant = $line['variant'];
            $photo = $variant?->productColor?->getFirstMedia('gallery');

            return ['variant_id' => $line['id'], 'product' => $line['product']?->name ?? 'Unavailable product', 'color' => $variant?->productColor?->name ?? '', 'size' => $variant?->size ?? '', 'sku' => $variant?->sku ?? 'Unavailable SKU #'.$line['id'], 'quantity' => $line['quantity'], 'price' => $line['price'], 'total' => $line['total'], 'available' => $line['available'], 'valid' => $line['valid'], 'photo' => $photo ? ProductPhoto::data($photo) : null];
        }, $quote['lines']);
    }

    public function save(array $data, ?AssistedSale $sale = null, bool $share = false): AssistedSale
    {
        Gate::authorize('manage-commerce');
        Validator::make($data, ['source' => 'required|in:whatsapp,phone', 'customer_label' => 'nullable|string|max:255', 'email' => 'nullable|email|max:254', 'phone' => 'nullable|string|max:25', 'internal_note' => 'nullable|string|max:2000'])->validate();
        $items = self::items($data['lines'] ?? []);

        return DB::transaction(function () use ($data, $sale, $share, $items): AssistedSale {
            if ($sale) {
                $sale = AssistedSale::lockForUpdate()->findOrFail($sale->id);
                if ($sale->order_id || $sale->version !== (int) ($data['version'] ?? 0)) {
                    throw ValidationException::withMessages(['sale' => 'This draft changed or was converted. Reopen it before making changes.']);
                }
                if ($sale->status === 'shared' && ! $share) {
                    throw ValidationException::withMessages(['sale' => 'Use Edit and replace link for a shared basket.']);
                }
            } else {
                $sale = new AssistedSale(['reference' => 'AS-'.Str::ulid(), 'version' => 0, 'actor_id' => auth()->id()]);
            }
            $quote = app(Cart::class)->quote($items, 'PKR');
            if ($share && ! $quote['valid']) {
                self::requireAvailable($quote);
            }
            $sale->fill(collect($data)->only(['source', 'customer_label', 'email', 'phone', 'internal_note'])->all());
            $sale->fill(['items' => $items, 'snapshot' => self::snapshot($quote), 'subtotal_minor' => $quote['subtotal'], 'version' => $sale->version + 1, 'status' => $share ? 'shared' : 'draft', 'expires_at' => $share ? now()->addHours(max(1, (int) config('store.assisted_sale_hours', 72))) : null])->save();
            if ($share) {
                $sale->links()->whereNull('revoked_at')->update(['revoked_at' => now()]);
                $token = Str::random(64);
                $sale->links()->create(['token' => $token, 'token_hash' => hash('sha256', $token), 'items' => $items, 'snapshot' => $sale->snapshot, 'subtotal_minor' => $sale->subtotal_minor, 'expires_at' => $sale->expires_at]);
            }

            return $sale;
        }, 3);
    }

    public static function formData(AssistedSale $sale): array
    {
        return $sale->only(['source', 'customer_label', 'email', 'phone', 'internal_note', 'version']) + ['lines' => collect($sale->items)->map(fn ($quantity, $id): array => ['variant_id' => $id, 'quantity' => $quantity])->values()->all()];
    }

    public function revoke(AssistedSale $sale): void
    {
        Gate::authorize('manage-commerce');
        DB::transaction(function () use ($sale): void {
            $sale = AssistedSale::lockForUpdate()->findOrFail($sale->id);
            if ($sale->order_id) {
                throw ValidationException::withMessages(['sale' => 'This sale has already converted. Use the Orders workflow.']);
            }
            $sale->links()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $sale->update(['status' => 'revoked', 'version' => $sale->version + 1]);
        });
    }

    public function duplicate(AssistedSale $sale): AssistedSale
    {
        Gate::authorize('manage-commerce');

        return $this->save(self::formData($sale->fresh()));
    }

    public static function requireAvailable(array $quote): void
    {
        if (! $quote['valid']) {
            $lines = collect($quote['lines'])->filter(fn ($line) => ! $line['valid'])->map(fn ($line): string => ($line['variant']?->sku ?? 'Removed SKU').' (requested '.$line['quantity'].', available '.$line['available'].')')->implode('; ');
            throw ValidationException::withMessages(['checkout' => 'These items cannot currently be ordered: '.$lines.'. Ask the store for an updated selection. No color or size has been substituted.']);
        }
    }

    public static function message(AssistedSaleLink $link): string
    {
        return StoreSettings::brandName().': Here is your selection: '.collect($link->snapshot)->map(fn ($line): string => $line['product'].', '.$line['color'].' / '.$line['size'].' × '.$line['quantity'])->implode('; ').'. Review your items and delivery total here: '.$link->url();
    }

    public function convert(AssistedSaleLink $link, string $guest, string $key, array $customer, array $review): Order
    {
        return DB::transaction(function () use ($link, $guest, $key, $customer, $review): Order {
            $sale = AssistedSale::lockForUpdate()->findOrFail($link->assisted_sale_id);
            $link = AssistedSaleLink::lockForUpdate()->findOrFail($link->id);
            if ($link->revoked_at || $link->expires_at->isPast()) {
                throw ValidationException::withMessages(['checkout' => 'This link was replaced, revoked or expired. Ask the store for a new link.']);
            }
            if ($sale->order_id) {
                $order = $sale->order;
                if (! hash_equals($order->guest_hash, $guest)) {
                    throw ValidationException::withMessages(['checkout' => 'This selection has already been ordered. Contact the store for another selection.']);
                }

                return $order;
            }
            if ($review['items'] !== $link->items) {
                throw ValidationException::withMessages(['checkout' => 'The shared selection changed. Request a new link.']);
            }
            self::requireAvailable(app(Cart::class)->quote($link->items, 'PKR'));
            $order = app(Checkout::class)->create($guest, $key, $customer, $review);
            $sale->update(['order_id' => $order->id, 'status' => 'converted', 'version' => $sale->version + 1]);

            return $order;
        }, 5);
    }

    public static function variantLabel(ProductVariant $variant): string
    {
        $product = $variant->productColor->product;
        $price = Pricing::resolve($variant, 'PKR', $product);

        return ($product?->name ?? 'Unavailable product').' / '.$variant->productColor->name.' / '.$variant->size.' / '.$variant->sku.' | '.($price > 0 ? Pricing::format($price) : 'PKR price unavailable').' | Available: '.Inventory::available($variant).(! $product?->is_active || ! $product?->category?->is_active || ! $variant->is_active ? ' | Inactive' : '');
    }
}
