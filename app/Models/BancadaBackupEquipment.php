<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BancadaBackupEquipment extends Model
{
    protected $table = 'bancada_backup_equipments';

    protected $fillable = [
        'tipo_equipamento',
        'plaqueta',
    ];
}
