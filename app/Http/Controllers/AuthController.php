<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * POST /api/register
     * Creates an account and returns a token straight away, so the
     * frontend doesn't need a separate login call right after signing up.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'email.unique'         => 'An account with that email already exists.',
            'password.min'         => 'Password must be at least 8 characters.',
            'password.confirmed'   => 'Password confirmation does not match.',
        ]);

        // The User model casts 'password' => 'hashed', so this is
        // stored hashed automatically - no need to Hash::make() here.
        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => $validated['password'],
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully!',
            'data'    => ['user' => $user, 'token' => $token],
        ], 201);
    }

    /**
     * POST /api/login
     * Returns a new Bearer token. Send it back as
     * "Authorization: Bearer <token>" on every request after this.
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Those credentials don\'t match our records.',
            ], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Logged in successfully!',
            'data'    => ['user' => $user, 'token' => $token],
        ], 200);
    }

    /**
     * POST /api/logout
     * Revokes only the token used for this request, so logging out on
     * one device doesn't sign the user out everywhere else.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully!',
        ], 200);
    }
}
