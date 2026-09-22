<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jira_projects', function (Blueprint $table): void {
            $table->unsignedBigInteger('jira_project_group_id')->nullable()->after('projeto_grupo');
            $table->index('jira_project_group_id', 'jira_projects_group_id_idx');
        });

        $groups = DB::table('jira_projects')
            ->whereNotNull('projeto_grupo')
            ->where('projeto_grupo', '<>', '')
            ->distinct()
            ->pluck('projeto_grupo');

        foreach ($groups as $name) {
            DB::table('jira_project_groups')->updateOrInsert(
                ['name' => trim((string) $name)],
                ['active' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        DB::statement(
            'UPDATE jira_projects p INNER JOIN jira_project_groups g ON g.name = p.projeto_grupo SET p.jira_project_group_id = g.id WHERE p.projeto_grupo IS NOT NULL AND p.projeto_grupo <> ""'
        );
    }

    public function down(): void
    {
        Schema::table('jira_projects', function (Blueprint $table): void {
            $table->dropIndex('jira_projects_group_id_idx');
            $table->dropColumn('jira_project_group_id');
        });
    }
};
