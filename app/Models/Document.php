<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = ['title', 'content', 'version'];

    protected function casts(): array
    {
        return ['content' => 'array', 'version' => 'integer'];
    }
}
