<?php

use Illuminate\Support\Facades\Route;

/*
Logic:
Serves the same React application shell for every frontend route.

Structure:
React Router controls browser-side navigation.
Laravel still owns all /api routes separately.

DSA:
No custom algorithm. These are constant route definitions.
*/

Route::view('/', 'app');

Route::view('/login', 'app');

Route::view('/tickets', 'app');

Route::view(
    '/tickets/{ticket}',
    'app'
)->whereNumber('ticket');
