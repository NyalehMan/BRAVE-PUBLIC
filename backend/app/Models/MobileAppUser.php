<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileAppUser extends Model
{
    protected $fillable = [
        'brunei_identity_id',
        'device_id',
        'api_token',
        'last_login_at',
    ];

    public function identity()
    {
        return $this->belongsTo(BruneiIdentity::class, 'brunei_identity_id');
    }
}