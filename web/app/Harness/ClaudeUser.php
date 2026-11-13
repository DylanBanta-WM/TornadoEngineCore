<?php

namespace App\Harness;

use App\Models\User;

/**
 * The account Claude uses when it opens the website through the harness. It can't sign in
 * with Google; admins decide what it can see with roles, like anyone else.
 */
class ClaudeUser
{
    public const EMAIL = 'claude@harness.invalid';

    public static function get(): User
    {
        return User::firstOrCreate(['email' => self::EMAIL], ['name' => 'Claude (harness)']);
    }
}
