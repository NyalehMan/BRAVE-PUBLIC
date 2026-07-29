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
        'operator_notes',
        'verified_by',
        'verified_at',
    ];

    public function mobileUser()
    {
        return $this->belongsTo(MobileAppUser::class, 'mobile_app_user_id');
    }

    public function identity()
    {
        return $this->belongsTo(BruneiIdentity::class, 'brunei_identity_id');
    }
}