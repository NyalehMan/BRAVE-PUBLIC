<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CadValidationAnswer extends Model
{
    protected $fillable = [
        'intake_id',
        'question_text',
        'answer',
        'risk_score',
    ];

    protected function casts(): array
    {
        return [
            'risk_score' => 'integer',
        ];
    }
}
