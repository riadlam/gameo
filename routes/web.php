<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/privacy_policy', function () {
    return response()->file(public_path('privacy_policy.html'));
});

Route::get('/terms_and_conditions', function () {
    return response()->file(public_path('terms_and_conditions.html'));
});

Route::get('/terms', function () {
    return response()->file(public_path('terms_and_conditions.html'));
});
