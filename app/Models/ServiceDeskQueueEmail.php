<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceDeskQueueEmail extends Model
{
    protected $fillable = [
        'user_id',
        'email',
    ];
}

