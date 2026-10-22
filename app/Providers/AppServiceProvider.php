<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Database\GuardaDeComandosPerigosos;
use App\Policies\UserPolicy;
use App\Support\ModuleRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);

        $this->registerModuleGates();
        $this->registerDisplayTimezone();

        // Espelha cada passo do equipamento no chamado de manutenção do Jira.
        \App\Models\BancadaEquipmentEvent::observe(\App\Observers\BancadaEquipmentEventObserver::class);

        // Enquanto a credencial do banco não for escopada, anota (e
        // opcionalmente recusa) comandos que nenhuma tela emite.
        GuardaDeComandosPerigosos::registrar();
    }

    /**
     * Macro ->local(): converte uma data guardada em UTC para o fuso de
     * exibição.
     *
     * Só use em coluna comprovadamente UTC. Este banco tem colunas escritas
     * por processos externos (n8n, sincronização do AD) que já gravam hora
     * local — aplicar a conversão nelas inverteria o erro em vez de corrigi-lo.
     */
    private function registerDisplayTimezone(): void
    {
        Carbon::macro('local', function () {
            /** @var Carbon $this */
            return $this->copy()->setTimezone(
                config('app.display_timezone', config('app.timezone'))
            );
        });
    }

    /**
     * Publica um gate por (módulo, permissão), permitindo nas views:
     *   @can('module.admissao.request') ... @endcan
     */
    private function registerModuleGates(): void
    {
        foreach (ModuleRegistry::all() as $module => $definition) {
            foreach (ModuleRegistry::abilities($module) as $ability) {
                Gate::define(
                    "module.{$module}.{$ability}",
                    fn (User $user) => $user->canModule($module, $ability)
                );
            }
        }
    }
}
