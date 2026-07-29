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
    ];

    public function intakes()
    {
        return $this->hasMany(CadIncidentIntake::class, 'call_id');
    }
}
