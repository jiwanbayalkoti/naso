<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Find a user by email.
     */
    public function findByEmail(string $email): ?User;

    /**
     * Get active users.
     */
    public function getActive(): Collection;
}
