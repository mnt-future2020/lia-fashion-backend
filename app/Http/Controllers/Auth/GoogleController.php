<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class GoogleController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            // Check if user already exists
            $user = User::where('email', $googleUser->email)->first();

            if (!$user) {
                // Create new user
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'phone' => null, // Phone is nullable for Google users
                    'password' => Hash::make(Str::random(16)), // Random password for Google users
                    'email_verified_at' => now(), // Google users are already verified
                ]);
            }

            // Generate token
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful',
                'token' => $token,
                'user' => $user
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Google login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function handleGoogleLogin(Request $request)
    {
        try {
            $request->validate([
                'access_token' => 'required|string',
            ]);

            // Verify the Google JWT token
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $request->access_token
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'Invalid Google token'
                ], 400);
            }

            $tokenInfo = $response->json();

            // Check if user already exists
            $user = User::where('email', $tokenInfo['email'])->first();

            if (!$user) {
                // Create new user
                $user = User::create([
                    'name' => $tokenInfo['name'] ?? $tokenInfo['email'],
                    'email' => $tokenInfo['email'],
                    'phone' => null, // Phone is nullable for Google users
                    'password' => Hash::make(Str::random(16)), // Random password for Google users
                    'email_verified_at' => now(), // Google users are already verified
                ]);
            }

            // Generate token
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful',
                'token' => $token,
                'user' => $user
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Google login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
