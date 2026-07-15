<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
    ];
}
