<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmdbAsset extends Model
{
    use SoftDeletes;

    protected $table = 'cmdb_assets';

    protected $fillable = [
        'asset_type',
        'name',
        'hostname',
        'description',
        'manufacturer_id',
        'manufacturer',
        'model_id',
        'model',
        'serial_number',
        'patrimony_number',
        'status',
        'criticality',
        'environment',
        'company_id',
        'unit_id',
        'organization_unit_id',
        'organization_department_id',
        'location',
        'rack_id',
        'main_ip',
        'operating_system',
        'responsible_user_id',
        'notes',
        'source_type',
        'source_connector_id',
        'source_external_id',
        'last_synced_at',
        'sync_status',
        'sync_error',
        'sync_hash',
        'backup_exception',
        'backup_exception_reason',
        'backup_exception_approved_by',
        'backup_exception_expires_at',
    ];

    protected $casts = [
        'manufacturer_id' => 'integer',
        'model_id' => 'integer',
        'last_synced_at' => 'datetime',
        'backup_exception' => 'boolean',
        'backup_exception_approved_by' => 'integer',
        'backup_exception_expires_at' => 'datetime',
    ];

    public function rack(): BelongsTo
    {
        return $this->belongsTo(CmdbRack::class, 'rack_id');
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    public function organizationDepartment(): BelongsTo
    {
        return $this->belongsTo(OrganizationDepartment::class, 'organization_department_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function sourceConnector(): BelongsTo
    {
        return $this->belongsTo(CmdbConnector::class, 'source_connector_id');
    }

    public function backupRecords(): HasMany
    {
        return $this->hasMany(CmdbAssetBackup::class, 'asset_id');
    }

    public function manufacturerCatalog(): BelongsTo
    {
        return $this->belongsTo(CmdbManufacturer::class, 'manufacturer_id');
    }

    public function modelCatalog(): BelongsTo
    {
        return $this->belongsTo(CmdbModel::class, 'model_id');
    }

    public function serverDetails(): HasOne
    {
        return $this->hasOne(CmdbServer::class, 'asset_id');
    }

    public function virtualMachineDetails(): HasOne
    {
        return $this->hasOne(CmdbVirtualMachine::class, 'asset_id');
    }

    public function databaseDetails(): HasOne
    {
        return $this->hasOne(CmdbDatabase::class, 'asset_id');
    }

    public function networkDeviceDetails(): HasOne
    {
        return $this->hasOne(CmdbNetworkDevice::class, 'asset_id');
    }

    public function firewallDetails(): HasOne
    {
        return $this->hasOne(CmdbFirewall::class, 'asset_id');
    }

    public function storageDetails(): HasOne
    {
        return $this->hasOne(CmdbStorage::class, 'asset_id');
    }

    public function upsDetails(): HasOne
    {
        return $this->hasOne(CmdbUps::class, 'asset_id');
    }

    public function ipAddresses(): HasMany
    {
        return $this->hasMany(CmdbIpAddress::class, 'asset_id');
    }

    public function expirationItems(): HasMany
    {
        return $this->hasMany(CmdbExpiration::class, 'asset_id');
    }

    public function outgoingRelationships(): HasMany
    {
        return $this->hasMany(CmdbRelationship::class, 'source_asset_id');
    }

    public function incomingRelationships(): HasMany
    {
        return $this->hasMany(CmdbRelationship::class, 'target_asset_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(CmdbAssetHistory::class, 'asset_id')->orderByDesc('created_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CmdbAssetDocument::class, 'asset_id')->orderByDesc('created_at');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(CmdbAssetDocument::class, 'asset_id')
            ->where('document_type', 'photo')
            ->orderByDesc('created_at');
    }
}
