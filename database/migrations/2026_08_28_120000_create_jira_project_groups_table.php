<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jira_project_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 255);
            $table->string('jira_project_id', 100)->nullable();
            $table->string('jira_group_id', 100)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique('name', 'jira_project_groups_name_unique');
            $table->index('jira_project_id');
            $table->index('jira_group_id');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jira_project_groups');
    }
};
