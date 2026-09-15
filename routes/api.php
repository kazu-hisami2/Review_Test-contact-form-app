<?php

use App\Http\Controllers\Api\V1\ContactController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/contacts', [ContactController::class, 'index']);
Route::get('/v1/contacts/{contact}', [ContactController::class, 'show']);
Route::post('/v1/contacts/', [ContactController::class, 'store']);
Route::put('/v1/contacts/{contact}', [ContactController::class, 'update']);
Route::delete('/v1/contacts/{contact}', [ContactController::class, 'destroy']);
