<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE circuitos_unidades MODIFY operadora VARCHAR(100) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE circuitos_unidades MODIFY operadora VARCHAR(50) NOT NULL');
    }
};
