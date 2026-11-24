<?php

use App\Http\Controllers\AssistedSaleController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\DispatchPrintController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\OrderCareController;
use App\Http\Controllers\PackingSlipController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\PurchaseDocumentController;
use App\Http\Controllers\RestockAlertController;
use App\Http\Controllers\SavedJacketsController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\StoreExportController;
use App\Http\Controllers\StoreInformationController;
use App\Http\Middleware\PrivateShopping;
use App\Services\CommerceSettings;
use App\Support\StorefrontCampaigns;
use App\Support\StorefrontSeo;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

foreach (['about' => 'about-us', 'contact' => 'contact-us', 'faq' => 'help', 'privacy' => 'privacy', 'terms' => 'terms'] as $page => $path) {
    Route::get('/'.$path, [StoreInformationController::class, 'show'])->defaults('page', $page)->name('information.'.$page);
}

Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/products/{product:slug}/preview', [ProductController::class, 'preview'])->name('products.preview');

Route::get('/', [HomeController::class, 'show'])->name('home');
Route::get('/homepage/preview', [HomeController::class, 'preview'])->name('home.preview');
Route::get('/collections/{collection:slug}', [CollectionController::class, 'show'])->name('collections.show');
Route::get('/collections/{collection:slug}/preview', [CollectionController::class, 'preview'])->name('collections.preview');
Route::get('/sitemap.xml', [CollectionController::class, 'sitemap'])->name('sitemap');
Route::get('/delivery-and-returns', fn () => view('shop.policies', ['settings' => CommerceSettings::get(), 'canonical' => StorefrontCampaigns::url('policies')]))->name('policies');
Route::get('/shop/suggestions', [ShopController::class, 'suggestions'])->middleware('throttle:120,1')->name('search.suggestions');
Route::get('/shop', [ShopController::class, 'index'])->name('shop');
Route::get('/categories/{category:slug}', [ShopController::class, 'index'])->name('categories.show');
Route::middleware(PrivateShopping::class)->group(function (): void {
    Route::get('/inquiry', [InquiryController::class, 'show'])->name('inquiry.show');
    Route::post('/inquiry/items', [InquiryController::class, 'add'])->block()->name('inquiry.add');
    Route::post('/inquiry', [InquiryController::class, 'update'])->block()->name('inquiry.update');
    Route::get('/admin/dispatch/picking', [DispatchPrintController::class, 'picking'])->name('dispatch.picking');
    Route::get('/admin/dispatch/manifests/{manifest}', [DispatchPrintController::class, 'manifest'])->name('dispatch.manifest');
    Route::get('/admin/courier-statement-template', [DispatchPrintController::class, 'template'])->name('settlements.template');
    Route::get('/assisted/{token}', [AssistedSaleController::class, 'show'])->where('token', '[a-zA-Z0-9]{64}')->name('assisted.show');
    Route::post('/assisted/{token}/checkout', [AssistedSaleController::class, 'checkout'])->where('token', '[a-zA-Z0-9]{64}')->middleware('throttle:20,1')->block()->name('assisted.checkout');
    Route::get('/admin/order-packing-slips/{order}', PackingSlipController::class)->name('orders.packing-slip');
    Route::get('/find-order', [OrderCareController::class, 'lookupForm'])->name('orders.find');
    Route::post('/find-order', [OrderCareController::class, 'lookup'])->middleware('throttle:6,1')->name('orders.lookup');
    Route::post('/orders/{token}/requests', [OrderCareController::class, 'submit'])->where('token', '[a-zA-Z0-9]{64}')->middleware('throttle:10,1')->name('orders.request');
    Route::get('/admin/order-messages/{event}', [OrderCareController::class, 'message'])->name('orders.message');
    Route::get('/products/{product:slug}/choices', [ProductController::class, 'choices'])->name('products.choices');
    Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
    Route::post('/cart', [CartController::class, 'add'])->name('cart.add')->block();
    Route::patch('/cart/{variant}', [CartController::class, 'update'])->name('cart.update')->block();
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store')->block();
    Route::get('/order-received', [CheckoutController::class, 'received'])->name('orders.received');
    Route::get('/orders/{token}', [CheckoutController::class, 'order'])->where('token', '[a-zA-Z0-9]{64}')->name('orders.show');
    Route::post('/orders/{token}/proof', [CheckoutController::class, 'proof'])->where('token', '[a-zA-Z0-9]{64}')->middleware('throttle:10,1')->name('orders.proof');
    Route::get('/admin/order-proofs/{order}', [CheckoutController::class, 'downloadProof'])->name('orders.proof.download');
});

Route::middleware(PrivateShopping::class)->group(function (): void {
    Route::get('/admin/purchases/open-lines.csv', [PurchaseDocumentController::class, 'export'])->name('purchases.export');
    Route::get('/admin/purchases/{purchaseOrder}/print', [PurchaseDocumentController::class, 'print'])->name('purchases.print');
});

Route::middleware(PrivateShopping::class)->group(function (): void {
    Route::post('/cart/promotion', [PromotionController::class, 'apply'])->name('cart.promotion')->middleware('throttle:30,1')->block();
    Route::delete('/cart/promotion', [PromotionController::class, 'remove'])->name('cart.promotion.remove')->block();
    Route::get('/admin/promotions/{promotion}/results.csv', [PromotionController::class, 'export'])->name('promotions.export');
});

Route::middleware(PrivateShopping::class)->group(function (): void {
    Route::post('/restock-alerts', [RestockAlertController::class, 'store'])->middleware('throttle:restock-subscribe')->name('restock.store');
    Route::get('/restock-alerts/{token}', [RestockAlertController::class, 'show'])->where('token', '[a-zA-Z0-9]{64}')->middleware('throttle:restock-view')->name('restock.manage');
    Route::post('/restock-alerts/{token}', [RestockAlertController::class, 'update'])->where('token', '[a-zA-Z0-9]{64}')->middleware('throttle:restock-manage')->name('restock.update');
});

Route::get('/robots.txt', function (): Response {
    $content = StorefrontSeo::indexingAllowed() ? "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /cart\nDisallow: /checkout\nDisallow: /orders/\nDisallow: /restock-alerts/\nSitemap: ".StorefrontCampaigns::url('sitemap')."\n" : "User-agent: *\nDisallow: /\n";

    return response($content)->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('robots');

Route::get('/admin/store-exports/{dataset}.csv', StoreExportController::class)->middleware([PrivateShopping::class, 'throttle:10,1'])->name('store.export');

Route::middleware(PrivateShopping::class)->group(function (): void {
    Route::post('/cart/{variant}/save-for-later', [SavedJacketsController::class, 'saveCartItem'])->middleware('throttle:60,1')->block()->name('saved.cart');
    Route::get('/saved-jackets', [SavedJacketsController::class, 'show'])->name('saved.show');
    Route::post('/saved-jackets/{product:slug}', [SavedJacketsController::class, 'update'])->middleware('throttle:60,1')->block()->name('saved.update');
    Route::delete('/recent-jackets', [SavedJacketsController::class, 'clear'])->block()->name('saved.clear');
});
