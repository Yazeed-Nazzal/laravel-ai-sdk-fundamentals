<?php

use App\Http\Controllers\DirectAssistantController;
use App\Http\Controllers\ManualConversationController;
use App\Http\Controllers\OrderSupportController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SupportConversationController;
use Illuminate\Support\Facades\Route;

Route::view('/login', 'login')->middleware('guest')->name('login');
Route::post('/login', [SessionController::class, 'store'])
    ->middleware(['guest', 'throttle:5,1'])->name('login.store');
Route::post('/logout', [SessionController::class, 'destroy'])
    ->middleware('auth')->name('logout');

// build first laravel ai agent using sdk with prvoides tool
Route::view('/order-support', 'order-support')->middleware('auth')->name('order-support.show');
Route::post('/order-support', OrderSupportController::class)
    ->middleware(['auth', 'throttle:10,1'])
    ->name('order-support');

// deal with the open ai api directly without laravel sdk
Route::view('/direct-assistant', 'direct-assistant')->middleware('auth')->name('direct-assistant.show');
Route::post('/direct-assistant', DirectAssistantController::class)
    ->middleware(['auth', 'throttle:10,1'])->name('direct-assistant');

// implement previous response id
Route::view('/chat', 'chat')->middleware('auth')->name('chat.show');
Route::view('/manual-chat', 'chat', ['manualChat' => true])->middleware('auth')->name('manual-chat.show');
Route::middleware('auth')->group(function (): void {
    Route::get('/support-conversations', [SupportConversationController::class, 'index'])->name('support-conversations.index');
    Route::get('/support-conversations/{conversation}', [SupportConversationController::class, 'show'])->whereNumber('conversation')->name('support-conversations.show');
    Route::post('/support-conversations', [SupportConversationController::class, 'store'])->middleware('throttle:10,1')->name('support-conversations.store');

    // implement manual api response and function call and output loop without laravel ai sdk
    Route::get('/manual-conversations', [ManualConversationController::class, 'index'])->name('manual-conversations.index');
    Route::get('/manual-conversations/{conversation}', [ManualConversationController::class, 'show'])->whereNumber('conversation')->name('manual-conversations.show');
    Route::post('/manual-conversations', [ManualConversationController::class, 'store'])->middleware('throttle:10,1')->name('manual-conversations.store');

});

Route::get('/', function () {
    return view('welcome');
});
