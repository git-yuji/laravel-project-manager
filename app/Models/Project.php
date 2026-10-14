<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = ['user_id', 'name', 'description'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function revisionRequests(): HasMany
    {
        return $this->hasMany(RevisionRequest::class);
    }
}
