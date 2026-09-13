<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

// お問い合わせフォームのルーティング（認証なし）
Route::get('/', [ContactController::class, 'index'])->name('contact.index');
Route::post('/contacts/confirm', [ContactController::class, 'confirm'])->name('contacts.confirm');
Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
Route::get('/thanks', [ContactController::class, 'thanks'])->name('contacts.thanks');

// 管理用画面のルーティング（要認証）
Route::middleware('auth')->group(function () {
    Route::get('/admin', fn () => 'お問い合わせ一覧（準備中）')->name('admin.index');
});
