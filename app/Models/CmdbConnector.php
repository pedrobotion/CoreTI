<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmdbConnector extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'manual' => 'Manual',
        'vmware' => 'VMware',
        'vcenter' => 'VMware vCenter',
        'veeam' => 'Veeam Backup & Replication',
        'proxmox' => 'Proxmox',
        'hyperv' => 'Hyper-V',
        'snmp' => 'SNMP',
        'ssh' => 'SSH',
        'winrm' => 'WinRM',
        'sonicwall' => 'SonicWall',
        'fortigate' => 'Fortigate',
        'mikrotik' => 'Mikrotik',
        'idrac' => 'iDRAC',
        'ilo' => 'iLO',
        'api' => 'API',
        'other' => 'Outro',
    ];

    public const STATUSES = [
        'active' => 'Ativo',
        'inactive' => 'Inativo',
        'error' => 'Erro',
        'testing' => 'Em teste',
    ];

    protected $table = 'cmdb_connectors';

    protected $fillable = [
        'name',
        'connector_type',
        'host',
        'port',
        'username',
        'password_encrypted',
        'token_encrypted',
        'status',
        'is_active',
        'verify_ssl',
        'timeout_seconds',
        'sync_interval_minutes',
        'import_powered_off',
        'last_connection_test_at',
        'last_connection_test_status',
        'last_connection_test_message',
        'last_successful_sync_at',
        'last_failed_sync_at',
        'last_sync_at',
        'last_sync_status',
        'last_error',
        'settings',
    ];

    protected $casts = [
        'port' => 'integer',
        'is_active' => 'boolean',
        'verify_ssl' => 'boolean',
        'import_powered_off' => 'boolean',
        'timeout_seconds' => 'integer',
        'sync_interval_minutes' => 'integer',
        'last_sync_at' => 'datetime',
        'last_connection_test_at' => 'datetime',
        'last_successful_sync_at' => 'datetime',
        'last_failed_sync_at' => 'datetime',
        'settings' => 'array',
    ];

    public function syncJobs(): HasMany
    {
        return $this->hasMany(CmdbSyncJob::class, 'connector_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(CmdbSyncLog::class, 'connector_id')->orderByDesc('started_at');
    }

    public function discoveredAssets(): HasMany
    {
        return $this->hasMany(CmdbDiscoveredAsset::class, 'connector_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(CmdbAsset::class, 'source_connector_id');
    }
}
