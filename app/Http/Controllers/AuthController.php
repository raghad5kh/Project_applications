<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Authentication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;


class AuthController extends Controller
{


    protected $authenService;

    public function __construct(Authentication $authenService)
    {
        $this->authenService = $authenService;
    }

    public function register(Request $request)
    {

        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'username' => 'required'
        ]);
        $register = $this->authenService->register($request);

        return $register;

    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        $login = $this->authenService->login($request);
        return $login;


    }

    public function logout(Request $request)
    {
        $logout = $this->authenService->logout($request);
        return $logout;
    }

}
