<?php

namespace App\Http\Controllers;

use App\__Infrastructure__\Eloquent\UserEloquent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect('/login')->withErrors(['email' => 'Erreur Google: ' . $e->getMessage()]);
        }

        $user = UserEloquent::where('email', $googleUser->getEmail())->first();

        if (!$user) {
            $user = UserEloquent::create([
                'name' => $googleUser->getName() ?? $googleUser->getNickname() ?? 'Utilisateur',
                'email' => $googleUser->getEmail(),
                'password' => Hash::make(Str::random(24)),
            ]);
        }

        Auth::login($user, true);

        return redirect('/');
    }
}
