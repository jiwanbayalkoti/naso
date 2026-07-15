<?php

namespace App\Models;

use App\Traits\HasAuditColumns;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Route;

class Menu extends Model
{
    use HasAuditColumns;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'parent_id',
        'title',
        'icon',
        'route_name',
        'url',
        'route_pattern',
        'permission',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('sort_order');
    }

    public function getResolvedUrlAttribute(): string
    {
        if ($this->route_name && Route::has($this->route_name)) {
            return route($this->route_name);
        }

        return $this->url ?? '#';
    }

    public function isActiveRoute(): bool
    {
        if ($this->route_pattern) {
            return request()->routeIs($this->route_pattern);
        }

        if ($this->route_name) {
            return request()->routeIs($this->route_name);
        }

        return false;
    }
}
