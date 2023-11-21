<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;


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

        Auth::login($user);

        return response()->json(['message' => 'Registration successful', 'user' => $user]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => 'required|string',
            'password' => 'required',
        ]);

        // Determine if the login input is an email or a username
        $field = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        // Add the determined field to the credentials array
        $credentials[$field] = $credentials['login'];
        unset($credentials['login']);
        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            return response()->json(['message' => 'Login successful']);
        }

        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    public function logout(Request $request)
    {
        $user = Auth::guard('web')->user(); // Get the currently authenticated user

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logout successful',
            'user_logged_out' => $user ? $user->name : 'Unknown',
        ]);
    }
}
