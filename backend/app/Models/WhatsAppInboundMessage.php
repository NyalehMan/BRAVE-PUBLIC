<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppInboundMessage extends Model
{
    protected $table = 'whatsapp_inbound_messages';

    protected $fillable = [
        'meta_message_id',
        'sender_wa_id',
        'message_type',
        'public_incident_report_id',
        'processed_at',
        'processing_error',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    public function report()
    {
        return $this->belongsTo(
            PublicIncidentReport::class,
            'public_incident_report_id'
        );
    }
}
