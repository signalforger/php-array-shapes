<?php

use App\Http\Controllers\WebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WebController::class, 'home'])->name('home');
Route::get('/job/{id}', [WebController::class, 'job'])->name('job.show');
Route::get('/api-docs', [WebController::class, 'apiDocs'])->name('api-docs');
Route::get('/about', [WebController::class, 'about'])->name('about');
