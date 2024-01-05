<?php


namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthService extends Service
{

    public function register($data)
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'username' => $data['username']
        ]);

        $authToken = $user->createToken('auth-token')->plainTextToken;

        return [
            'message' => 'Registration successful',
            'user' => $user,
            'authToken' => $authToken];
    }

    public function login(User $user)
    {
        $authToken = $user->createToken('auth-token')->plainTextToken;

        return [
            'message' => 'Login successful',
            'user' => $user, 
            'authToken' => $authToken];
    }


    public function logout(User $user)
    {
        // Revoke the current access token
        $deleted = $user->tokens()->delete();

        if ($deleted) {
            return ['message' => 'Token revoked successfully'];
        } else {
            return ['message' => 'Failed to revoke token', 'status' => 500];
        }
    }
}
