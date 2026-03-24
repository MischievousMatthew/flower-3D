<?php

use Illuminate\Support\Facades\Route;

Route::get('/login', function () {
    return response()->json([
        'message' => 'Please use the API login endpoint',
        'api_login_url' => '/api/auth/login'
    ]);
})->name('login');