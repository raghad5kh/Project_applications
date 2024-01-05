<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;


class AuthController extends Controller
{

    public function __construct(private AuthService $authenService){}

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'username' => 'required'
        ]);
        $result = $this->authenService->register($request);

        return response()->json(['message' => $result['message'], 'user' => $result['user'], 'Token' => $result['authToken']], 200);

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
        $result = $this->authenService->login($user);
        return response()->json(['message' => $result['message'], 'user' => $result['user'], 'Token' => $result['authToken']], 200);


    }

    public function logout(Request $request)
    {
        $result = $this->authenService->logout($request->user());
        if (isset($result['status'])) {
            return response()->json(['message' => $result['message']], $result['status']);
        }
        return response()->json(['message' => $result['message']], 200);
    }

}
