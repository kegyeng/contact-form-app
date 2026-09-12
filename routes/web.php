<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ContactController::class, 'index'])
    ->name('contacts.index');

Route::post('/contacts/confirm', [ContactController::class, 'confirm'])
    ->name('contacts.confirm');

Route::post('/contacts', [ContactController::class, 'store'])
    ->name('contacts.store');

Route::view('/thanks', 'contact.thanks')
    ->name('contacts.thanks');
