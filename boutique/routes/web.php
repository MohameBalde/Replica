<?php

use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DeliverySettingsController;
use App\Http\Controllers\Admin\PaymentSettingsController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ShareController;
use App\Http\Controllers\Admin\StoreSettingsController;
use App\Http\Controllers\Admin\StoreSetupController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\InvoiceController;
use App\Http\Controllers\Shop\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('admin.orders.index') : redirect()->route('register'));

// S10 : compte commerçant
Route::middleware('guest')->group(function () {
    Route::get('/inscription', [RegisterController::class, 'create'])->name('register');
    Route::post('/inscription', [RegisterController::class, 'store'])->middleware('throttle:auth');
    Route::get('/connexion', [LoginController::class, 'create'])->name('login');
    Route::post('/connexion', [LoginController::class, 'store'])->middleware('throttle:auth');
});
Route::post('/deconnexion', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Espace commerçant
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    // S11 : création de la boutique
    Route::get('/boutique/creer', [StoreSetupController::class, 'create'])->name('store.create');
    Route::post('/boutique/creer', [StoreSetupController::class, 'store'])->name('store.store');

    Route::middleware('store')->group(function () {
        Route::get('/', fn () => redirect()->route('admin.orders.index'))->name('home');

        // S12, S13 : commandes
        Route::get('/commandes', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/commandes/{order}', [OrderController::class, 'show'])->whereNumber('order')->name('orders.show');
        Route::patch('/commandes/{order}/statuts', [OrderController::class, 'updateStatuses'])->whereNumber('order')->name('orders.statuses');
        Route::post('/commandes/{order}/annuler', [OrderController::class, 'cancel'])->whereNumber('order')->name('orders.cancel');
        Route::get('/commandes/{order}/preuves/{proof}', [OrderController::class, 'proof'])->whereNumber(['order', 'proof'])->name('orders.proof');

        // S17 : clients
        Route::get('/clients', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/clients/{customer}', [CustomerController::class, 'show'])->whereNumber('customer')->name('customers.show');
        Route::post('/clients/{customer}/paiement-livraison', [CustomerController::class, 'toggleCashOnDelivery'])->whereNumber('customer')->name('customers.cod');

        // S14, S15 : produits
        Route::get('/produits', [ProductController::class, 'index'])->name('products.index');
        Route::get('/produits/creer', [ProductController::class, 'create'])->name('products.create');
        Route::post('/produits', [ProductController::class, 'store'])->name('products.store');
        Route::get('/produits/{product}', [ProductController::class, 'edit'])->whereNumber('product')->name('products.edit');
        Route::put('/produits/{product}', [ProductController::class, 'update'])->whereNumber('product')->name('products.update');

        // S20 : boutique (infos, ouvert / fermé)
        Route::get('/reglages', [StoreSettingsController::class, 'edit'])->name('settings.store');
        Route::put('/reglages', [StoreSettingsController::class, 'update'])->name('settings.store.update');
        Route::post('/reglages/ouverture', [StoreSettingsController::class, 'toggle'])->name('settings.store.toggle');

        // S18 : modes de livraison (frais, minimum, paiement à la livraison par mode)
        Route::get('/livraison', [DeliverySettingsController::class, 'index'])->name('delivery.index');
        Route::post('/livraison', [DeliverySettingsController::class, 'store'])->name('delivery.store');
        Route::put('/livraison/{method}', [DeliverySettingsController::class, 'update'])->whereNumber('method')->name('delivery.update');

        // S22 : partager ma boutique
        Route::get('/partager', [ShareController::class, 'show'])->name('share');
        Route::put('/partager/message', [ShareController::class, 'updateWelcome'])->name('share.welcome');
        Route::get('/partager/qr.svg', [ShareController::class, 'qr'])->name('share.qr');

        // S19 : paiements (dont le paiement à la livraison activable)
        Route::get('/paiement', [PaymentSettingsController::class, 'edit'])->name('payments.edit');
        Route::put('/paiement/livraison', [PaymentSettingsController::class, 'updateCashOnDelivery'])->name('payments.cod');
        Route::put('/paiement/{type}', [PaymentSettingsController::class, 'updateMobileMoney'])
            ->whereIn('type', ['orange_money', 'mtn_momo'])->name('payments.mobile');
    });
});

// S06, S07 : facture publique (lien non devinable) et envoi WhatsApp
Route::get('/c/{order}', [InvoiceController::class, 'show'])->name('invoice.show');
Route::get('/c/{order}/whatsapp', [InvoiceController::class, 'whatsapp'])->name('invoice.whatsapp');
Route::post('/c/{order}/preuve', [InvoiceController::class, 'storeProof'])->middleware('throttle:proof')->name('invoice.proof');

// Boutique publique : en dernier, car /{store} attrape tout le reste.
Route::prefix('{store}')->name('shop.')->group(function () {
    Route::get('/', [StorefrontController::class, 'show'])->name('show');
    Route::get('/p/{product:slug}', [StorefrontController::class, 'product'])->scopeBindings()->name('product');
    Route::get('/panier', [CartController::class, 'show'])->name('cart');
    Route::post('/panier', [CartController::class, 'add'])->middleware('throttle:cart')->name('cart.add');
    Route::patch('/panier', [CartController::class, 'update'])->name('cart.update');
    Route::get('/commande', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/commande', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
});
