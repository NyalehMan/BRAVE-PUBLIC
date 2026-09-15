<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CadCall extends Model
{
    protected $fillable = [
        'call_ref',
        'caller_phone',
        'caller_name',
        'caller_id_type',
        'caller_id_no',
        'operator_name',
        'status',
        'call_started_at',
        'call_ended_at',
        'previous_call_count',
        'false_alarm_count',
        'suspicious_score',
        'prank_flag',
    ];

    protected function casts(): array
    {
        return [
            'call_started_at' => 'datetime',
            'call_ended_at' => 'datetime',
            'previous_call_count' => 'integer',
            'false_alarm_count' => 'integer',
            'suspicious_score' => 'integer',
            'prank_flag' => 'boolean',
        ];
    }

    public function intakes()
    {
        return $this->hasMany(CadIncidentIntake::class, 'call_id');
    }
}
