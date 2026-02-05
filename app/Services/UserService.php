<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function findUser(string $email): ?User
    {
        return User::where('email', $email)->first();
    }


    public function create(string $name, string $email): ?User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        $user->assignRole('user');

        return $user;
    }


}
