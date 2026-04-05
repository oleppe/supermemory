<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function authenticatedUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    protected function supermemoryContainerTag(User $user): string
    {
        return 'user-'.$user->getKey();
    }
}
