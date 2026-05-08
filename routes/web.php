<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt; 
use App\Http\Controllers\SocialController;

Volt::route('/', 'auth')->name('auth');

//Socialite Routes
Route::get('/auth/{provider}/redirect', [SocialController::class, 'redirectProvider'])->where('provider', 'google|github')->name('social.redirect');
Route::get('/auth/{provider}/callback', [SocialController::class, 'callbackRequest'])->where('provider', 'google|github')->name('social.callback');

Route::middleware('auth')->group(function () {
    Volt::route('/chat', 'messenger')->name('messenger');
    Route::post('/logout', [SocialController::class, 'logout'])->name('logout');
});
