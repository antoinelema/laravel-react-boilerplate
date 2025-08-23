<?php

use App\__Application__\Http\Controllers\Api\ProspectController;
use App\__Application__\Http\Controllers\Api\ProspectSearchController;
use App\__Application__\Http\Controllers\Api\ProspectNoteController;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group.
|
*/

Route::middleware(['auth:sanctum,web'])->prefix('v1')->group(function () {
    
    // Recherche de prospects via APIs externes
    Route::prefix('prospects')->group(function () {
        // Recherche externe avec limitation pour les utilisateurs gratuits
        Route::middleware(['search.limit'])->post('search', [ProspectSearchController::class, 'search']);
        
        // Routes publiques (pas de limitation premium)
        Route::get('sources', [ProspectSearchController::class, 'sources']);
        Route::get('quota', [ProspectSearchController::class, 'quota']);
    });

    // Routes Premium uniquement - CRUD prospects
    Route::middleware(['premium'])->prefix('prospects')->group(function () {
        Route::get('/', [ProspectController::class, 'index']);
        Route::post('/', [ProspectController::class, 'store']);
        Route::get('{id}', [ProspectController::class, 'show']);
        Route::put('{id}', [ProspectController::class, 'update']);
        Route::delete('{id}', [ProspectController::class, 'destroy']);
        
        // Recherche dans prospects existants
        Route::get('search/local', [ProspectController::class, 'search']);
    });
    
    // Notes de prospects - Premium uniquement
    Route::middleware(['premium'])->prefix('prospects/{prospectId}/notes')->group(function () {
        Route::get('/', [ProspectNoteController::class, 'index']);
        Route::post('/', [ProspectNoteController::class, 'store']);
        Route::put('{noteId}', [ProspectNoteController::class, 'update']);
        Route::delete('{noteId}', [ProspectNoteController::class, 'destroy']);
    });
    
    // Historique des recherches (à implémenter plus tard)
    // Route::get('search-history', [ProspectSearchController::class, 'history']);
});

// Routes API admin
Route::middleware(['auth:sanctum,web', 'admin'])->prefix('v1/admin')->group(function () {
    Route::get('stats', [AdminController::class, 'stats']);
    Route::get('users/{user}', [AdminController::class, 'userDetails']);
    Route::post('users/{user}/upgrade', [AdminController::class, 'upgradeUser']);
    Route::post('users/{user}/downgrade', [AdminController::class, 'downgradeUser']);
    Route::post('users/{user}/reset-quota', [AdminController::class, 'resetUserQuota']);
});