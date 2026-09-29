<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppReportDraft extends Model
{
    protected $table = 'whatsapp_report_drafts';

    protected $fillable = [
        'sender_wa_id',
        'reporter_name',
        'phone_number_id',
        'incident_type',
        'severity_level',
        'district',
        'description',
        'latitude',
        'longitude',
        'photo_path',
        'started_at',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }
}
