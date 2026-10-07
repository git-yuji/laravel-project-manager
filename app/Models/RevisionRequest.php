<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionRequest extends Model
{
    public const STATUSES = ['pending' => '未対応', 'in_progress' => '対応中', 'completed' => '完了'];

    protected $fillable = ['content', 'status'];
}
