<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE bancada_equipments DROP INDEX IF EXISTS bancada_equipments_plaqueta_unique');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE bancada_equipments ADD UNIQUE INDEX bancada_equipments_plaqueta_unique (plaqueta)');
    }
};
