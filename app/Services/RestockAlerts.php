<?php

namespace App\Services;

use App\Jobs\ConfirmRestockEmail;
use App\Jobs\SendRestockAlerts;
use App\Models\ProductVariant;
use App\Models\RestockSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class RestockAlerts
{
    public static function publicVariant(ProductVariant $variant): bool
    {
        $product = $variant->productColor?->product;

        return $variant->is_active && $product?->is_active && $product->category?->is_active && Pricing::resolve($variant, 'PKR', $product) > 0;
    }

    public static function shortage(ProductVariant $variant): bool
    {
        return self::publicVariant($variant) && $variant->on_hand !== null && Inventory::available($variant) === 0;
    }

    public static function buyUrl(ProductVariant $variant): string
    {
        return rtrim(config('app.url'), '/').route('products.show', [$variant->productColor->product->slug, 'color' => $variant->product_color_id, 'size' => $variant->size, 'currency' => 'PKR'], false);
    }

    public static function label(ProductVariant $variant): string
    {
        return $variant->productColor->product->name.' / '.$variant->productColor->name.' / '.$variant->size;
    }

    public function subscribe(int $variantId, string $email): bool
    {
        return DB::transaction(function () use ($variantId, $email): bool {
            $variant = ProductVariant::with('productColor.product.category')->lockForUpdate()->findOrFail($variantId);
            abort_unless(self::publicVariant($variant), 404);
            if (! self::shortage($variant)) {
                return false;
            }
            $email = mb_strtolower(trim($email));
            $row = RestockSubscription::where('product_variant_id', $variantId)->where('email_hash', hash('sha256', $email))->lockForUpdate()->first();
            if ($row && ! $row->cancelled_at && ! $row->completed_at && ($row->confirmed_at || $row->created_at->gt(now()->subMinutes(15)))) {
                return true;
            }
            $token = Str::random(64);
            $values = ['email' => $email, 'token' => $token, 'token_hash' => hash('sha256', $token), 'confirmation_expires_at' => now()->addDays(7), 'confirmation_sent_at' => null, 'confirmed_at' => null, 'cancelled_at' => null, 'completed_at' => null, 'created_at' => now()];
            if ($row) {
                $row->update($values);
            } else {
                $row = RestockSubscription::create($values + ['product_variant_id' => $variantId, 'email_hash' => hash('sha256', $email)]);
            }
            ConfirmRestockEmail::dispatch($row->id, $row->token_hash)->afterCommit();

            return true;
        });
    }

    public function confirm(string $token): void
    {
        $found = RestockSubscription::where('token_hash', hash('sha256', $token))->firstOrFail();
        DB::transaction(function () use ($found): void {
            $variant = ProductVariant::lockForUpdate()->findOrFail($found->product_variant_id);
            $row = RestockSubscription::lockForUpdate()->findOrFail($found->id);
            if ($row->token_hash !== $found->token_hash || $row->cancelled_at || $row->completed_at || (! $row->confirmed_at && $row->confirmation_expires_at->isPast())) {
                return;
            }
            if (! $row->confirmed_at) {
                $row->update(['confirmed_at' => now()]);
            }
            DB::afterCommit(fn () => SendRestockAlerts::dispatch($variant->id));
        });
    }

    public function cancel(string $token): void
    {
        RestockSubscription::where('token_hash', hash('sha256', $token))->whereNull('cancelled_at')->update(['cancelled_at' => now()]);
    }

    public static function mail(RestockSubscription $row, string $subject, string $body, string $kind): void
    {
        $database = config('database.connections.'.config('database.default').'.database');
        $mailer = $database === 'leather_store_demo' ? 'log' : config('mail.default');
        $sent = Mail::mailer($mailer)->raw($body, function ($message) use ($row, $subject, $kind): void {
            $message->to($row->email)->subject($subject);
            $message->getSymfonyMessage()->getHeaders()->addIdHeader('Message-ID', 'restock-'.$kind.'-'.$row->id.'-'.$row->token_hash.'@'.parse_url(config('app.url'), PHP_URL_HOST));
        });
        if ($sent === null) {
            throw new \RuntimeException('The restock message was not handed to the mail transport.');
        }
    }

    public static function manageUrl(RestockSubscription $row): string
    {
        return rtrim(config('app.url'), '/').route('restock.manage',['token' => $row->token],false);
    }
}
