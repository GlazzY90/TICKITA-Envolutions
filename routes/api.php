<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SupportOptionsController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketMessageController;
use Illuminate\Support\Facades\Route;

/*
Logic:
Defines the public login route and authenticated support-ticket API.

Structure:
Authentication is the outer boundary. Fine-grained authorization remains
inside policies/controllers because being logged in is not sufficient.

DSA:
Routing uses framework route matching; no application-level DSA.
*/

Route::post('/login', [
    AuthController::class,
    'login',
]);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [
        AuthController::class,
        'me',
    ]);

    Route::post('/logout', [
        AuthController::class,
        'logout',
    ]);

    Route::get('/tickets', [
        TicketController::class,
        'index',
    ]);

    Route::post('/tickets', [
        TicketController::class,
        'store',
    ]);

    Route::get('/tickets/{ticket}', [
        TicketController::class,
        'show',
    ]);

    Route::patch('/tickets/{ticket}', [
        TicketController::class,
        'update',
    ]);

    Route::post('/tickets/{ticket}/messages', [
        TicketMessageController::class,
        'store',
    ]);

    Route::get('/support/options', [
        SupportOptionsController::class,
        'index',
    ]);
});
