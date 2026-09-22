<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoreTiNotificationRule extends Model
{
    protected $table = 'coreti_notification_rules';

    protected $fillable = ['event_key', 'label', 'description', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function recipients()
    {
        return $this->belongsToMany(CoreTiNotificationRecipient::class, 'coreti_notification_recipient_rule', 'rule_id', 'recipient_id');
    }
}
