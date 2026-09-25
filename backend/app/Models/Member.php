<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Member extends Model
{
    public const LEVELS = ['Junior', 'Mid', 'Senior', 'Lead'];

    protected $fillable = [
        'slug', 'name', 'role', 'level', 'years', 'avatar', 'available',
        'location', 'education', 'languages', 'bio', 'skills', 'links', 'position',
    ];

    protected function casts(): array
    {
        return [
            'available' => 'boolean',
            'years' => 'integer',
            'skills' => 'array',
            'links' => 'array',
        ];
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }
}
