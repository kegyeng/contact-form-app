<?php

use App\Http\Controllers\Api\V1\ContactController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function () {
    Route::get('/contacts', [ContactController::class, 'index'])
        ->name('api.v1.contacts.index');

    Route::get('/contacts/{contact}', [ContactController::class, 'show'])
        ->name('api.v1.contacts.show');
    Route::post('/contacts', [ContactController::class, 'store'])
        ->name('api.v1.contacts.store');
    Route::put('/contacts/{contact}', [ContactController::class, 'update'])
        ->name('api.v1.contacts.update');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])
        ->name('api.v1.contacts.destroy');
});
