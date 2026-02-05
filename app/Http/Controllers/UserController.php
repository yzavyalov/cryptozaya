<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\Operations\BalanceService;
use App\Services\UserService;
use Illuminate\Http\Request;
use function Sodium\compare;

class UserController extends Controller
{
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function createUser(UserRequest $request)
    {
        $data = $request->validated();

        $user = $this->userService->create($data['name'], $data['email']);

        $user->assignRole('user');

        if ($user) {
            return redirect()->back()->with('success', 'User successfully created.');
        } else {
            return redirect()->back()->with('error', 'Failed to create user.');
        }
    }

    public function index()
    {
        $users = User::all();

        return view('cabinet.all-users',compact('users'));
    }

}
