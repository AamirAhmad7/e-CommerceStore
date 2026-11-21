<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Services\Inventory;
use App\Services\Pricing;
use App\Support\ProductPhoto;
use App\Support\ProductPresentation;
use App\Support\StoreSettings;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function show(Request $request, Product $product): Response
    {
        $product->load('category');
        abort_unless($product->is_active && $product->category?->is_active, 404);

        return $this->render($request, $product);
    }

    public function preview(Request $request, Product $product): Response
    {
        $panel = Filament::getPanel('admin');
        $user = auth($panel->getAuthGuard())->user();
        abort_unless($user, 403);
        abort_unless($user instanceof FilamentUser ? $user->canAccessPanel($panel) : config('app.env') === 'local', 403);
        Filament::setCurrentPanel($panel);
        auth()->shouldUse($panel->getAuthGuard());
        abort_unless(ProductResource::canEdit($product), 403);

        return $this->render($request, $product, preview: true)
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('Referrer-Policy', 'no-referrer');
    }

    private function render(Request $request, Product $product, bool $preview = false): Response
    {
        $currency = Pricing::currency($request->query('currency', $request->session()->get('currency')));
        $request->session()->put('currency', $currency);
        $product->loadMissing(['category', 'media', 'colors.media', 'colors.variants' => fn ($query) => $query->where('is_active', true)->orderBy('id')]);
        $baseUrl = route($preview ? 'products.preview' : 'products.show', $product->slug);
        $shared = $product->getMedia('shared')->filter(fn ($photo) => $photo->getCustomProperty('placement') === 'detail')->map(ProductPhoto::data(...))->values()->all();
        $guides = $product->getMedia('shared')->filter(fn ($photo) => $photo->getCustomProperty('placement') === 'size_guide')->map(ProductPhoto::data(...))->values()->all();
        $measurementGuide = $product->applicableSizeGuide();
        $colors = $product->colors->filter(fn ($color) => $preview || $color->variants->isNotEmpty())->map(function ($color) use ($baseUrl, $currency, $product, $preview): array {
            $photos = $color->getMedia('gallery')->map(ProductPhoto::data(...))->values()->all();
            $variants = $color->variants->sort(fn ($a, $b) => (ProductPresentation::sizeKey($a->size) <=> ProductPresentation::sizeKey($b->size)) ?: ($a->id <=> $b->id))->map(function ($variant) use ($currency, $product, $preview): array {
                $price = Pricing::resolve($variant, $currency, $product);
                $checkoutPrice = Pricing::resolve($variant, 'PKR', $product);
                $available = Inventory::available($variant);
                $problem = $checkoutPrice <= 0 ? 'PKR checkout price is not configured.' : ($price <= 0 ? $currency.' display price is not configured.' : ($variant->on_hand === null ? 'Stock is not configured.' : null));

                return ['id' => $variant->id, 'size' => $variant->size, 'label' => ProductPresentation::sizeLabel($variant->size), 'sku' => $variant->sku, 'price' => $price, 'price_label' => $price > 0 ? Pricing::format($price, $currency) : ($preview ? 'Display price is not configured.' : 'Price unavailable'), 'available' => $available, 'can_buy' => Inventory::purchasable($variant, $currency, $product), 'availability' => $problem ? ($preview ? $problem : ($price <= 0 || $checkoutPrice <= 0 ? 'Currently unavailable to order. Choose another size or ask the store about this item.' : 'Availability has not been confirmed. Choose another size or ask the store.')) : ($available > 0 ? 'In stock: '.$available.' available.' : 'Sold out'), 'disabled_label' => $available === 0 ? 'Sold out' : 'Currently unavailable', 'stock_configured' => $variant->on_hand !== null, 'can_notify' => ! $preview && $variant->is_active && $checkoutPrice > 0 && $price > 0 && $variant->on_hand !== null && $available === 0];
            })->values()->all();
            $labels = array_count_values(array_column($variants, 'label'));
            foreach ($variants as &$variant) {
                if ($labels[$variant['label']] > 1) {
                    $variant['label'] .= ' ('.$variant['sku'].')';
                }
            }
            unset($variant);

            return [
                'id' => (string) $color->id,
                'name' => $color->name,
                'swatch' => preg_match('/^#[a-f0-9]{6}$/i', $color->swatch_hex ?? '') ? $color->swatch_hex : null,
                'url' => $baseUrl.'?'.http_build_query(['color' => $color->id, 'currency' => $currency]),
                'photos' => $photos,
                'variants' => $variants,
                'price_label' => ProductPresentation::price($variants, $preview),
                'availability' => ! $variants ? 'No sizes are currently available to order. Browse other products or contact the store.' : (collect($variants)->contains('can_buy', true) ? 'Choose a size.' : (collect($variants)->every(fn ($variant) => $variant['available'] === 0) ? 'All sizes in this color are sold out. Choose another color or ask the store.' : 'No sizes in this color can currently be ordered. Choose another color or ask the store.')),
                'status' => ! $variants ? 'No active sizes (preview only)' : (collect($variants)->every(fn ($v) => $v['stock_configured'] && $v['available'] === 0) ? 'Sold out' : ''),
            ];
        })->values();
        $whatsappNumber = $preview ? null : StoreSettings::whatsappNumber();
        $whatsappUrl = null;
        if ($whatsappNumber) {
            $publicUrl = rtrim(config('app.url'), '/').route('products.show', $product->slug, false);
            $makeWhatsappUrl = function (?array $color = null, ?array $variant = null) use ($whatsappNumber, $product, $publicUrl): string {
                $message = "Hello, I'm interested in ".$product->name;
                if ($color) {
                    $message .= ' in '.$color['name'];
                }
                if ($variant) {
                    $message .= ', size '.$variant['size'].' (SKU '.$variant['sku'].')';
                }

                return 'https://wa.me/'.$whatsappNumber.'?text='.rawurlencode($message.'. Is this available?'."\n".$publicUrl);
            };
            $whatsappUrl = $makeWhatsappUrl();
            $colors = $colors->map(function (array $color) use ($makeWhatsappUrl): array {
                $color['whatsapp_url'] = $makeWhatsappUrl($color);
                foreach ($color['variants'] as &$variant) {
                    $variant['whatsapp_url'] = $makeWhatsappUrl($color, $variant);
                }
                unset($variant);

                return $color;
            });
        }
        $selected = $colors->firstWhere('id', $request->query('color')) ?? $colors->first(fn (array $color): bool => collect($color['variants'])->contains('can_buy', true)) ?? $colors->first(fn (array $color): bool => count($color['variants']) > 0) ?? $colors->first();
        $colorUnavailable = $request->filled('color') && ! $colors->contains('id', $request->query('color'));
        $selectedVariant = $colorUnavailable ? null : collect($selected['variants'] ?? [])->firstWhere('size', $request->query('size'));
        $sizeUnavailable = $request->filled('size') && ! ($selectedVariant['can_buy'] ?? false);
        if ($sizeUnavailable) {
            $selectedVariant = null;
        } elseif (! $request->filled('size') && ! $colorUnavailable) {
            $purchasable = collect($selected['variants'] ?? [])->where('can_buy', true);
            $selectedVariant = $purchasable->count() === 1 ? $purchasable->first() : null;
        }
        $brand = StoreSettings::brandName();
        $canonical = route('products.show', $product->slug);
        $metaDescription = Str::limit(Str::squish(strip_tags($product->description ?: $product->name.' by '.$brand.'. Explore colors, photos and offered sizes.')), 155, '');
        $socialPhoto = $selected['photos'][0] ?? null;

        return response()->view('products.show', compact('product', 'preview', 'colors', 'selected', 'selectedVariant', 'shared', 'guides', 'measurementGuide', 'colorUnavailable', 'sizeUnavailable', 'brand', 'canonical', 'metaDescription', 'socialPhoto', 'currency', 'whatsappUrl'));
    }
}
