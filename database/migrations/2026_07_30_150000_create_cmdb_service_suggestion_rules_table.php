<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cmdb_service_suggestion_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('pattern', 120);
            $table->string('match_type', 20)->default('contains');
            $table->foreignId('service_id')->constrained('cmdb_services')->cascadeOnDelete();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('active')->default(true);
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['active', 'priority']);
        });

        $services = DB::table('cmdb_services')->pluck('id', 'name');
        $rules = [
            ['sap', 'SAP'], ['citrix', 'Citrix'], ['japaoctx', 'Citrix'],
            ['zabbix', 'Zabbix'], ['n8n', 'N8N'], ['sql', 'Microsoft SQL Server'],
            ['dns', 'DNS'], ['dhcp', 'DHCP'], ['backup', 'Backup'],
        ];

        foreach ($rules as $index => [$pattern, $service]) {
            if (! isset($services[$service])) continue;
            DB::table('cmdb_service_suggestion_rules')->insert([
                'pattern' => $pattern,
                'match_type' => 'contains',
                'service_id' => $services[$service],
                'priority' => $index + 10,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cmdb_service_suggestion_rules');
    }
};
