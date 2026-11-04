<?php

namespace App\Models;

class PlatformUser extends User
{
    protected $connection = 'mercatura';
    protected $table = 'users';
    protected string $guard_name = 'platform';
}
