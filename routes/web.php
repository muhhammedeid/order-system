<?php

use App\Http\Controllers\CatalogController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home');
});

Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');

Route::get('/product/{product:slug}', [CatalogController::class, 'show'])->name('product.show');
