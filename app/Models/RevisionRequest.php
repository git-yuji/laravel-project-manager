<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RevisionRequest extends Model
{
    public const STATUSES = ['pending' => '未対応', 'in_progress' => '対応中', 'completed' => '完了'];

    protected $fillable = ['content', 'status'];

    /** @return HasMany<RevisionStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(RevisionStatusHistory::class)->orderByDesc('id');
    }
}
