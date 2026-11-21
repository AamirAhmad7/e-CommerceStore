<?php

namespace App\Support;

use App\Models\Product;
use App\Services\Pricing;
use Illuminate\Support\Str;

final class StorefrontSeo
{
    public static function description(?string $text): string
    {
        return Str::limit(Str::squish(strip_tags($text ?? '')), 320, '');
    }

    public static function indexingAllowed(): bool
    {
        return config('app.env') === 'production' && config('store.indexing_enabled', false) && ! in_array(parse_url(config('app.url'), PHP_URL_HOST), ['localhost', '127.0.0.1', '::1'], true);
    }

    public static function organization(): array
    {
        return ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => StoreSettings::brandName(), 'url' => StorefrontCampaigns::url('home')];
    }

    public static function product(Product $product, ?array $color, ?array $selectedVariant, string $currency, string $description): array
    {
        $url = StorefrontCampaigns::url('products.show', ['product' => $product->slug]);
        $node = ['@type' => 'Product', 'name' => $product->name, 'description' => $description, 'url' => $url];
        if ($color) {
            $node['color'] = $color['name'];
            if ($color['photos']) {
                $node['image'] = array_column($color['photos'], 'src');
            }
            if ($selectedVariant) {
                $node['sku'] = $selectedVariant['sku'];
                $node['size'] = $selectedVariant['size'];
            }
            $variants = $selectedVariant ? [$selectedVariant] : $color['variants'];
            $offers = [];
            if ($currency === 'PKR') {
                foreach ($variants as $variant) {
                    if ($variant['price'] <= 0 || ! $variant['stock_configured'] || (! $variant['can_buy'] && ! $variant['can_notify'])) {
                        continue;
                    }
                    $offers[] = ['@type' => 'Offer', 'price' => Pricing::decimal($variant['price'], 'PKR'), 'priceCurrency' => 'PKR', 'availability' => 'https://schema.org/'.($variant['can_buy'] ? 'InStock' : 'OutOfStock'), 'url' => $url.'?'.http_build_query(['color' => $color['id'], 'size' => $variant['size'], 'currency' => 'PKR'])];
                }
            }
            if ($offers) {
                $node['offers'] = $offers;
            }
        }
        $crumbs = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Shop', 'item' => StorefrontCampaigns::url('shop')]];
        if ($product->category?->is_active) {
            $crumbs[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $product->category->name, 'item' => StorefrontCampaigns::url('categories.show', ['category' => $product->category->slug])];
        }
        $crumbs[] = ['@type' => 'ListItem', 'position' => count($crumbs) + 1, 'name' => $product->name, 'item' => $url];

        return ['@context' => 'https://schema.org', '@graph' => [$node, ['@type' => 'BreadcrumbList', 'itemListElement' => $crumbs]]];
    }
}
