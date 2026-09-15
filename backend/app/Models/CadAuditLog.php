<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CadAuditLog extends Model
{
    protected $fillable = [
        'action',
        'entity_type',
        'entity_id',
        'user_id',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }
}
