<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CadIncidentIntake extends Model
{
    protected $table = 'cad_incident_intake';

    protected $fillable = [
        'call_id',
        'incident_category',
        'severity_level',
        'district',
        'location_description',
        'latitude',
        'longitude',
        'more_details',
        'validation_status',
        'submitted_to_brave',
        'brave_incident_objectid',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'submitted_to_brave' => 'boolean',
            'brave_incident_objectid' => 'integer',
        ];
    }

    public function call()
    {
        return $this->belongsTo(CadCall::class, 'call_id');
    }

    public function answers()
    {
        return $this->hasMany(CadValidationAnswer::class, 'intake_id');
    }
}
