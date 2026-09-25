<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Project extends Model
{
    protected $fillable = ['title', 'category', 'year', 'image', 'link', 'desc', 'tech', 'position'];

    protected function casts(): array
    {
        return ['tech' => 'array'];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class);
    }
}
