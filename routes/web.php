<?php

use App\Http\Controllers\DirectAssistantController;
use App\Http\Controllers\OrderSupportController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::view('/login', 'login')->middleware('guest')->name('login');
Route::post('/login', [SessionController::class, 'store'])
    ->middleware(['guest', 'throttle:5,1'])->name('login.store');
Route::post('/logout', [SessionController::class, 'destroy'])
    ->middleware('auth')->name('logout');
Route::view('/order-support', 'order-support')->middleware('auth')->name('order-support.show');
Route::view('/direct-assistant', 'direct-assistant')->middleware('auth')->name('direct-assistant.show');
Route::post('/direct-assistant', DirectAssistantController::class)
    ->middleware(['auth', 'throttle:10,1'])->name('direct-assistant');

Route::post('/order-support', OrderSupportController::class)
    ->middleware(['auth', 'throttle:10,1'])
    ->name('order-support');

Route::get('/', function () {
    return view('welcome');
});
