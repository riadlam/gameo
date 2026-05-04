<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/privacy_policy', function () {
    return response()->file(public_path('privacy_policy.html'));
});
