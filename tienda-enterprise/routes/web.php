<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\QueryDemoController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

Route::post('/ordenes', [OrderController::class, 'store'])->name('orders.store');
Route::get('/ordenes/{order}/factura', [OrderController::class, 'invoice'])->name('orders.invoice');

Route::get('/demo/n-mas-1', [QueryDemoController::class, 'lazyLoading'])->name('demo.lazy-loading');
Route::get('/demo/eager-loading', [QueryDemoController::class, 'eagerLoading'])->name('demo.eager-loading');
