<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/koleksiyonlar', [CollectionController::class, 'index'])->name('collections.index');
Route::get('/koleksiyon/{slug}', [CollectionController::class, 'show'])->name('collections.show');
Route::get('/urun/{slug}', [ProductController::class, 'show'])->name('products.show');

Route::get('/sepet', [CartController::class, 'index'])->name('cart.index');
Route::post('/sepet/ekle', [CartController::class, 'add'])->name('cart.add');
Route::post('/sepet/guncelle', [CartController::class, 'update'])->name('cart.update');
Route::post('/sepet/cikar', [CartController::class, 'remove'])->name('cart.remove');
Route::post('/sepet/kupon', [CartController::class, 'applyCoupon'])->name('cart.coupon');
Route::post('/sepet/kupon-sil', [CartController::class, 'forgetCoupon'])->name('cart.coupon.forget');

Route::get('/iletisim', [PageController::class, 'contact'])->name('contact');
Route::get('/sayfa/{slug}', [PageController::class, 'legal'])->name('legal');
