<?php

use App\Http\Controllers\ClienteGoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/google/cliente', [ClienteGoogleAuthController::class, 'redirect']);
Route::get('/auth/google/cliente/callback', [ClienteGoogleAuthController::class, 'callback']);
