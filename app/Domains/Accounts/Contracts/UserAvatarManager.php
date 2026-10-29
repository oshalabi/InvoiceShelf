<?php

namespace App\Domains\Accounts\Contracts;

use App\Domains\Accounts\Models\User;

interface UserAvatarManager
{
    public function clear(User $user): void;

    public function replaceFile(User $user, string $path, string $fileName): void;

    public function replaceBase64(User $user, string $contents, string $fileName): void;
}
