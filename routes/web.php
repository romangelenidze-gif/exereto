<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductSearchController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderMediaController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShipmentController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\FeedProductController;
use App\Http\Controllers\FeedCategoryController;

// 1. Редирект с голого домена (exereto.com) на язык по умолчанию (exereto.com/ru)
Route::get('/', function () {
    return redirect('/ru');
});

// 2. Оборачиваем ВСЕ маршруты в префикс языка
Route::prefix('{locale}')->where(['locale' => 'ru|en|ka'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Публичные маршруты
    |--------------------------------------------------------------------------
    */
    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::view('/how-it-works', 'public.how-it-works')->name('how-it-works');
    Route::get('/buy-for-me', [OrderController::class, 'create'])->name('buy-for-me');
    Route::view('/forwarding', 'public.forwarding')->name('forwarding');
    Route::view('/help', 'public.help')->name('help');

    // Публичные юридические страницы
    Route::view('/terms', 'public.terms')->name('terms');
    Route::view('/privacy', 'public.privacy')->name('privacy');

    Route::get('/product-search', [ProductSearchController::class, 'index'])->name('product-search');

    // AWIN
    Route::get('/product/{feed}/{sku}', [FeedProductController::class, 'show'])
           ->where('feed', '[a-z0-9\-]+')->where('sku', '[A-Za-z0-9\-_.]+')
           ->name('feed-product.show');

    // Страница категории товаров из фида (плашки на главной)
    Route::get('/category/{slug}', [FeedCategoryController::class, 'show'])
           ->where('slug', '[a-z0-9\-]+')
           ->name('feed-category.show');
           
    // ПУБЛИЧНЫЙ МАРШРУТ АВТОКОМПЛИТА С ТРОТТЛИНГОМ (30 запросов в минуту)
    Route::get('/api/autocomplete', [ProductSearchController::class, 'autocomplete'])
        ->middleware('throttle:60,1')
        ->name('api.autocomplete');

    /*
    |--------------------------------------------------------------------------
    | Гостевые маршруты авторизации
    |--------------------------------------------------------------------------
    */
    Route::middleware('guest')->group(function () {
        Route::view('/login', 'auth.login')->name('login');
        Route::post('/login', [AuthController::class, 'login']);

        Route::view('/register', 'auth.register')->name('register');
        Route::post('/register', [AuthController::class, 'register']);

        // --- Маршруты восстановления пароля ---
        Route::get('/forgot-password', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'create'])->name('password.request');
        Route::post('/forgot-password', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'store'])->name('password.email');
        
        Route::get('/reset-password/{token}', [App\Http\Controllers\Auth\ResetPasswordController::class, 'create'])->name('password.reset');
        Route::post('/reset-password', [App\Http\Controllers\Auth\ResetPasswordController::class, 'store'])->name('password.update');
    });

    /*
    |--------------------------------------------------------------------------
    | Маршруты подтверждения Email и выхода (Доступны авторизованным)
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth')->group(function () {
        Route::get('/email/verify', function () {
            return view('auth.verify-email');
        })->name('verification.notice');

        Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
            $request->fulfill();
            return redirect()->route('dashboard')->with('success', 'Почта успешно подтверждена!');
        })->middleware('signed')->name('verification.verify');

        Route::post('/email/verification-notification', function (Request $request) {
            $request->user()->sendEmailVerificationNotification();
            return back()->with('status', 'verification-link-sent');
        })->middleware('throttle:6,1')->name('verification.send');

        // Выход из системы
        Route::post('/logout', function () {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
            return redirect()->route('home');
        })->name('logout');
    });

    /*
    |--------------------------------------------------------------------------
    | Личный кабинет пользователя (Защищенная зона)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
        Route::get('/dashboard', [OrderController::class, 'index'])->name('dashboard');
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/quotes/{quote}/accept', [OrderController::class, 'acceptQuote'])->name('orders.quotes.accept');
        Route::post('/orders/{order}/quotes/{quote}/reject', [OrderController::class, 'rejectQuote'])->name('orders.quotes.reject');
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/orders/{order}/payments', [PaymentController::class, 'store'])->name('orders.payments.store');
        Route::get('/packages', [PackageController::class, 'index'])->name('packages.index');
        Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');
        Route::get('/orders/{order}/payments/{payment}/receipt', [OrderMediaController::class, 'downloadReceipt'])->name('orders.payments.receipt');
        Route::get('/packages/{package}/photo', [OrderMediaController::class, 'downloadPackagePhoto'])->name('packages.photo');
        Route::get('/orders/{order}/items/{item}/screenshot', [OrderMediaController::class, 'downloadItemScreenshot'])->name('orders.items.screenshot');    
    });

});