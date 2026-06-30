<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\TempUser;
use App\Notifications\OtpNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $tempUser = TempUser::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'otp' => $otp,
                'expires_at' => now()->addMinutes(10)
            ]);

            try {
                $tempUser->notify(new OtpNotification($otp));
            } catch (\Exception $e) {
                Log::error('Failed to send OTP email: ' . $e->getMessage());
                $tempUser->delete();
                return response()->json([
                    'message' => 'Failed to send OTP email',
                    'error' => $e->getMessage()
                ], 500);
            }

            return response()->json([
                'message' => 'Please verify your email with OTP',
                'email' => $tempUser->email
            ], 201);
        } catch (\Exception $e) {
            Log::error('Registration failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tempUser = TempUser::where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('expires_at', '>', now())
            ->first();

        if (!$tempUser) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        try {
            $user = User::create([
                'name' => $tempUser->name,
                'email' => $tempUser->email,
                'phone' => $tempUser->phone,
                'password' => $tempUser->password,
                'email_verified_at' => now()
            ]);

            $tempUser->delete();
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'message' => 'Registration completed successfully',
                'token' => $token,
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to complete registration'], 500);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid login credentials'
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function getAllUsers()
    {
        try {
            $users = User::select([
                'id',
                'name',
                'email',
                'phone',
                'email_verified_at',
                'created_at'
            ])->orderBy('created_at', 'desc')->get();

            return response()->json($users);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUserDetails($id)
    {
        try {
            $user = User::with('details')
                ->select([
                    'id',
                    'name',
                    'email',
                    'phone',
                    'email_verified_at',
                    'created_at'
                ])
                ->findOrFail($id);

            return response()->json($user);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch user details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
