<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface MenuRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Get active root menus with children for sidebar.
     */
    public function getSidebarTree(): Collection;

    /**
     * Get parent menu options for forms.
     */
    public function parentOptions(?int $excludeId = null): Collection;
}
