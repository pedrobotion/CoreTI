<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('jira_project_groups')->updateOrInsert(
            ['name' => 'Pivot'],
            ['active' => true, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('jira_project_groups')->where('name', 'Pivot')->delete();
    }
};
