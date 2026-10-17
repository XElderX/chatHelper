<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatGptAccount extends Model
{
    protected $table = 'chatgpt_accounts';

    protected $guarded = [];

    protected $hidden = ['credentials', 'subject', 'auth_epoch'];

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array'];
    }
}
