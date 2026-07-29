<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BruneiIdentity extends Model
{
    protected $fillable = [
        'ic_no',
        'full_name',
        'address',
        'nationality',
        'id_picture',
        'password',
    ];

    protected $hidden = [
        'password',
    ];
}