<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;


class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'username' => 'required'
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'username' => $data['username']
        ]);

        $authToken = $user->createToken('auth-token')->plainTextToken;


        return response()->json([
            'message' => 'Registration successful',
            'user' => $user,
            "Token" => $authToken]);
    }


    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        
        
        // Use 'email' and 'password' keys in the Auth::attempt method
        if (!Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Get the authenticated user
        $user = Auth::user();
        
        // Create a personal access token for the user
        $authToken = $user->createToken('auth-token')->plainTextToken;

        return response()->json(['message' => 'Login successful','user' => $user, 'Token' => $authToken]);
    }


    public function logout(Request $request)
    {
        // Revoke the current access token
        $deleted = $request->user()->tokens()->delete();

        if ($deleted) {
            return response()->json(['message' => 'Token revoked successfully']);
        } else {
            return response()->json(['message' => 'Failed to revoke token'], 500);
        }
    }

}