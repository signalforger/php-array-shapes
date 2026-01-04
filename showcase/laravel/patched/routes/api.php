<?php

use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\PokemonController;
use App\Http\Controllers\Api\SavedSearchController;
use Illuminate\Support\Facades\Route;

// Jobs
Route::get('/jobs', [JobController::class, 'index']);
Route::get('/jobs/stats', [JobController::class, 'stats']);
Route::get('/jobs/{id}', [JobController::class, 'show']);

// Companies
Route::get('/companies', [CompanyController::class, 'index']);
Route::get('/companies/{slug}', [CompanyController::class, 'show']);

// Saved Searches (with webhook support)
Route::get('/saved-searches', [SavedSearchController::class, 'index']);
Route::post('/saved-searches', [SavedSearchController::class, 'store']);
Route::get('/saved-searches/{id}', [SavedSearchController::class, 'show']);
Route::put('/saved-searches/{id}', [SavedSearchController::class, 'update']);
Route::delete('/saved-searches/{id}', [SavedSearchController::class, 'destroy']);
Route::post('/saved-searches/{id}/test-webhook', [SavedSearchController::class, 'testWebhook']);

// Pokemon (PokeAPI integration)
Route::get('/pokemon', [PokemonController::class, 'index']);
Route::get('/pokemon/{nameOrId}', [PokemonController::class, 'show']);
