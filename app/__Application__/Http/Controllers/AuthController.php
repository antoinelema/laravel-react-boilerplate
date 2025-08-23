<?php
namespace App\__Application__\Http\Controllers;

use Illuminate\Support\Facades\Log;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use App\__Infrastructure__\Persistence\Eloquent\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, true)) {
            $request->session()->regenerate();
            
            // Redirection selon le rôle utilisateur
            if (Auth::user()->isAdmin()) {
                return redirect()->intended('/admin');
            }
            
            return redirect()->intended('/prospects/search');
        }

        return back()->withErrors([
            'email' => 'Identifiants invalides',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }


    public function handleGoogleCallback()
    {
        $googleUser = Socialite::driver('google')->user();
        $user = User::where('email', $googleUser->getEmail())->first();
        if (!$user) {
            [$firstname, $name] = $this->splitFullName($googleUser->getName() ?? $googleUser->getNickname() ?? $googleUser->getEmail());
            $user = User::create([
                'name' => $name,
                'firstname' => $firstname,
                'email' => $googleUser->getEmail(),
                'password' => bcrypt(uniqid()), // mot de passe random
            ]);
        }
        Auth::login($user);
        
        // Redirection selon le rôle utilisateur
        if ($user->isAdmin()) {
            return redirect('/admin');
        }
        
        return redirect('/prospects/search');
    }

    /**
     * Sépare un nom complet en prénom et nom (grossièrement)
     * @return array [firstname, name]
     */
    private function splitFullName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);
        $firstname = $parts[0] ?? $fullName;
        $name = $parts[1] ?? $fullName;
        return [$firstname, $name];
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'firstname' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);
        Log::info('Register data', $data);
        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);
        Auth::login($user);
        return response()->json([
            'name' => $user->name,
            'firstname' => $user->firstname,
            'email' => $user->email,
        ], 201);
    }

    public function profile()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        return inertia('Profile', [
            'id' => $user->id,
            'name' => $user->name,
            'firstname' => $user->firstname,
            'email' => $user->email,
        ]);
    }

    public function user(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'firstname' => $user->firstname,
            'email' => $user->email,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }        

        $eloquentUser = User::find($user->id);
        if (!$eloquentUser) {
            return response()->json(['message' => 'User not found'], 404);
        }
        $data = $request->validate([
            'name' => 'required|string|min:2',
            'firstname' => 'required|string|min:2',
            'password' => 'nullable|string|min:8|confirmed',
        ]);
        $eloquentUser->name = $data['name'];
        $eloquentUser->firstname = $data['firstname'];
        if (!empty($data['password'])) {
            $eloquentUser->password = Hash::make($data['password']);
        }
        $eloquentUser->save();
        
        return response()->json(['message' => 'Profil mis à jour']);
    }
}
