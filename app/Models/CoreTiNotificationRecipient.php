<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoreTiNotificationRecipient extends Model
{
    protected $table = 'coreti_notification_recipients';

    protected $fillable = ['name', 'email', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function rules()
    {
        return $this->belongsToMany(CoreTiNotificationRule::class, 'coreti_notification_recipient_rule', 'recipient_id', 'rule_id');
    }
}
