<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicIncidentReport extends Model
{
    protected $fillable = [
        'mobile_app_user_id',
        'brunei_identity_id',
        'reporter_ic_no',
        'reporter_full_name',
        'district',
        'incident_type',
        'description',
        'latitude',
        'longitude',
        'photo_path',
        'status',
        'submitted_to_brave',
        'brave_incident_objectid',
        'arcgis_submission_uuid',
        'arcgis_submitted_at',
        'arcgis_submission_error',
        'operator_notes',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'submitted_to_brave' => 'boolean',
            'brave_incident_objectid' => 'integer',
            'arcgis_submitted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function mobileUser()
    {
        return $this->belongsTo(MobileAppUser::class, 'mobile_app_user_id');
    }

    public function identity()
    {
        return $this->belongsTo(BruneiIdentity::class, 'brunei_identity_id');
    }
}
