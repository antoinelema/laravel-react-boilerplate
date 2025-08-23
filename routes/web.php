<?php
use App\__Application__\Http\Controllers\AuthController;
use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;

Route::get('/', function () {
    return view('welcome');
});


// Auth Google Socialite
Route::get('/auth/google', function () {
    return Socialite::driver('google')->redirect();
});
Route::get('/auth/google/callback', [
    AuthController::class,
    'handleGoogleCallback',
]);

Route::get('/login', function () {
    return Inertia::render('Login');
});
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login');
Route::get('/register', function () {
    return Inertia::render('Register');
});
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    
    // Routes prospects - recherche ouverte à tous les utilisateurs authentifiés
    Route::get('/prospects/search', function () {
        return Inertia::render('ProspectSearch');
    });
    
    // Routes prospects premium uniquement
    Route::middleware('premium')->group(function () {
        Route::get('/prospects', function () {
            return Inertia::render('ProspectDashboard');
        });
    });
});

// Page d'upgrade pour utilisateurs gratuits
Route::middleware('auth')->get('/upgrade', function () {
    return Inertia::render('UpgradePage');
});