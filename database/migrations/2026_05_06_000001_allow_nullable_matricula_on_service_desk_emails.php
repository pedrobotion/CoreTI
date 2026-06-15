<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE service_desk_emails MODIFY matricula VARCHAR(20) NULL');
        DB::table('service_desk_emails')
            ->where('matricula', 'like', 'LEGACY-%')
            ->update(['matricula' => null]);
    }

    public function down(): void
    {
        DB::table('service_desk_emails')
            ->whereNull('matricula')
            ->update(['matricula' => '']);

        DB::statement('ALTER TABLE service_desk_emails MODIFY matricula VARCHAR(20) NOT NULL');
    }
};
