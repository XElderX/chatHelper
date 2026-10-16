<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WritingSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['live_analysis' => 'boolean', 'dark_mode' => 'boolean'];
    }
}
