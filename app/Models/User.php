<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const MASTER_USER_ID = 2;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'role',
        'reset_token',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function modulePermissions(): HasOne
    {
        return $this->hasOne(UserModulePermission::class);
    }

    public function hasModuleAccess(string $module): bool
    {
        if ($this->isMasterAccount()) {
            return true;
        }

        if ($this->role === 'admin') {
            return true;
        }

        $allowedModules = ['servicedesk', 'unidades', 'aplicativos', 'bancada', 'administrativo'];
        if (! in_array($module, $allowedModules, true)) {
            return false;
        }

        $permissions = $this->modulePermissions;
        if (! $permissions) {
            return false;
        }

        return (bool) ($permissions->{$module} ?? false);
    }

    public function isMasterAccount(): bool
    {
        return (int) $this->id === self::MASTER_USER_ID;
    }
}
