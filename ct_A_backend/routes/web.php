<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - CT_Achats
|--------------------------------------------------------------------------
|
| Catch-all route to serve the Vue.js SPA for all non-API URLs.
|
*/

Route::get('/{any}', function () {
    return file_get_contents(public_path('index.html'));
})->where('any', '^(?!api).*$');
