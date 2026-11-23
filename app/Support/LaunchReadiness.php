<?php

namespace App\Support;

use App\Filament\Pages\CatalogAttention;
use App\Models\Collection;
use App\Models\Homepage;
use App\Models\Product;
use App\Payments\Gateways;
use App\Services\CommerceSettings;
use Illuminate\Support\Facades\DB;

final class LaunchReadiness
{
    public static function findings(): array
    {
        $settings = CommerceSettings::get();
        $productsUrl = route('filament.admin.resources.products.index');
        $commerceUrl = route('filament.admin.pages.commerce-settings');
        $homeUrl = route('filament.admin.pages.homepage');
        $collectionsUrl = route('filament.admin.resources.collections.index');
        $rows = [];
        $add = function (string $status, string $title, string $detail, ?string $url = null) use (&$rows): void {
            $scope = in_array($title, ['Published purchasable products', 'PKR prices', 'Inventory', 'Color photography'], true) ? 'Product information' : 'Store-wide';
            $rows[] = compact('status', 'title', 'detail', 'url', 'scope');
        };
        $variants = DB::table('product_variants as v')->join('product_colors as c', 'c.id', '=', 'v.product_color_id')->join('products as p', 'p.id', '=', 'c.product_id')->join('categories as cat', 'cat.id', '=', 'p.category_id')->whereNull('p.deleted_at')->where('p.is_active', true)->where('cat.is_active', true)->where('v.is_active', true);
        $priced = 'COALESCE(v.pkr_price_minor,p.pkr_price_minor,0) > 0';
        $available = 'v.on_hand IS NOT NULL AND v.on_hand > v.reserved';
        $counts = (clone $variants)->selectRaw("COUNT(*) as sizes, SUM(CASE WHEN $priced THEN 0 ELSE 1 END) as missing_prices, SUM(CASE WHEN v.on_hand IS NULL THEN 1 ELSE 0 END) as unset_stock, SUM(CASE WHEN v.on_hand IS NOT NULL AND v.on_hand <= v.reserved THEN 1 ELSE 0 END) as sold_out, COUNT(DISTINCT CASE WHEN $priced AND $available THEN p.id END) as purchasable")->first();
        $publicProducts = Product::visible()->count();
        $add($counts->purchasable > 0 ? 'Ready' : 'Needs configuration', 'Published purchasable products', "{$counts->purchasable} of {$publicProducts} public products have an active, priced PKR variant with available stock.", $productsUrl);
        $add($counts->missing_prices ? 'Needs configuration' : 'Ready', 'PKR prices', (int) $counts->missing_prices.' active public sizes lack a positive effective PKR price. Product base prices and variant overrides follow the normal pricing rules.', CatalogAttention::getUrl(['issue' => 'missing_price']));
        $add($counts->unset_stock ? 'Needs configuration' : 'Ready', 'Inventory', (int) $counts->unset_stock.' active public sizes have no stock configured. '.(int) $counts->sold_out.' are out of stock (a normal business state). Use audited Adjust stock; never fabricate availability.', CatalogAttention::getUrl(['issue' => 'stock']));
        $missingPhotos = Product::visible()->whereHas('colors', fn ($q) => $q->whereHas('variants', fn ($v) => $v->where('is_active', true))->whereDoesntHave('media', fn ($m) => $m->where('collection_name', 'gallery')))->count();
        $add($missingPhotos ? 'Optional improvement' : 'Ready', 'Color photography', "{$missingPhotos} public products have an active color with no gallery photo.", $productsUrl);
        $home = Homepage::where('key', 'main')->first();
        $add($home?->published ? 'Ready' : 'Needs configuration', 'Published homepage', $home?->published ? 'A published homepage snapshot exists. Check its mobile presentation before launch.' : 'Publish your headline, hero and selected collections. The automatic catalog fallback is not owner approval.', $homeUrl);
        $ids = collect($home?->published['featured'] ?? [])->pluck('collection_id');
        if (($home?->published['primary_target'] ?? null) === 'collection') {
            $ids->push($home->published['primary_collection_id'] ?? null);
        }
        $ids = $ids->filter()->unique();
        $valid = Collection::whereIn('id', $ids)->where('is_published', true)->whereHas('eligibleItems')->count();
        $broken = $ids->count() - $valid;
        $add($broken ? 'Needs configuration' : 'Ready', 'Homepage collection links', "{$broken} configured collection links are missing, unpublished or empty. Public pages omit these sections and fall back to Shop.", $collectionsUrl);
        $shipping = $settings['shipping_minor'] !== null;
        $methods = array_filter(Gateways::all(), fn ($method) => $method->enabled($settings));
        $add($shipping && $methods ? 'Ready' : 'Needs configuration', 'Pakistan checkout', $shipping && $methods ? 'Checkout is configured for Pakistan in PKR. Payment and shipping settings still need owner approval.' : 'Checkout is disabled by missing delivery charges or an unavailable payment method.', $commerceUrl);
        $add($shipping ? 'Ready' : 'Needs configuration', 'Delivery charges', $shipping ? 'Delivery charges are configured. Verify these against your actual costs; this page does not approve them.' : 'Choose Free delivery or enter a Fixed delivery charge.', $commerceUrl);
        $add($methods ? 'Ready' : 'Needs configuration', 'Payment methods', $methods ? 'Available: '.implode(', ', array_map(fn ($m) => $m->code() === 'cod' ? 'Cash on delivery' : 'Manual bank transfer', $methods)).'. COD stays unpaid until payment is recorded.' : 'No usable payment method is enabled.', $commerceUrl);
        $add(filled($settings['contact_email']) ? 'Ready' : 'Needs configuration', 'Customer contact', filled($settings['contact_email']) ? 'A customer contact email is configured; verify someone monitors it.' : 'Set the customer contact email. WhatsApp is optional and never blocks launch.', $commerceUrl);
        $add(filled($settings['delivery_information']) ? 'Ready' : 'Needs configuration', 'Delivery information', filled($settings['delivery_information']) ? 'Owner-authored delivery text is present. Review its promises.' : 'Publish your delivery information; do not promise dates without your own policy.', $commerceUrl);
        $approved = $settings['returns_policy_approved'] && filled($settings['returns_information']);
        $add($approved ? 'Ready' : 'Needs configuration', 'Returns information', $settings['returns_enabled'] ? ($approved ? 'Approved wording is present. The displayed window comes from the return-request setting.' : 'Return requests are enabled but public wording is not approved; no public window is promised.') : 'Return requests are disabled. This is a policy choice, not a stock problem. '.($approved ? 'Approved wording is saved.' : 'Review and approve your customer-facing policy before launch.'), $commerceUrl);
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?? '';
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.test') || str_ends_with($host, '.local') || (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE));
        $add($local || ! str_starts_with(config('app.url'), 'https://') ? 'Needs configuration' : 'Manual check', 'Public site address', $local ? 'APP_URL is local/private. Public social crawlers cannot reach it. Configure a public HTTPS address for launch.' : 'Confirm the configured HTTPS site, canonical URLs, media links and certificate from a customer device.');
        foreach (['Email delivery' => 'Send a real test to your own inbox, verify order recovery links, and check the notification queue. Demo mail is captured locally; configuration cannot prove delivery.', 'Backups' => 'Perform and document a database AND media backup restoration in isolation.', 'Social previews' => 'Test public product/collection links with the public hostname. Local metadata checks do not prove WhatsApp previews.', 'Owner acceptance' => 'Approve prices, stock, delivery/returns wording and payment instructions; finish the Black/Brown form check and one mobile COD journey.'] as $title => $detail) {
            $add('Manual check', $title, $detail);
        }

        return $rows;
    }
}
