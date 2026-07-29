<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BruneiTide extends Model
{
    protected $fillable = [
        'district',
        'station',
        'source_url',
        'tide_datetime',
        'tide_height',
        'tide_type',
    ];
}