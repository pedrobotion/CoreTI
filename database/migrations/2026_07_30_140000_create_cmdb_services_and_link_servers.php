<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cmdb_services')) {
            Schema::create('cmdb_services', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->text('description')->nullable();
                $table->string('category')->nullable()->index();
                $table->unsignedBigInteger('owner_id')->nullable()->index();
                $table->string('criticality', 20)->nullable();
                $table->boolean('active')->default(true)->index();
                $table->timestamps();
            });
        }

        DB::table('cmdb_services')->insertOrIgnore(collect([
            ['name' => 'SAP', 'category' => 'Aplicação'],
            ['name' => 'Citrix', 'category' => 'Virtualização'],
            ['name' => 'Microsoft SQL Server', 'category' => 'Banco de dados'],
            ['name' => 'Progress', 'category' => 'Banco de dados'],
            ['name' => 'Zabbix', 'category' => 'Monitoramento'],
            ['name' => 'N8N', 'category' => 'Automação'],
            ['name' => 'Power BI', 'category' => 'Aplicação'],
            ['name' => 'Active Directory', 'category' => 'Infraestrutura'],
            ['name' => 'DNS', 'category' => 'Infraestrutura'],
            ['name' => 'DHCP', 'category' => 'Infraestrutura'],
            ['name' => 'Backup', 'category' => 'Proteção de dados'],
            ['name' => 'Firewall', 'category' => 'Segurança'],
            ['name' => 'Monitoramento', 'category' => 'Infraestrutura'],
            ['name' => 'Servidor de arquivos', 'category' => 'Infraestrutura'],
            ['name' => 'Aplicação interna', 'category' => 'Aplicação'],
            ['name' => 'Banco de dados', 'category' => 'Banco de dados'],
        ])->map(fn (array $item): array => $item + ['created_at' => now(), 'updated_at' => now(), 'active' => true])->all());

        if (Schema::hasTable('cmdb_servers') && ! Schema::hasColumn('cmdb_servers', 'service_id')) {
            Schema::table('cmdb_servers', function (Blueprint $table): void {
                $table->foreignId('service_id')->nullable()->after('asset_id')->constrained('cmdb_services')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cmdb_servers') && Schema::hasColumn('cmdb_servers', 'service_id')) {
            Schema::table('cmdb_servers', function (Blueprint $table): void {
                $table->dropForeign(['service_id']);
                $table->dropColumn('service_id');
            });
        }

        Schema::dropIfExists('cmdb_services');
    }
};
