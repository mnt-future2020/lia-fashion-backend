<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Notifications\AdminResetPasswordNotification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class AdminAuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:admins',
            'password' => 'required|string|min:6',
        ]);

        $admin = Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $admin->createToken('admin-token')->plainTextToken;

        return response()->json([
            'admin' => $admin,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'message' => 'Invalid login credentials'
            ], 401);
        }

        // Create token without abilities
        $token = $admin->createToken('admin-token')->plainTextToken;

        return response()->json([
            'admin' => $admin,
            'token' => $token,
        ]);
    }

    public function logout()
    {
        auth()->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin) {
            return response()->json(['message' => 'Admin not found'], 404);
        }

        $token = Str::random(64);

        DB::table('admin_password_reset_tokens')->updateOrInsert(
            ['email' => $admin->email],
            [
                'token' => $token,
                'created_at' => now()
            ]
        );

        $admin->notify(new AdminResetPasswordNotification($token));

        return response()->json(['message' => 'Password reset link sent to your email']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:6|confirmed'
        ]);

        $reset = DB::table('admin_password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$reset || now()->subHours(1)->gt($reset->created_at)) {
            return response()->json(['message' => 'Invalid or expired token'], 400);
        }

        $admin = Admin::where('email', $request->email)->first();
        $admin->password = Hash::make($request->password);
        $admin->save();

        DB::table('admin_password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password reset successfully']);
    }

    public function verify(Request $request)
    {
        return response()->json(['verified' => true]);
    }
}
