<?php

use App\Support\ModuleRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normaliza as permissões de módulo.
 *
 * Antes: uma coluna booleana por módulo em user_module_permissions, o que
 * exigia migration a cada módulo novo e só permitia "tem ou não tem acesso".
 * Agora: uma linha por (usuário, módulo, permissão), permitindo níveis
 * diferentes dentro do mesmo módulo — necessário para o RH registrar
 * admissões sem enxergar o resto da TI.
 *
 * A tabela antiga é preservada intacta para rollback; o backfill abaixo apenas
 * a lê.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_module_accesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module', 64);
            $table->string('ability', 32)->default('view');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'module', 'ability']);
            $table->index(['module', 'ability']);
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_module_accesses');
    }

    /**
     * Converte o estado atual para o modelo novo sem que ninguém perca acesso:
     *  - cada booleano true vira 'view' + 'manage' (era o que o booleano dava);
     *  - cmdb e network_datacenter continuam se implicando mutuamente, como o
     *    User::hasModuleAccess() legado fazia;
     *  - role = admin recebe concessão explícita de tudo, para que o bypass
     *    legado possa ser desligado com segurança depois.
     */
    private function backfill(): void
    {
        $now = now();
        $rows = [];

        $push = function (int $userId, string $module, string $ability) use (&$rows, $now): void {
            $key = $userId.'|'.$module.'|'.$ability;
            $rows[$key] = [
                'user_id' => $userId,
                'module' => $module,
                'ability' => $ability,
                'granted_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        };

        if (Schema::hasTable('user_module_permissions')) {
            $legacyColumns = array_values(array_filter(
                ModuleRegistry::keys(),
                fn (string $module) => Schema::hasColumn('user_module_permissions', $module)
            ));

            foreach (DB::table('user_module_permissions')->get() as $permission) {
                $userId = (int) $permission->user_id;

                foreach ($legacyColumns as $module) {
                    if (! (bool) ($permission->{$module} ?? false)) {
                        continue;
                    }

                    foreach (['view', 'manage'] as $ability) {
                        if (ModuleRegistry::supportsAbility($module, $ability)) {
                            $push($userId, $module, $ability);
                        }
                    }
                }

                // Equivalência histórica entre CMDB e Network/Datacenter.
                $infra = (bool) ($permission->cmdb ?? false) || (bool) ($permission->network_datacenter ?? false);
                if ($infra) {
                    foreach (['cmdb', 'network_datacenter'] as $module) {
                        foreach (['view', 'manage'] as $ability) {
                            $push($userId, $module, $ability);
                        }
                    }
                }
            }
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role')) {
            $adminIds = DB::table('users')->where('role', 'admin')->pluck('id');

            foreach ($adminIds as $adminId) {
                foreach (ModuleRegistry::keys() as $module) {
                    foreach (ModuleRegistry::abilities($module) as $ability) {
                        $push((int) $adminId, $module, $ability);
                    }
                }
            }
        }

        foreach (array_chunk(array_values($rows), 500) as $chunk) {
            DB::table('user_module_accesses')->insert($chunk);
        }
    }
};
