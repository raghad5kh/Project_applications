<?php

namespace App\Services;

use App\Models\User;

class UserService extends Service
{
    public function getUserById($userId)
    {
        return User::query()->find($userId);
    }
    
    public function getUserByEmailOrUsername($user){
        return User::query()->where('email', $user)->orWhere('username', $user)->first();
    }
}
