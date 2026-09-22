<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cmdb_servers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_id')->unique()->index();
            $table->string('cpu')->nullable();
            $table->unsignedInteger('memory_gb')->nullable();
            $table->unsignedInteger('storage_total_gb')->nullable();
            $table->string('raid')->nullable();
            $table->string('hypervisor')->nullable();
            $table->boolean('is_virtualized')->default(false);
            $table->date('warranty_start_date')->nullable();
            $table->date('warranty_end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('cmdb_virtual_machines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_id')->unique()->index();
            $table->unsignedBigInteger('host_asset_id')->nullable()->index();
            $table->unsignedInteger('cpu_cores')->nullable();
            $table->unsignedInteger('memory_gb')->nullable();
            $table->unsignedInteger('storage_gb')->nullable();
            $table->string('hypervisor')->nullable();
            $table->string('datastore')->nullable();
            $table->timestamps();
        });

        Schema::create('cmdb_databases', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_id')->unique()->index();
            $table->unsignedBigInteger('server_asset_id')->nullable()->index();
            $table->string('database_type')->nullable()->index();
            $table->string('version')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('instance_name')->nullable();
            $table->boolean('backup_enabled')->default(false);
            $table->boolean('replication_enabled')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('cmdb_network_devices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_id')->unique()->index();
            $table->string('device_type')->nullable()->index();
            $table->string('firmware_version')->nullable();
            $table->unsignedInteger('ports_count')->nullable();
            $table->boolean('poe_enabled')->default(false);
            $table->boolean('stack_enabled')->default(false);
            $table->string('management_ip')->nullable();
            $table->timestamps();
        });

        Schema::create('cmdb_firewalls', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_id')->unique()->index();
            $table->string('firewall_type')->nullable()->index();
            $table->string('firmware_version')->nullable();
            $table->string('license_key')->nullable();
            $table->date('license_expiration_date')->nullable();
            $table->boolean('ha_enabled')->default(false);
            $table->boolean('vpn_enabled')->default(false);
            $table->unsignedInteger('vpn_count')->nullable();
            $table->timestamps();
        });

        Schema::create('cmdb_storages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_id')->unique()->index();
            $table->string('storage_type')->nullable()->index();
            $table->decimal('total_capacity_tb', 10, 2)->nullable();
            $table->decimal('used_capacity_tb', 10, 2)->nullable();
            $table->string('raid')->nullable();
            $table->boolean('iscsi_enabled')->default(false);
            $table->boolean('nfs_enabled')->default(false);
            $table->boolean('smb_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('cmdb_ups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_id')->unique()->index();
            $table->unsignedInteger('power_va')->nullable();
            $table->unsignedInteger('power_watts')->nullable();
            $table->unsignedInteger('battery_quantity')->nullable();
            $table->string('battery_type')->nullable();
            $table->date('installation_date')->nullable();
            $table->date('last_battery_replacement_date')->nullable();
            $table->date('next_battery_replacement_date')->nullable();
            $table->unsignedInteger('estimated_runtime_minutes')->nullable();
            $table->date('warranty_start_date')->nullable();
            $table->date('warranty_end_date')->nullable();
            $table->unsignedBigInteger('rack_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('cmdb_ip_addresses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_id')->nullable()->index();
            $table->string('ip_address')->index();
            $table->string('subnet_mask')->nullable();
            $table->string('gateway')->nullable();
            $table->string('dns')->nullable();
            $table->string('vlan')->nullable();
            $table->string('mac_address')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'is_primary']);
        });

        Schema::create('cmdb_relationships', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('source_asset_id')->index();
            $table->unsignedBigInteger('target_asset_id')->index();
            $table->string('relationship_type', 40)->index();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['source_asset_id', 'target_asset_id']);
        });

        Schema::create('cmdb_expirations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_id')->index();
            $table->string('expiration_type', 40)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('expiration_date')->index();
            $table->unsignedInteger('alert_days_before')->default(30);
            $table->string('status', 20)->default('pending')->index();
            $table->string('jira_issue_key')->nullable()->index();
            $table->string('jira_issue_url')->nullable();
            $table->timestamp('notified_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'expiration_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cmdb_expirations');
        Schema::dropIfExists('cmdb_relationships');
        Schema::dropIfExists('cmdb_ip_addresses');
        Schema::dropIfExists('cmdb_ups');
        Schema::dropIfExists('cmdb_storages');
        Schema::dropIfExists('cmdb_firewalls');
        Schema::dropIfExists('cmdb_network_devices');
        Schema::dropIfExists('cmdb_databases');
        Schema::dropIfExists('cmdb_virtual_machines');
        Schema::dropIfExists('cmdb_servers');
    }
};
