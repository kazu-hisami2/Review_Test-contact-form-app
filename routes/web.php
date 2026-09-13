<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => 'お問い合わせフォーム（準備中）');

Route::middleware('auth')->group(function () {
    Route::get('/admin', fn () => 'お問い合わせ一覧（準備中）')->name('admin.index');
});
