<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BackupWebhookController;
use App\Http\Controllers\CmdbBackupTargetController;
use App\Http\Controllers\CmdbDatabaseBackupController;
use App\Http\Controllers\AdmissionCaseController;
use App\Http\Controllers\AdmissionProfileController;
use App\Http\Controllers\AdmissionTaskController;
use App\Http\Controllers\CircuitController;
use App\Http\Controllers\AssetAssignmentController;
use App\Http\Controllers\CorpDeviceController;
use App\Http\Controllers\CorpLineController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\TelefoniaFaturaController;
use App\Http\Controllers\TelefoniaRelatorioController;
use App\Http\Controllers\CoordenacaoController;
use App\Http\Controllers\CmdbAssetController;
use App\Http\Controllers\CmdbAssetDocumentController;
use App\Http\Controllers\CmdbAssetHistoryController;
use App\Http\Controllers\CmdbController;
use App\Http\Controllers\CmdbDiscoveredAssetController;
use App\Http\Controllers\CmdbConnectorController;
use App\Http\Controllers\CmdbImportController;
use App\Http\Controllers\CmdbImportBatchController;
use App\Http\Controllers\CmdbDiscoveryController;
use App\Http\Controllers\CmdbManufacturerController;
use App\Http\Controllers\CmdbModelController;
use App\Http\Controllers\CmdbQuickCreateController;
use App\Http\Controllers\CmdbSyncJobController;
use App\Http\Controllers\CmdbSyncLogController;
use App\Http\Controllers\CmdbIpAddressController;
use App\Http\Controllers\CmdbExpirationController;
use App\Http\Controllers\CmdbRackController;
use App\Http\Controllers\CmdbRelationshipController;
use App\Http\Controllers\CmdbServiceController;
use App\Http\Controllers\CmdbServiceSuggestionRuleController;
use App\Http\Controllers\CmdbPendingController;
use App\Http\Controllers\CmdbVlanController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Ad\AdPurgeApiController;
use App\Http\Controllers\Ad\AdPurgeController;
use App\Http\Controllers\Ad\AdOfficeRuleController;
use App\Http\Controllers\Ad\AdCadastroIncompletoController;
use App\Http\Controllers\ServiceDeskController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\BancadaServicosController;
use App\Http\Controllers\NetworkContratanteController;
use App\Http\Controllers\NetworkDatacenterController;
use App\Http\Controllers\NetworkProdutoController;
use App\Http\Controllers\NetworkServicoConectividadeController;
use App\Http\Controllers\TiCreditCardController;
use App\Http\Controllers\JiraProjectController;
use App\Http\Controllers\LicensingController;
use App\Http\Controllers\OfficeSyncChangeRequestController;
use App\Http\Controllers\OfficeAllocationController;
use App\Http\Controllers\OfficeLicensePriceController;
use App\Http\Controllers\UnidadeDigitalController;
use App\Http\Controllers\CoreTiNotificationSettingsController;
use App\Http\Controllers\AdministrativeCostCenterController;

// Raiz: visitante -> /login | autenticado -> /home
Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('home')
        : redirect()->route('login');
});

// Área autenticada
/*
 | LEGADO — webhook de auditoria de backup.
 |
 | Mantido apenas por compatibilidade com agentes que ainda possam postar aqui.
 | NÃO é mais fonte primária: a auditoria roda por conta própria em
 | backups:verificar, a partir do cadastro de CmdbBackupTarget.
 |
 | Desligar o n8n e todos os agentes não afeta a auditoria.
 | Pode ser removido quando se confirmar que ninguém mais envia eventos.
 */
Route::post('/webhooks/backup', [BackupWebhookController::class, 'store'])
    ->middleware(['backup_webhook', 'throttle:120,1,webhook-backup:'])
    ->name('webhooks.backup');

/*
 | Expurgo do Active Directory — endpoints consumidos pelo servidor do AD.
 |
 | O sentido é sempre AD → CoreTI. O CoreTI não abre conexão com o domínio e
 | não guarda credencial capaz de apagar contas: responde apenas o que uma
 | pessoa aprovou na tela.
 */
/*
 | Log de impressão — endpoints consumidos pelos servidores IMP01/02/03.
 |
 | O sentido é sempre servidor → CoreTI, como no AD: o CoreTI não abre conexão
 | com os servidores nem guarda credencial de domínio.
 */
Route::prefix('api/impressao')
    ->middleware(['impressao_token', 'throttle:120,1,api-impressao:'])
    ->name('api.impressao.')->group(function () {
        Route::post('/lote', [\App\Http\Controllers\Impressao\PrintLogApiController::class, 'lote'])->name('lote');
        Route::get('/ultimo-evento', [\App\Http\Controllers\Impressao\PrintLogApiController::class, 'ultimoEvento'])->name('ultimo-evento');
    });

Route::prefix('api/ad/exclusao')
    // O token tem 64 caracteres e a comparação é hash_equals, então adivinhar
    // é inviável — mas endpoint sem limite ainda serve de canal de carga e
    // esconde tentativa persistente no meio do ruído.
    ->middleware(['ad_purge_token', 'throttle:60,1,api-ad:'])
    ->name('api.ad.exclusao.')->group(function () {
    Route::post('/previa', [AdPurgeApiController::class, 'previa'])->name('previa');
    Route::get('/aprovado', [AdPurgeApiController::class, 'aprovado'])->name('aprovado');
    Route::post('/resultado', [AdPurgeApiController::class, 'resultado'])->name('resultado');
});

/*
 | Rotas de vínculo, iguais nos dois contextos.
 |
 | Telefonia e Equipamentos usam o mesmo controller — um vínculo, N itens, um
 | termo, um desvínculo. O que muda é o prefixo, o módulo que autoriza e quais
 | tipos de item cada tela oferece. Declarar as duas listas à mão deixaria uma
 | divergir da outra na primeira manutenção; o contexto viaja como default de
 | rota e chega ao controller como parâmetro.
 */
if (! function_exists('rotasDeVinculo')) {
    function rotasDeVinculo(string $contexto, string $permissaoDeEscrita): Closure
    {
        return function () use ($contexto, $permissaoDeEscrita): void {
            $comContexto = fn (string $metodo) => [AssetAssignmentController::class, $metodo];

            // Leitura: basta o módulo.
            Route::get('/', $comContexto('index'))->defaults('contexto', $contexto)->name('index');
            Route::get('/buscar-colaboradores', $comContexto('lookupColaboradores'))->defaults('contexto', $contexto)->name('lookup.colaboradores');
            Route::get('/exportar/excel', $comContexto('exportExcel'))->defaults('contexto', $contexto)->name('export.excel');
            Route::get('/exportar/pdf', $comContexto('exportPdf'))->defaults('contexto', $contexto)->name('export.pdf');
            Route::get('/{assignment}', $comContexto('show'))->defaults('contexto', $contexto)->whereNumber('assignment')->name('show');
            Route::get('/{assignment}/termo', $comContexto('termo'))->defaults('contexto', $contexto)->whereNumber('assignment')->name('termo');
            Route::get('/{assignment}/termo/assinado', $comContexto('verTermo'))->defaults('contexto', $contexto)->whereNumber('assignment')->name('termo.ver');

            // Escrita: exige 'manage' no módulo do contexto.
            Route::middleware('module_access:'.$permissaoDeEscrita)->group(function () use ($comContexto, $contexto): void {
                Route::get('/novo/registrar', $comContexto('create'))->defaults('contexto', $contexto)->name('create');
                Route::post('/', $comContexto('store'))->defaults('contexto', $contexto)->name('store');
                Route::get('/{assignment}/editar', $comContexto('edit'))->defaults('contexto', $contexto)->whereNumber('assignment')->name('edit');
                Route::put('/{assignment}', $comContexto('update'))->defaults('contexto', $contexto)->whereNumber('assignment')->name('update');
                Route::delete('/{assignment}', $comContexto('destroy'))->defaults('contexto', $contexto)->whereNumber('assignment')->name('destroy');
                Route::post('/{assignment}/termo/assinado', $comContexto('uploadTermo'))->defaults('contexto', $contexto)->whereNumber('assignment')->name('termo.upload');
                Route::delete('/{assignment}/termo/assinado', $comContexto('excluirTermo'))->defaults('contexto', $contexto)->whereNumber('assignment')->name('termo.excluir');
                Route::get('/{assignment}/devolucao', $comContexto('desvinculoForm'))->defaults('contexto', $contexto)->whereNumber('assignment')->name('desvinculo');
                Route::post('/{assignment}/devolucao', $comContexto('desvincular'))->defaults('contexto', $contexto)->whereNumber('assignment')->name('desvincular');
            });
        };
    }
}

Route::middleware(['auth', 'active'])->group(function () {
    // Home principal
    Route::get('/home', HomeController::class)->name('home');

    // Alias: qualquer redirect para 'dashboard' cai em /home
    Route::redirect('/dashboard', '/home')->name('dashboard');

    /*
     | Cadastro de colaborador no AD: pendências e centro de custo por OU.
     |
     | Vivem sob o módulo ServiceDesk, na seção AD, junto do painel de contas —
     | é a mesma pessoa que consulta o diretório e mantém esses dados.
     |
     | A Exclusão de contas ficou de fora: é operação destrutiva e continua com
     | módulo próprio, para poder ser concedida a menos gente.
     */
    Route::prefix('service-desk/ad/cadastro-incompleto')->name('service-desk.ad.cadastro-incompleto.')->middleware('module_access:servicedesk')->group(function () {
        Route::get('/', [AdCadastroIncompletoController::class, 'index'])->name('index');
        Route::get('/exportar', [AdCadastroIncompletoController::class, 'exportar'])->name('exportar');

        Route::middleware('module_access:servicedesk.manage')->group(function () {
            Route::post('/nao-colaborador', [AdCadastroIncompletoController::class, 'marcarNaoColaborador'])->name('nao-colaborador.store');
            Route::delete('/nao-colaborador/{conta}', [AdCadastroIncompletoController::class, 'reverterNaoColaborador'])->name('nao-colaborador.destroy');
        });
    });

    Route::prefix('service-desk/ad/centro-custo')->name('service-desk.ad.centro-custo.')->middleware('module_access:servicedesk')->group(function () {
        Route::get('/', [AdOfficeRuleController::class, 'index'])->name('index');

        Route::middleware('module_access:servicedesk.manage')->group(function () {
            Route::patch('/', [AdOfficeRuleController::class, 'atualizarLote'])->name('atualizar-lote');
            Route::post('/excecoes', [AdOfficeRuleController::class, 'adicionarExcecao'])->name('excecoes.store');
            Route::delete('/excecoes/{excecao}', [AdOfficeRuleController::class, 'removerExcecao'])->name('excecoes.destroy');
            Route::patch('/{regra}', [AdOfficeRuleController::class, 'update'])->name('update');
            Route::post('/{regra}/confirmar', [AdOfficeRuleController::class, 'confirmar'])->name('confirmar');
        });
    });

    Route::prefix('ad/exclusao')->name('ad.exclusao.')->middleware('module_access:ad')->group(function () {
        Route::get('/', [AdPurgeController::class, 'index'])->name('index');
        Route::get('/{lote}', [AdPurgeController::class, 'show'])->name('show');

        // Aprovar exclusão de conta é a ação sensível: exige ad.execute.
        Route::middleware('module_access:ad.execute')->group(function () {
            Route::post('/{lote}/aprovar', [AdPurgeController::class, 'aprovar'])->name('aprovar');
            Route::post('/{lote}/rejeitar', [AdPurgeController::class, 'rejeitar'])->name('rejeitar');
        });
    });

    // Rotas de Perfil (usadas pelo layout do Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('module_access:unidades')->group(function () {
        Route::get('/circuits', [CircuitController::class, 'index'])->name('circuits.index');
        Route::get('/circuits/units', [CircuitController::class, 'units'])->name('circuits.units');
        Route::get('/circuits/units/dashboard', [CircuitController::class, 'unitsDashboard'])->name('circuits.units.dashboard');
        Route::get('/circuits/units/create', [CircuitController::class, 'createUnit'])->name('circuits.units.create');
        Route::get('/circuits/units/lookup/uf', [CircuitController::class, 'lookupUnitUf'])->name('circuits.units.lookup.uf');
        Route::post('/circuits/operators', [CircuitController::class, 'storeOperator'])->name('circuits.operators.store');
        Route::post('/circuits/units', [CircuitController::class, 'storeUnit'])->name('circuits.units.store');
        Route::get('/circuits/units/{unit}/edit', [CircuitController::class, 'editUnit'])->name('circuits.units.edit');
        Route::put('/circuits/units/{unit}', [CircuitController::class, 'updateUnit'])->name('circuits.units.update');
        Route::patch('/circuits/units/{unit}/operacional', [CircuitController::class, 'updateOperational'])->name('circuits.units.operational.update');
        Route::delete('/circuits/units/{unit}', [CircuitController::class, 'destroyUnit'])->name('circuits.units.destroy');
        Route::get('/circuits/ligga', [CircuitController::class, 'ligga'])->name('circuits.ligga');
        Route::get('/circuits/embratel', [CircuitController::class, 'embratel'])->name('circuits.embratel');
        Route::get('/circuits/oi', [CircuitController::class, 'oi'])->name('circuits.oi');
    });

    Route::get('/aplicativos', [ApplicationController::class, 'index'])->middleware('module_access:aplicativos')->name('applications.index');
    Route::middleware('admin')->group(function () {
        Route::middleware('module_access:aplicativos')->group(function () {
            Route::get('/aplicativos/create', [ApplicationController::class, 'create'])->name('applications.create');
            Route::post('/aplicativos', [ApplicationController::class, 'store'])->name('applications.store');
        });
    });
    Route::get('/aplicativos/{application}/download', [ApplicationController::class, 'download'])->middleware('module_access:aplicativos')->name('applications.download');

    /*
     | Admissão de colaboradores.
     |
     | O RH precisa de 'admissao.request' e nada mais — não recebe 'admin' nem
     | acesso aos demais módulos. A execução das tarefas exige 'admissao.execute'
     | (TI e áreas de apoio) e o catálogo de perfis exige 'admissao.manage'.
     */
    Route::prefix('admissao')->name('admissao.')->middleware('module_access:admissao')->group(function () {
        Route::get('/', [AdmissionCaseController::class, 'index'])->name('cases.index');

        Route::get('/fila', [AdmissionTaskController::class, 'board'])->name('tasks.board');

        Route::middleware('module_access:admissao.request')->group(function () {
            Route::get('/casos/novo', [AdmissionCaseController::class, 'create'])->name('cases.create');
            Route::post('/casos', [AdmissionCaseController::class, 'store'])->name('cases.store');
            Route::get('/colaboradores/lookup', [AdmissionCaseController::class, 'lookupCollaborator'])->name('collaborators.lookup');
        });

        Route::get('/casos/{case}', [AdmissionCaseController::class, 'show'])->name('cases.show');

        Route::middleware('module_access:admissao.request')->group(function () {
            Route::get('/casos/{case}/editar', [AdmissionCaseController::class, 'edit'])->name('cases.edit');
            Route::put('/casos/{case}', [AdmissionCaseController::class, 'update'])->name('cases.update');
            Route::post('/casos/{case}/enviar', [AdmissionCaseController::class, 'submit'])->name('cases.submit');
        });

        Route::post('/casos/{case}/cancelar', [AdmissionCaseController::class, 'cancel'])->name('cases.cancel');

        Route::middleware('module_access:admissao.execute')->group(function () {
            Route::post('/casos/{case}/reenviar-chamados', [AdmissionCaseController::class, 'redispatch'])->name('cases.redispatch');
            Route::post('/tarefas/{task}/concluir', [AdmissionTaskController::class, 'complete'])->name('tasks.complete');
            Route::post('/tarefas/{task}/bloquear', [AdmissionTaskController::class, 'block'])->name('tasks.block');
            Route::post('/tarefas/{task}/dispensar', [AdmissionTaskController::class, 'skip'])->name('tasks.skip');
        });

        Route::middleware('module_access:admissao.manage')->group(function () {
            Route::get('/perfis', [AdmissionProfileController::class, 'index'])->name('profiles.index');
            Route::post('/perfis', [AdmissionProfileController::class, 'store'])->name('profiles.store');
            Route::put('/perfis/{profile}', [AdmissionProfileController::class, 'update'])->name('profiles.update');
            Route::patch('/perfis/{profile}/toggle', [AdmissionProfileController::class, 'toggle'])->name('profiles.toggle');
        });
    });

    Route::prefix('coordenacao')->name('coordenacao.')->middleware('module_access:coordenacao')->group(function () {
        Route::get('/', [CoordenacaoController::class, 'dashboard'])->name('dashboard');
        Route::get('/dashboard', [CoordenacaoController::class, 'dashboard'])->name('index');
        Route::get('/colaboradores', [CoordenacaoController::class, 'collaborators'])->name('collaborators');
        Route::get('/unidades', [CoordenacaoController::class, 'units'])->name('units');
        Route::get('/regras-de-custo', [CoordenacaoController::class, 'rules'])->name('rules');
        Route::post('/regras-de-custo', [CoordenacaoController::class, 'storeRule'])->name('rules.store');
        Route::patch('/regras-de-custo/{rule}', [CoordenacaoController::class, 'updateRule'])->name('rules.update');
        Route::patch('/regras-de-custo/{rule}/toggle', [CoordenacaoController::class, 'toggleRule'])->name('rules.toggle');
        Route::get('/exportar', [CoordenacaoController::class, 'export'])->name('export');
        Route::get('/produtos', [\App\Http\Controllers\CoordenacaoProductController::class, 'index'])->name('products.index');
        Route::get('/produtos/create', [\App\Http\Controllers\CoordenacaoProductController::class, 'create'])->middleware('admin')->name('products.create');
        Route::post('/produtos', [\App\Http\Controllers\CoordenacaoProductController::class, 'store'])->middleware('admin')->name('products.store');
        Route::get('/produtos/{product}/edit', [\App\Http\Controllers\CoordenacaoProductController::class, 'edit'])->middleware('admin')->name('products.edit');
        Route::patch('/produtos/{product}', [\App\Http\Controllers\CoordenacaoProductController::class, 'update'])->middleware('admin')->name('products.update');
        Route::patch('/produtos/{product}/toggle', [\App\Http\Controllers\CoordenacaoProductController::class, 'toggle'])->middleware('admin')->name('products.toggle');
        Route::post('/produtos/{product}/duplicate', [\App\Http\Controllers\CoordenacaoProductController::class, 'duplicate'])->middleware('admin')->name('products.duplicate');
        Route::get('/rateio', [\App\Http\Controllers\CoordenacaoRateioController::class, 'index'])->name('rateio.index');
        Route::get('/rateio/unidades/{unitKey}', [\App\Http\Controllers\CoordenacaoRateioController::class, 'unit'])->name('rateio.unit');
        Route::get('/pendencias', [\App\Http\Controllers\CoordenacaoPendingController::class, 'index'])->name('pending.index');
        Route::patch('/pendencias/{item}/resolver', [\App\Http\Controllers\CoordenacaoPendingController::class, 'resolve'])->middleware('admin')->name('pending.resolve');
        Route::get('/fechamentos', [\App\Http\Controllers\CoordenacaoClosingController::class, 'index'])->name('closings.index');
        Route::post('/fechamentos', [\App\Http\Controllers\CoordenacaoClosingController::class, 'store'])->middleware('admin')->name('closings.store');
        Route::get('/fechamentos/{closing}', [\App\Http\Controllers\CoordenacaoClosingController::class, 'show'])->name('closings.show');
        Route::post('/fechamentos/{closing}/calcular', [\App\Http\Controllers\CoordenacaoClosingController::class, 'calculate'])->middleware('admin')->name('closings.calculate');
        Route::post('/fechamentos/{closing}/revisar', [\App\Http\Controllers\CoordenacaoClosingController::class, 'review'])->middleware('admin')->name('closings.review');
        Route::post('/fechamentos/{closing}/fechar', [\App\Http\Controllers\CoordenacaoClosingController::class, 'close'])->middleware('admin')->name('closings.close');
        Route::post('/fechamentos/{closing}/reabrir', [\App\Http\Controllers\CoordenacaoClosingController::class, 'reopen'])->middleware('admin')->name('closings.reopen');
    });

    Route::prefix('cmdb')->name('cmdb.')->middleware('module_access:cmdb,network_datacenter')->group(function () {
        Route::get('/', [CmdbController::class, 'dashboard'])->name('dashboard');
        Route::get('/dashboard', [CmdbController::class, 'dashboard'])->name('index');
        Route::get('/quick-create', [CmdbQuickCreateController::class, 'index'])->name('quick-create.index');
        Route::get('/quick-create/create', [CmdbQuickCreateController::class, 'create'])->name('quick-create.create');
        Route::post('/quick-create', [CmdbQuickCreateController::class, 'store'])->name('quick-create.store');
        Route::get('/import', [CmdbImportController::class, 'index'])->name('import.index');
        Route::get('/import/template', [CmdbImportController::class, 'template'])->name('import.template');
        Route::post('/import/preview', [CmdbImportController::class, 'preview'])->name('import.preview');
        Route::post('/import/{batch}/confirm', [CmdbImportController::class, 'confirm'])->name('import.confirm');
        Route::get('/importacoes/lotes', [CmdbImportBatchController::class, 'index'])->name('import.batches.index');
        Route::get('/importacoes/lotes/{batch}', [CmdbImportBatchController::class, 'show'])->name('import.batches.show');
        Route::get('/importacoes/lotes/{batch}/exportar', [CmdbImportBatchController::class, 'export'])->name('import.batches.export');
        Route::post('/importacoes/lotes/{batch}/reprocessar', [CmdbImportBatchController::class, 'reprocess'])->name('import.batches.reprocess');
        Route::get('/importacoes/lotes/{batch}/resultado', [CmdbImportBatchController::class, 'result'])->name('import.batches.result');
        Route::get('/discovery', [CmdbDiscoveryController::class, 'index'])->name('discovery.index');
        Route::get('/operations', [CmdbController::class, 'operations'])->name('operations');
        Route::get('/pendencias', [CmdbPendingController::class, 'index'])->name('pending.index');
        Route::get('/pendencias/servicos', [CmdbPendingController::class, 'services'])->name('pending.services');
        Route::post('/pendencias/servicos', [CmdbPendingController::class, 'bulkService'])->name('pending.bulk-service');
        Route::get('/relatorios', [CmdbController::class, 'reports'])->name('reports');
        Route::get('/servicos', [CmdbServiceController::class, 'index'])->name('services.index');
        Route::post('/servicos', [CmdbServiceController::class, 'store'])->name('services.store');
        Route::patch('/servicos/{service}', [CmdbServiceController::class, 'update'])->name('services.update');
        Route::patch('/servicos/{service}/toggle', [CmdbServiceController::class, 'toggle'])->name('services.toggle');
        Route::get('/configuracoes/regras-sugestao', [CmdbServiceSuggestionRuleController::class, 'index'])->name('service-rules.index');
        Route::post('/configuracoes/regras-sugestao', [CmdbServiceSuggestionRuleController::class, 'store'])->name('service-rules.store');
        Route::patch('/configuracoes/regras-sugestao/{rule}', [CmdbServiceSuggestionRuleController::class, 'update'])->name('service-rules.update');
        Route::patch('/configuracoes/regras-sugestao/{rule}/toggle', [CmdbServiceSuggestionRuleController::class, 'toggle'])->name('service-rules.toggle');
        Route::view('/links-circuitos', 'cmdb.links.index')->name('links.index');
        Route::get('/vlans', [CmdbVlanController::class, 'index'])->name('vlans.index');

        Route::get('/fabricantes', [CmdbManufacturerController::class, 'index'])->name('manufacturers.index');
        Route::get('/fabricantes/create', [CmdbManufacturerController::class, 'create'])->name('manufacturers.create');
        Route::post('/fabricantes', [CmdbManufacturerController::class, 'store'])->name('manufacturers.store');
        Route::get('/fabricantes/{manufacturer}', [CmdbManufacturerController::class, 'show'])->name('manufacturers.show');
        Route::get('/fabricantes/{manufacturer}/edit', [CmdbManufacturerController::class, 'edit'])->name('manufacturers.edit');
        Route::put('/fabricantes/{manufacturer}', [CmdbManufacturerController::class, 'update'])->name('manufacturers.update');
        Route::delete('/fabricantes/{manufacturer}', [CmdbManufacturerController::class, 'destroy'])->name('manufacturers.destroy');

        Route::get('/modelos', [CmdbModelController::class, 'index'])->name('models.index');
        Route::get('/modelos/create', [CmdbModelController::class, 'create'])->name('models.create');
        Route::post('/modelos', [CmdbModelController::class, 'store'])->name('models.store');
        Route::get('/modelos/{model}', [CmdbModelController::class, 'show'])->name('models.show');
        Route::get('/modelos/{model}/edit', [CmdbModelController::class, 'edit'])->name('models.edit');
        Route::put('/modelos/{model}', [CmdbModelController::class, 'update'])->name('models.update');
        Route::delete('/modelos/{model}', [CmdbModelController::class, 'destroy'])->name('models.destroy');

        Route::get('/connectors', [CmdbConnectorController::class, 'index'])->name('connectors.index');
        Route::get('/connectors/create', [CmdbConnectorController::class, 'create'])->name('connectors.create');
        Route::post('/connectors', [CmdbConnectorController::class, 'store'])->name('connectors.store');
        Route::get('/connectors/{connector}', [CmdbConnectorController::class, 'show'])->name('connectors.show');
        Route::get('/connectors/{connector}/edit', [CmdbConnectorController::class, 'edit'])->name('connectors.edit');
        Route::put('/connectors/{connector}', [CmdbConnectorController::class, 'update'])->name('connectors.update');
        Route::delete('/connectors/{connector}', [CmdbConnectorController::class, 'destroy'])->name('connectors.destroy');
        Route::post('/connectors/{connector}/test', [CmdbConnectorController::class, 'test'])->name('connectors.test');
        Route::post('/connectors/{connector}/sync-now', [CmdbConnectorController::class, 'syncNow'])->name('connectors.sync-now');

        Route::get('/sync-jobs', [CmdbSyncJobController::class, 'index'])->name('sync-jobs.index');
        Route::get('/sync-jobs/create', [CmdbSyncJobController::class, 'create'])->name('sync-jobs.create');
        Route::post('/sync-jobs', [CmdbSyncJobController::class, 'store'])->name('sync-jobs.store');
        Route::get('/sync-jobs/{job}', [CmdbSyncJobController::class, 'show'])->name('sync-jobs.show');
        Route::get('/sync-jobs/{job}/edit', [CmdbSyncJobController::class, 'edit'])->name('sync-jobs.edit');
        Route::put('/sync-jobs/{job}', [CmdbSyncJobController::class, 'update'])->name('sync-jobs.update');
        Route::delete('/sync-jobs/{job}', [CmdbSyncJobController::class, 'destroy'])->name('sync-jobs.destroy');
        Route::post('/sync-jobs/{job}/run-now', [CmdbSyncJobController::class, 'runNow'])->name('sync-jobs.run-now');

        Route::get('/sync-logs', [CmdbSyncLogController::class, 'index'])->name('sync-logs.index');
        Route::get('/sync-logs/{syncLog}', [CmdbSyncLogController::class, 'show'])->name('sync-logs.show');
        Route::get('/descobertas', [CmdbDiscoveredAssetController::class, 'index'])->name('discovered-assets.index');
        Route::get('/descobertas/{discoveredAsset}', [CmdbDiscoveredAssetController::class, 'show'])->name('discovered-assets.show');
        Route::post('/descobertas/{discoveredAsset}/vincular', [CmdbDiscoveredAssetController::class, 'link'])->name('discovered-assets.link');
        Route::get('/descobertas/{discoveredAsset}/criar-ativo', [CmdbDiscoveredAssetController::class, 'createAsset'])->name('discovered-assets.create-asset');
        Route::post('/descobertas/{discoveredAsset}/ignorar', [CmdbDiscoveredAssetController::class, 'ignore'])->name('discovered-assets.ignore');
        Route::post('/descobertas/{discoveredAsset}/reabrir', [CmdbDiscoveredAssetController::class, 'reopen'])->name('discovered-assets.reopen');
        Route::post('/descobertas/lote/ignorar', [CmdbDiscoveredAssetController::class, 'bulkIgnore'])->name('discovered-assets.bulk-ignore');
        Route::post('/descobertas/lote/reabrir', [CmdbDiscoveredAssetController::class, 'bulkReopen'])->name('discovered-assets.bulk-reopen');

        Route::get('/ativos', [CmdbAssetController::class, 'index'])->name('assets.index');
        Route::get('/ativos/exportar', [CmdbAssetController::class, 'export'])->name('assets.export');
        Route::post('/ativos/acoes-lote', [CmdbAssetController::class, 'bulkUpdate'])->name('assets.bulk-update');
        Route::get('/ativos/create', [CmdbAssetController::class, 'create'])->name('assets.create');
        Route::post('/ativos', [CmdbAssetController::class, 'store'])->name('assets.store');
        Route::get('/ativos/{asset}', [CmdbAssetController::class, 'show'])->name('assets.show');
        Route::get('/ativos/{asset}/duplicar', [CmdbQuickCreateController::class, 'duplicate'])->name('assets.duplicate');
        Route::post('/ativos/{asset}/duplicar', [CmdbQuickCreateController::class, 'storeDuplicate'])->name('assets.duplicate.store');
        Route::get('/ativos/{asset}/edit', [CmdbAssetController::class, 'edit'])->name('assets.edit');
        Route::put('/ativos/{asset}', [CmdbAssetController::class, 'update'])->name('assets.update');
        Route::delete('/ativos/{asset}', [CmdbAssetController::class, 'destroy'])->name('assets.destroy');
        Route::get('/ativos/{asset}/dependencies', [CmdbAssetController::class, 'dependencies'])->name('assets.dependencies');
        Route::get('/ativos/{asset}/diagram', [CmdbAssetController::class, 'diagram'])->name('assets.diagram');
        Route::post('/ativos/{asset}/historico', [CmdbAssetHistoryController::class, 'store'])->name('assets.histories.store');
        Route::post('/ativos/{asset}/documentos', [CmdbAssetDocumentController::class, 'store'])->name('assets.documents.store');
        Route::delete('/ativos/{asset}/documentos/{document}', [CmdbAssetDocumentController::class, 'destroy'])->name('assets.documents.destroy');
        Route::get('/ativos/{asset}/documentos/{document}/download', [CmdbAssetDocumentController::class, 'download'])->name('assets.documents.download');

        Route::get('/enderecos-ip', [CmdbIpAddressController::class, 'index'])->name('ip-addresses.index');
        Route::get('/enderecos-ip/create', [CmdbIpAddressController::class, 'create'])->name('ip-addresses.create');
        Route::post('/enderecos-ip', [CmdbIpAddressController::class, 'store'])->name('ip-addresses.store');
        Route::get('/enderecos-ip/{ipAddress}/edit', [CmdbIpAddressController::class, 'edit'])->name('ip-addresses.edit');
        Route::put('/enderecos-ip/{ipAddress}', [CmdbIpAddressController::class, 'update'])->name('ip-addresses.update');
        Route::delete('/enderecos-ip/{ipAddress}', [CmdbIpAddressController::class, 'destroy'])->name('ip-addresses.destroy');

        Route::get('/racks', [CmdbRackController::class, 'index'])->name('racks.index');
        Route::get('/racks/create', [CmdbRackController::class, 'create'])->name('racks.create');
        Route::post('/racks', [CmdbRackController::class, 'store'])->name('racks.store');
        Route::get('/racks/{rack}', [CmdbRackController::class, 'show'])->name('racks.show');
        Route::get('/racks/{rack}/edit', [CmdbRackController::class, 'edit'])->name('racks.edit');
        Route::put('/racks/{rack}', [CmdbRackController::class, 'update'])->name('racks.update');
        Route::delete('/racks/{rack}', [CmdbRackController::class, 'destroy'])->name('racks.destroy');

        /*
         | Auditoria autônoma de backups: o CoreTI mantém o cadastro do que
         | deve existir e verifica sozinho, por SSH. Não depende de webhook.
         */
        Route::get('/backups/alvos', [CmdbBackupTargetController::class, 'index'])->name('backup-targets.index');
        Route::get('/backups/alvos/novo', [CmdbBackupTargetController::class, 'create'])->name('backup-targets.create');
        Route::post('/backups/alvos', [CmdbBackupTargetController::class, 'store'])->name('backup-targets.store');
        Route::get('/backups/alvos/{target}', [CmdbBackupTargetController::class, 'show'])->name('backup-targets.show');
        Route::get('/backups/alvos/{target}/editar', [CmdbBackupTargetController::class, 'edit'])->name('backup-targets.edit');
        Route::put('/backups/alvos/{target}', [CmdbBackupTargetController::class, 'update'])->name('backup-targets.update');
        Route::patch('/backups/alvos/{target}/toggle', [CmdbBackupTargetController::class, 'toggle'])->name('backup-targets.toggle');
        Route::post('/backups/alvos/{target}/testar-conexao', [CmdbBackupTargetController::class, 'testarConexao'])->name('backup-targets.test');
        Route::post('/backups/alvos/{target}/verificar', [CmdbBackupTargetController::class, 'verificarAgora'])->name('backup-targets.verify');

        // LEGADO: histórico recebido por webhook do agente/n8n. Preservado para
        // consulta; não alimenta mais a auditoria.
        Route::get('/backups-banco', [CmdbDatabaseBackupController::class, 'index'])->name('database-backups.index');
        Route::get('/backups-banco/eventos', [CmdbDatabaseBackupController::class, 'events'])->name('database-backups.events');
        Route::get('/backups-banco/{backup}', [CmdbDatabaseBackupController::class, 'show'])->name('database-backups.show');
        Route::post('/backups-banco/{backup}/chamado', [CmdbDatabaseBackupController::class, 'reenviarChamado'])->name('database-backups.jira');

        Route::get('/vencimentos', [CmdbExpirationController::class, 'index'])->name('expirations.index');
        Route::post('/vencimentos', [CmdbExpirationController::class, 'store'])->name('expirations.store');
        Route::patch('/vencimentos/{expiration}', [CmdbExpirationController::class, 'update'])->name('expirations.update');
        Route::patch('/vencimentos/{expiration}/toggle', [CmdbExpirationController::class, 'toggle'])->name('expirations.toggle');
        Route::post('/vencimentos/{expiration}/jira', [CmdbExpirationController::class, 'openJira'])->name('expirations.jira');

        Route::get('/relacionamentos', [CmdbRelationshipController::class, 'index'])->name('relationships.index');
        Route::post('/relacionamentos', [CmdbRelationshipController::class, 'store'])->name('relationships.store');
        Route::get('/relacionamentos/{relationship}/edit', [CmdbRelationshipController::class, 'edit'])->name('relationships.edit');
        Route::put('/relacionamentos/{relationship}', [CmdbRelationshipController::class, 'update'])->name('relationships.update');
        Route::delete('/relacionamentos/{relationship}', [CmdbRelationshipController::class, 'destroy'])->name('relationships.destroy');
    });

    Route::prefix('network-datacenter')->name('network-datacenter.')->middleware('module_access:network_datacenter')->group(function () {
        Route::get('/', [NetworkDatacenterController::class, 'dashboard'])->name('index');
        Route::get('/dashboard', [NetworkDatacenterController::class, 'dashboard'])->name('dashboard');
        Route::get('/servicos', [NetworkServicoConectividadeController::class, 'index'])->name('servicos.index');
        Route::get('/servicos/create', [NetworkServicoConectividadeController::class, 'create'])->name('servicos.create');
        Route::post('/servicos', [NetworkServicoConectividadeController::class, 'store'])->name('servicos.store');
        Route::get('/servicos/{servico}', [NetworkServicoConectividadeController::class, 'show'])->name('servicos.show');
        Route::get('/servicos/{servico}/edit', [NetworkServicoConectividadeController::class, 'edit'])->name('servicos.edit');
        Route::put('/servicos/{servico}', [NetworkServicoConectividadeController::class, 'update'])->name('servicos.update');
        Route::post('/servicos/extrair-ficha', [NetworkServicoConectividadeController::class, 'extractFicha'])->name('servicos.extract-ficha');
        Route::post('/servicos/{servico}/ficha', [NetworkServicoConectividadeController::class, 'uploadFicha'])->name('servicos.ficha.upload');
        Route::get('/servicos/{servico}/ficha/download', [NetworkServicoConectividadeController::class, 'downloadFicha'])->name('servicos.ficha.download');
        Route::post('/servicos/{servico}/extrair-ia', [NetworkServicoConectividadeController::class, 'extractAi'])->name('servicos.ficha.extract');
        Route::get('/localidades/lookup', [NetworkDatacenterController::class, 'lookupLocation'])->name('localidades.lookup');

        Route::get('/contratantes', [NetworkContratanteController::class, 'index'])->name('contratantes.index');
        Route::post('/contratantes', [NetworkContratanteController::class, 'store'])->name('contratantes.store');
        Route::patch('/contratantes/{contratante}', [NetworkContratanteController::class, 'update'])->name('contratantes.update');
        Route::patch('/contratantes/{contratante}/toggle', [NetworkContratanteController::class, 'toggle'])->name('contratantes.toggle');

        Route::get('/produtos', [NetworkProdutoController::class, 'index'])->name('produtos.index');
        Route::post('/produtos', [NetworkProdutoController::class, 'store'])->name('produtos.store');
        Route::patch('/produtos/{produto}', [NetworkProdutoController::class, 'update'])->name('produtos.update');
        Route::patch('/produtos/{produto}/toggle', [NetworkProdutoController::class, 'toggle'])->name('produtos.toggle');
    });

    Route::prefix('service-desk')->name('service-desk.')->middleware('module_access:servicedesk')->group(function () {
        Route::get('/', [ServiceDeskController::class, 'dashboard'])->name('dashboard');
        Route::get('/ad', [\App\Http\Controllers\ServiceDeskAdController::class, 'index'])->name('ad');
        Route::get('/ad/export', [\App\Http\Controllers\ServiceDeskAdController::class, 'export'])->name('ad.export');
        Route::get('/chamados', [ServiceDeskController::class, 'tickets'])->name('tickets');
        Route::get('/minha-fila', [ServiceDeskController::class, 'myQueue'])->name('my-queue');
        Route::post('/minha-fila/emails', [ServiceDeskController::class, 'storeMyQueueEmail'])->name('my-queue.emails.store');
        Route::delete('/minha-fila/emails/{queueEmail}', [ServiceDeskController::class, 'destroyMyQueueEmail'])->name('my-queue.emails.destroy');
        Route::get('/emails/sede', [ServiceDeskController::class, 'emails'])->defaults('scope', 'sede')->name('emails.sede');
        Route::get('/emails/unidades', [ServiceDeskController::class, 'emails'])->defaults('scope', 'unidades')->name('emails.unidades');
        Route::get('/emails/cerrado', [ServiceDeskController::class, 'emails'])->defaults('scope', 'cerrado')->name('emails.cerrado');
        Route::get('/emails/genericos', [ServiceDeskController::class, 'emails'])->defaults('scope', 'genericos')->name('emails.genericos');
        Route::get('/emails/export', [ServiceDeskController::class, 'exportEmails'])->name('emails.export');
        Route::patch('/emails/coreti/{email}', [ServiceDeskController::class, 'updateCoretiGoogleEmail'])->name('emails.coreti.update');
        Route::get('/emails/lookup/collaborator', [ServiceDeskController::class, 'lookupCollaborator'])->name('emails.lookup.collaborator');
        Route::get('/emails/{scope}/lookup/centro-custo', [ServiceDeskController::class, 'lookupCostCenter'])->whereIn('scope', ['sede', 'unidades', 'cerrado', 'genericos'])->name('emails.lookup.cost-center');
        Route::post('/emails/{scope}', [ServiceDeskController::class, 'storeEmail'])->whereIn('scope', ['sede', 'unidades', 'cerrado', 'genericos'])->name('emails.store');
        Route::get('/emails/{scope}/{email}/edit', [ServiceDeskController::class, 'editEmail'])->whereIn('scope', ['sede', 'unidades', 'cerrado', 'genericos'])->name('emails.edit');
        Route::put('/emails/{scope}/{email}', [ServiceDeskController::class, 'updateEmail'])->whereIn('scope', ['sede', 'unidades', 'cerrado', 'genericos'])->name('emails.update');
        Route::patch('/emails/{scope}/{email}/toggle', [ServiceDeskController::class, 'toggleEmail'])->whereIn('scope', ['sede', 'unidades', 'cerrado', 'genericos'])->name('emails.toggle');
        Route::delete('/emails/{scope}/{email}', [ServiceDeskController::class, 'destroyEmail'])->whereIn('scope', ['sede', 'unidades', 'cerrado', 'genericos'])->name('emails.destroy');
        Route::get('/office', [LicensingController::class, 'officeLicensing'])->name('office');
        Route::get('/office/alteracoes-pendentes', [OfficeSyncChangeRequestController::class, 'index'])->name('office.change-requests');
        Route::post('/office/alteracoes-pendentes/{changeRequest}/aprovar', [OfficeSyncChangeRequestController::class, 'approve'])->name('office.change-requests.approve');
        Route::post('/office/alteracoes-pendentes/{changeRequest}/rejeitar', [OfficeSyncChangeRequestController::class, 'reject'])->name('office.change-requests.reject');
        Route::post('/workspace-sync/now', [ServiceDeskController::class, 'syncWorkspaceNow'])->name('workspace-sync.now');
        Route::post('/workspace-sync/preview', [ServiceDeskController::class, 'refreshWorkspacePreview'])->name('workspace-sync.preview');
        Route::post('/workspace-sync/apply', [ServiceDeskController::class, 'applyWorkspaceSync'])->name('workspace-sync.apply');
        Route::view('/configuracoes', 'service-desk.placeholder', [
            'title' => 'Configurações',
            'description' => 'Parâmetros, filas, categorias e permissões do módulo.',
        ])->name('settings');
        Route::middleware('admin')->group(function () {
            Route::get('/configuracoes/notificacoes', [CoreTiNotificationSettingsController::class, 'index'])->name('notification-settings');
            Route::post('/configuracoes/notificacoes/destinatarios', [CoreTiNotificationSettingsController::class, 'storeRecipient'])->name('notification-settings.recipients.store');
            Route::patch('/configuracoes/notificacoes/destinatarios/{recipient}', [CoreTiNotificationSettingsController::class, 'updateRecipient'])->name('notification-settings.recipients.update');
            Route::patch('/configuracoes/notificacoes/destinatarios/{recipient}/toggle', [CoreTiNotificationSettingsController::class, 'toggleRecipient'])->name('notification-settings.recipients.toggle');
            Route::put('/configuracoes/notificacoes/regras', [CoreTiNotificationSettingsController::class, 'updateRules'])->name('notification-settings.rules.update');
        });
    });

    /*
     | Telefonia Corporativa — linhas, aparelhos e vínculos.
     |
     | Mora sob /administrativo para aparecer naquele menu, mas pede
     | 'module_access:telefonia', não 'administrativo': quem cuida da
     | telefonia não precisa enxergar cartão de crédito e licenciamento.
     | Cada escrita pede 'manage', como no resto do Administrativo.
     */
    Route::prefix('administrativo/telefonia')
        ->name('administrativo.telefonia.')
        ->middleware('module_access:telefonia')
        ->group(function () {
            // Painel e relatórios: leitura pura, bastam as permissões do módulo.
            Route::get('/', [TelefoniaRelatorioController::class, 'dashboard'])->name('dashboard');
            Route::prefix('relatorios')->name('relatorios.')->group(function () {
                Route::get('/gastos', [TelefoniaRelatorioController::class, 'gastosMensais'])->name('gastos');
                Route::get('/gastos/exportar/{formato}', [TelefoniaRelatorioController::class, 'exportGastos'])
                    ->whereIn('formato', ['excel', 'pdf'])->name('gastos.export');
                Route::get('/unidades', [TelefoniaRelatorioController::class, 'comparativoUnidades'])->name('unidades');
                Route::get('/unidades/exportar/{formato}', [TelefoniaRelatorioController::class, 'exportComparativo'])
                    ->whereIn('formato', ['excel', 'pdf'])->name('unidades.export');
            });

            // Leitura pede o módulo; toda escrita pede 'manage'. Quem recebe
            // só 'view' consulta e exporta sem poder alterar.
            Route::get('/linhas', [CorpLineController::class, 'index'])->name('lines.index');
            Route::get('/linhas/exportar/excel', [CorpLineController::class, 'exportExcel'])->name('lines.export.excel');
            Route::get('/linhas/exportar/pdf', [CorpLineController::class, 'exportPdf'])->name('lines.export.pdf');
            Route::middleware('module_access:telefonia.manage')->group(function () {
                Route::get('/linhas/novo', [CorpLineController::class, 'create'])->name('lines.create');
                Route::post('/linhas', [CorpLineController::class, 'store'])->name('lines.store');
                Route::get('/linhas/{line}/editar', [CorpLineController::class, 'edit'])->name('lines.edit');
                Route::put('/linhas/{line}', [CorpLineController::class, 'update'])->name('lines.update');
                Route::delete('/linhas/{line}', [CorpLineController::class, 'destroy'])->name('lines.destroy');
            });
            // Depois das rotas literais, para /linhas/novo não cair aqui como {line}.
            Route::get('/linhas/{line}', [CorpLineController::class, 'show'])->whereNumber('line')->name('lines.show');

            Route::get('/aparelhos', [CorpDeviceController::class, 'index'])->name('devices.index');
            Route::get('/aparelhos/exportar/excel', [CorpDeviceController::class, 'exportExcel'])->name('devices.export.excel');
            Route::get('/aparelhos/exportar/pdf', [CorpDeviceController::class, 'exportPdf'])->name('devices.export.pdf');
            Route::middleware('module_access:telefonia.manage')->group(function () {
                Route::get('/aparelhos/novo', [CorpDeviceController::class, 'create'])->name('devices.create');
                Route::post('/aparelhos', [CorpDeviceController::class, 'store'])->name('devices.store');
                Route::get('/aparelhos/{device}/editar', [CorpDeviceController::class, 'edit'])->name('devices.edit');
                Route::put('/aparelhos/{device}', [CorpDeviceController::class, 'update'])->name('devices.update');
                Route::delete('/aparelhos/{device}', [CorpDeviceController::class, 'destroy'])->name('devices.destroy');
            });
            Route::get('/aparelhos/{device}', [CorpDeviceController::class, 'show'])->whereNumber('device')->name('devices.show');

            Route::prefix('vinculos')->name('assignments.')->group(
                rotasDeVinculo(\App\Support\AssignmentContext::TELEFONIA, 'telefonia.manage')
            );

            /*
             | Faturas. A importação tem duas etapas: 'preview' lê o PDF e
             | mostra o que será gravado, 'confirm' grava. Ambas exigem
             | 'manage' — ler a fatura já move o arquivo para a área privada.
             */
            Route::prefix('faturas')->name('faturas.')->group(function () {
                Route::get('/', [TelefoniaFaturaController::class, 'index'])->name('index');
                Route::middleware('module_access:telefonia.manage')->group(function () {
                    Route::get('/importar', [TelefoniaFaturaController::class, 'create'])->name('create');
                    Route::post('/importar', [TelefoniaFaturaController::class, 'preview'])->name('preview');
                    Route::post('/confirmar', [TelefoniaFaturaController::class, 'confirm'])->name('confirm');
                    Route::delete('/{fatura}', [TelefoniaFaturaController::class, 'destroy'])->whereNumber('fatura')->name('destroy');
                });
                Route::get('/{fatura}', [TelefoniaFaturaController::class, 'show'])->whereNumber('fatura')->name('show');
                Route::get('/{fatura}/pdf', [TelefoniaFaturaController::class, 'download'])->whereNumber('fatura')->name('download');
            });
        });

    /*
     | Equipamentos — inventário do parque distribuído pelas unidades.
     |
     | Sob /service-desk para abrir o menu daquele módulo, mas com permissão
     | própria: dar Equipamentos não dá junto e-mails, AD e licenciamento.
     */
    Route::prefix('service-desk/equipamentos')
        ->name('service-desk.equipamentos.')
        ->middleware('module_access:equipamentos')
        ->group(function () {
            Route::get('/', [EquipmentController::class, 'index'])->name('index');
            Route::get('/exportar', [EquipmentController::class, 'export'])->name('export');
            Route::middleware('module_access:equipamentos.manage')->group(function () {
                Route::get('/novo', [EquipmentController::class, 'create'])->name('create');
                Route::post('/', [EquipmentController::class, 'store'])->name('store');
                Route::get('/{equipment}/editar', [EquipmentController::class, 'edit'])->name('edit');
                Route::put('/{equipment}', [EquipmentController::class, 'update'])->name('update');
                Route::delete('/{equipment}', [EquipmentController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('entregas')->name('assignments.')->group(
                rotasDeVinculo(\App\Support\AssignmentContext::EQUIPAMENTOS, 'equipamentos.manage')
            );
        });

    /*
     | Governança, Unidade Digital, Indicadores e Monitoramento.
     |
     | Saíram do grupo Administrativo: não pertencem àquele módulo, e a
     | Governança vai virar módulo próprio. Até lá ficam restritas a
     | administrador — entrar junto com a permissão de Administrativo daria
     | acesso a quem nunca foi autorizado a ver isso.
     */
    Route::middleware('admin')->group(function () {
        Route::view('/governanca', 'panels.governanca')->name('governanca');
        Route::view('/governanca/visao-geral', 'panels.governanca.visao-geral')->name('governanca.visao-geral');
        Route::view('/governanca/politicas', 'panels.governanca.politicas')->name('governanca.politicas');
        Route::view('/governanca/auditorias', 'panels.governanca.auditorias')->name('governanca.auditorias');
        Route::view('/governanca/riscos', 'panels.governanca.riscos')->name('governanca.riscos');

        Route::get('/unidade-digital', [UnidadeDigitalController::class, 'index'])->name('unidade-digital');
        Route::get('/unidade-digital/status', [UnidadeDigitalController::class, 'status'])->name('unidade-digital.status');
        Route::view('/unidade-digital/visao-geral', 'panels.unidade-digital.visao-geral')->name('unidade-digital.visao-geral');
        Route::view('/unidade-digital/projetos', 'panels.unidade-digital.projetos')->name('unidade-digital.projetos');
        Route::view('/unidade-digital/demandas', 'panels.unidade-digital.demandas')->name('unidade-digital.demandas');
        Route::view('/unidade-digital/roadmap', 'panels.unidade-digital.roadmap')->name('unidade-digital.roadmap');

        Route::view('/indicadores', 'panels.indicadores')->name('indicadores');
        Route::view('/indicadores/visao-geral', 'panels.indicadores.visao-geral')->name('indicadores.visao-geral');
        Route::view('/indicadores/tendencias', 'panels.indicadores.tendencias')->name('indicadores.tendencias');
        Route::view('/indicadores/metas', 'panels.indicadores.metas')->name('indicadores.metas');
        Route::view('/indicadores/exportacao', 'panels.indicadores.exportacao')->name('indicadores.exportacao');

        Route::view('/monitoramento', 'panels.monitoramento')->name('monitoramento');
        Route::view('/monitoramento/visao-geral', 'panels.monitoramento.visao-geral')->name('monitoramento.visao-geral');
        Route::view('/monitoramento/alertas', 'panels.monitoramento.alertas')->name('monitoramento.alertas');
        Route::view('/monitoramento/disponibilidade', 'panels.monitoramento.disponibilidade')->name('monitoramento.disponibilidade');
        Route::view('/monitoramento/incidentes', 'panels.monitoramento.incidentes')->name('monitoramento.incidentes');
    });

    Route::prefix('centros-custo')->name('cost-centers.')->middleware('admin')->group(function () {
        Route::get('/', [AdministrativeCostCenterController::class, 'index'])->name('index');
        Route::post('/', [AdministrativeCostCenterController::class, 'store'])->name('store');
        Route::patch('/{center}', [AdministrativeCostCenterController::class, 'update'])->name('update');
        Route::patch('/{center}/toggle', [AdministrativeCostCenterController::class, 'toggle'])->name('toggle');
        Route::delete('/{center}', [AdministrativeCostCenterController::class, 'destroy'])->name('destroy');
    });

    // Mesma correção do grupo Administrativo: a permissão de módulo passa a
    // bastar para consultar, e cada escrita exige 'manage'.
    Route::prefix('service-desk/jira-projetos')->name('jira-projects.')->middleware('module_access:administrativo')->group(function () {
        Route::get('/', [JiraProjectController::class, 'index'])->name('index');
        Route::post('/catalogo', [JiraProjectController::class, 'storeCatalog'])->name('catalog.store')->middleware('module_access:administrativo.manage');
        Route::patch('/catalogo/{jiraProjectGroup}', [JiraProjectController::class, 'updateCatalog'])->name('catalog.update')->middleware('module_access:administrativo.manage');
        Route::delete('/catalogo/{jiraProjectGroup}', [JiraProjectController::class, 'destroyCatalog'])->name('catalog.destroy')->middleware('module_access:administrativo.manage');
        Route::get('/create', [JiraProjectController::class, 'create'])->name('create')->middleware('module_access:administrativo.manage');
        Route::post('/', [JiraProjectController::class, 'store'])->name('store')->middleware('module_access:administrativo.manage');
        Route::get('/{jiraProject}/edit', [JiraProjectController::class, 'edit'])->name('edit')->middleware('module_access:administrativo.manage');
        Route::put('/{jiraProject}', [JiraProjectController::class, 'update'])->name('update')->middleware('module_access:administrativo.manage');
        Route::delete('/{jiraProject}', [JiraProjectController::class, 'destroy'])->name('destroy')->middleware('module_access:administrativo.manage');
    });

    Route::prefix('bancada-servicos')->name('bancada-servicos.')->middleware(['admin', 'module_access:bancada'])->group(function () {
        Route::get('/', [BancadaServicosController::class, 'dashboard'])->name('dashboard');
        Route::get('/chamados', [BancadaServicosController::class, 'tickets'])->name('tickets');
        Route::get('/equipamentos', [BancadaServicosController::class, 'assets'])->name('assets');
        Route::get('/equipamentos/novo', [BancadaServicosController::class, 'createAsset'])->name('assets.create');
        Route::post('/equipamentos', [BancadaServicosController::class, 'storeAsset'])->name('assets.store');
        Route::get('/equipamentos/backup/imprimir-etiqueta', [BancadaServicosController::class, 'printBackupTemplate'])->name('assets.backup.print-template');
        Route::get('/equipamentos/entregues', [BancadaServicosController::class, 'deliveredAssets'])->name('assets.delivered');
        Route::get('/equipamentos/descartados', [BancadaServicosController::class, 'discardedAssets'])->name('assets.discarded');
        Route::get('/equipamentos/backup', [BancadaServicosController::class, 'backupAssets'])->name('assets.backup');
        Route::get('/aguardando-entrega', [BancadaServicosController::class, 'awaitingDelivery'])->name('awaiting-delivery');
        Route::get('/rotas-malote', [BancadaServicosController::class, 'routesConfig'])->name('routes.config');
        Route::post('/rotas-malote', [BancadaServicosController::class, 'storeRouteConfig'])->name('routes.store');
        Route::put('/rotas-malote/{route}', [BancadaServicosController::class, 'updateRouteConfig'])->name('routes.update');
        Route::patch('/rotas-malote/{route}/toggle', [BancadaServicosController::class, 'toggleRouteConfig'])->name('routes.toggle');
        Route::get('/equipamentos/{equipment}/editar', [BancadaServicosController::class, 'editAsset'])->name('assets.edit');
        Route::put('/equipamentos/{equipment}', [BancadaServicosController::class, 'updateAsset'])->name('assets.update');
        Route::get('/equipamentos/{equipment}/historico', [BancadaServicosController::class, 'assetHistory'])->name('assets.history');
        Route::patch('/equipamentos/{equipment}/backup/disponibilidade', [BancadaServicosController::class, 'toggleBackupAvailability'])->name('assets.backup.availability.update');
        Route::patch('/equipamentos/{equipment}/backup/emprestar', [BancadaServicosController::class, 'loanBackupEquipment'])->name('assets.backup.loan');
        Route::patch('/equipamentos/{equipment}/backup', [BancadaServicosController::class, 'updateBackupData'])->name('assets.backup.update');
        Route::patch('/equipamentos/{equipment}/descarte', [BancadaServicosController::class, 'updateDiscardControls'])->name('assets.discard.update');
        Route::patch('/equipamentos/{equipment}/status', [BancadaServicosController::class, 'updateAssetStatus'])->name('assets.status');
        Route::patch('/equipamentos/{equipment}/entrada-realizada', [BancadaServicosController::class, 'markEntryCompleted'])->name('assets.entry.completed');
        Route::patch('/equipamentos/{equipment}/administrativo', [BancadaServicosController::class, 'administrativeProcess'])->name('assets.administrative.process');
        Route::patch('/equipamentos/{equipment}/enviar-cd', [BancadaServicosController::class, 'markSentToCd'])->name('assets.send-to-cd');
        Route::get('/equipamentos/{equipment}/imprimir', [BancadaServicosController::class, 'printLabel'])->name('assets.print');
        Route::get('/impressora', [BancadaServicosController::class, 'printer'])->name('printer');
        Route::get('/sla', [BancadaServicosController::class, 'sla'])->name('sla');
        Route::get('/relatorios', [BancadaServicosController::class, 'reports'])->name('reports');
    });

    Route::get('/bancada-servicos/anexos/{attachment}/download', [BancadaServicosController::class, 'downloadAttachment'])
        ->middleware('auth')
        ->name('bancada-servicos.attachments.download');

    Route::get('/bancada-servicos/equipamentos/{equipment}/documentos', [BancadaServicosController::class, 'getEquipmentDocuments'])
        ->middleware('auth')
        ->name('bancada-servicos.assets.documents');

    // Admin routes
    /*
     | Antes exigia ['admin', 'module_access:administrativo'] — as duas coisas.
     | Conceder o módulo Administrativo não bastava: sem role=admin o usuário
     | via 403, e o menu escondia o link para não oferecer porta fechada.
     |
     | Isso esvaziava o sentido da permissão por módulo. Agora a leitura pede
     | o módulo, e cada escrita pede 'manage' — quem recebe só 'view' consulta
     | sem alterar.
     */
    Route::middleware('module_access:administrativo')->group(function () {
        // Paineis (atalhos principais)
        Route::get('/administrativo', [BancadaServicosController::class, 'administrativePanel'])->name('administrativo');

        // Subpaginas Administrativo
        Route::get('/administrativo/visao-geral', [BancadaServicosController::class, 'administrativeOverview'])->name('administrativo.visao-geral');
        Route::get('/administrativo/entrada-fiscal', [BancadaServicosController::class, 'administrativeEntryFiscal'])->name('administrativo.entrada-fiscal');
        Route::get('/administrativo/terceiros', [BancadaServicosController::class, 'administrativeThirdParties'])->name('administrativo.terceiros');
        Route::get('/administrativo/pecas', [BancadaServicosController::class, 'administrativeParts'])->name('administrativo.pecas');
        Route::get('/administrativo/estoque-interno', [BancadaServicosController::class, 'administrativeInternalStock'])->name('administrativo.estoque-interno');
        Route::get('/administrativo/nota-saida', [BancadaServicosController::class, 'administrativeOutboundNote'])->name('administrativo.nota-saida');
        Route::get('/administrativo/empresas-terceirizadas', [BancadaServicosController::class, 'administrativeThirdPartyCompanies'])->name('administrativo.empresas-terceirizadas');
        Route::get('/administrativo/historico', [BancadaServicosController::class, 'administrativeHistory'])->name('administrativo.historico');
        Route::get('/administrativo/centros-custo', [AdministrativeCostCenterController::class, 'index'])->name('administrativo.cost-centers.index');
        Route::post('/administrativo/centros-custo', [AdministrativeCostCenterController::class, 'store'])->name('administrativo.cost-centers.store')->middleware('module_access:administrativo.manage');
        Route::patch('/administrativo/centros-custo/{center}', [AdministrativeCostCenterController::class, 'update'])->name('administrativo.cost-centers.update')->middleware('module_access:administrativo.manage');
        Route::patch('/administrativo/centros-custo/{center}/toggle', [AdministrativeCostCenterController::class, 'toggle'])->name('administrativo.cost-centers.toggle')->middleware('module_access:administrativo.manage');
        Route::delete('/administrativo/centros-custo/{center}', [AdministrativeCostCenterController::class, 'destroy'])->name('administrativo.cost-centers.destroy')->middleware('module_access:administrativo.manage');
        Route::get('/administrativo/cartao-ti', [TiCreditCardController::class, 'index'])->name('administrativo.cartao-ti.index');
        Route::get('/administrativo/cartao-ti/cartoes/create', [TiCreditCardController::class, 'createCard'])->name('administrativo.cartao-ti.cards.create');
        Route::post('/administrativo/cartao-ti/cartoes', [TiCreditCardController::class, 'storeCard'])->name('administrativo.cartao-ti.cards.store')->middleware('module_access:administrativo.manage');
        Route::get('/administrativo/cartao-ti/cartoes/{card}/edit', [TiCreditCardController::class, 'editCard'])->name('administrativo.cartao-ti.cards.edit');
        Route::patch('/administrativo/cartao-ti/cartoes/{card}', [TiCreditCardController::class, 'updateCard'])->name('administrativo.cartao-ti.cards.update')->middleware('module_access:administrativo.manage');
        Route::patch('/administrativo/cartao-ti/cartoes/{card}/toggle', [TiCreditCardController::class, 'toggleCard'])->name('administrativo.cartao-ti.cards.toggle')->middleware('module_access:administrativo.manage');
        Route::get('/administrativo/cartao-ti/lancamentos/create', [TiCreditCardController::class, 'createPurchase'])->name('administrativo.cartao-ti.purchases.create');
        Route::post('/administrativo/cartao-ti/lancamentos', [TiCreditCardController::class, 'storePurchase'])->name('administrativo.cartao-ti.purchases.store')->middleware('module_access:administrativo.manage');
        Route::get('/administrativo/cartao-ti/lancamentos/{purchase}/edit', [TiCreditCardController::class, 'editPurchase'])->name('administrativo.cartao-ti.purchases.edit');
        Route::patch('/administrativo/cartao-ti/lancamentos/{purchase}', [TiCreditCardController::class, 'updatePurchase'])->name('administrativo.cartao-ti.purchases.update')->middleware('module_access:administrativo.manage');
        Route::get('/administrativo/cartao-ti/lancamentos/{purchase}/fatura', [TiCreditCardController::class, 'downloadInvoice'])->name('administrativo.cartao-ti.purchases.invoice.download');
        Route::post('/administrativo/terceiros/empresas', [BancadaServicosController::class, 'storeThirdPartyCompany'])->name('bancada-servicos.admin.third-party-companies.store')->middleware('module_access:administrativo.manage');
        Route::patch('/administrativo/terceiros/empresas/{company}', [BancadaServicosController::class, 'updateThirdPartyCompany'])->name('bancada-servicos.admin.third-party-companies.update')->middleware('module_access:administrativo.manage');
        Route::patch('/administrativo/terceiros/empresas/{company}/toggle', [BancadaServicosController::class, 'toggleThirdPartyCompany'])->name('bancada-servicos.admin.third-party-companies.toggle')->middleware('module_access:administrativo.manage');
        Route::view('/administrativo/relatorios', 'panels.administrativo.relatorios')->name('administrativo.relatorios');
        Route::view('/administrativo/configuracoes', 'panels.administrativo.configuracoes')->name('administrativo.configuracoes');
        Route::view('/administrativo/solicitacoes', 'panels.administrativo.solicitacoes')->name('administrativo.solicitacoes');
        Route::prefix('/administrativo/licenciamento')->name('administrativo.licensing.')->group(function () {
            Route::get('/email', [LicensingController::class, 'email'])->name('email');
            Route::get('/jira', [LicensingController::class, 'jira'])->name('jira');
            Route::get('/office', [OfficeAllocationController::class, 'index'])->name('office');
            Route::post('/office/gerar', [OfficeAllocationController::class, 'generate'])->name('office.generate')->middleware('module_access:administrativo.manage');
            Route::post('/office/{batch}/reprocessar', [OfficeAllocationController::class, 'reprocess'])->name('office.reprocess')->middleware('module_access:administrativo.manage');
            Route::post('/office/{batch}/validar', [OfficeAllocationController::class, 'validateBatch'])->name('office.validate')->middleware('module_access:administrativo.manage');
            Route::post('/office/{batch}/fechar', [OfficeAllocationController::class, 'close'])->name('office.close')->middleware('module_access:administrativo.manage');
            Route::post('/office/{batch}/cancelar', [OfficeAllocationController::class, 'cancel'])->name('office.cancel')->middleware('module_access:administrativo.manage');
            Route::get('/office/{batch}/exportar', [OfficeAllocationController::class, 'export'])->name('office.allocation.export');
            Route::get('/office/precos', [OfficeLicensePriceController::class, 'index'])->name('office-prices.index');
            Route::post('/office/precos', [OfficeLicensePriceController::class, 'store'])->name('office-prices.store')->middleware('module_access:administrativo.manage');
            Route::patch('/office/precos/{price}', [OfficeLicensePriceController::class, 'update'])->name('office-prices.update')->middleware('module_access:administrativo.manage');
            Route::patch('/office/precos/{price}/encerrar', [OfficeLicensePriceController::class, 'endValidity'])->name('office-prices.end')->middleware('module_access:administrativo.manage');
            Route::get('/office/export', [LicensingController::class, 'exportOfficeLicenses'])->name('office.export');
            Route::get('/office/lookup/matricula', [LicensingController::class, 'lookupOfficeByMatricula'])->name('office.lookup.matricula');
            Route::post('/office', [LicensingController::class, 'storeOffice'])->name('office.store')->middleware('module_access:administrativo.manage');
            Route::post('/office/importar-matriculas', [LicensingController::class, 'importOfficeMatriculasByEmail'])->name('office.import-matriculas')->middleware('module_access:administrativo.manage');
            Route::post('/office/sincronizar-microsoft', [LicensingController::class, 'syncOfficeMicrosoft'])->name('office.sync-microsoft')->middleware('module_access:administrativo.manage');
            Route::patch('/office/{officeLicense}/ajuste-organizacional', [LicensingController::class, 'updateOfficeOrganization'])->name('office.organization.update')->middleware('module_access:administrativo.manage');
            Route::put('/office/{officeLicense}', [LicensingController::class, 'updateOffice'])->name('office.update')->middleware('module_access:administrativo.manage');
            Route::patch('/office/{officeLicense}/status', [LicensingController::class, 'toggleOfficeStatus'])->name('office.toggle')->middleware('module_access:administrativo.manage');
            Route::delete('/office/{officeLicense}', [LicensingController::class, 'destroyOffice'])->name('office.destroy')->middleware('module_access:administrativo.manage');
        });

        Route::get('/admin', [AdminController::class, 'index'])->name('admin.dashboard');
        Route::post('/admin/approve/{user}', [AdminController::class, 'approveUser'])->name('admin.approve')->middleware('module_access:administrativo.manage');
        Route::post('/admin/reject/{user}', [AdminController::class, 'rejectUser'])->name('admin.reject')->middleware('module_access:administrativo.manage');
        Route::post('/admin/reset-password/{user}', [AdminController::class, 'resetPassword'])->name('admin.reset-password')->middleware('module_access:administrativo.manage');
        Route::patch('/admin/role/{user}', [AdminController::class, 'updateRole'])->name('admin.update-role')->middleware('module_access:administrativo.manage');
        Route::patch('/admin/module-access/{user}', [AdminController::class, 'updateModuleAccess'])->name('admin.update-module-access')->middleware('module_access:administrativo.manage');
        Route::delete('/admin/delete/{user}', [AdminController::class, 'deleteUser'])->name('admin.delete')->middleware('module_access:administrativo.manage');
    });

    /*
     | Impressoras. Só leitura: o cadastro é do portal da Simpress, e a
     | sincronização de 4x ao dia sobrescreveria qualquer edição nossa.
     | Por isso nada aqui pede 'manage' ainda — quando abrirmos pedido de
     | toner pelo CoreTI, essa ação é que vai exigir.
     */
    /*
     | Dashboards analíticos. Cada um é protegido pelo módulo do assunto que
     | analisa — ver estatística de impressão é ver dado de impressão.
     */
    Route::prefix('dashboards')->name('dashboards.')->group(function () {
        Route::get('/impressoes', [\App\Http\Controllers\Impressao\PrintDashboardController::class, 'impressoes'])
            ->middleware('module_access:impressoras')->name('impressoes');
    });

    Route::prefix('impressoras')->name('impressoras.')->middleware('module_access:impressoras')->group(function () {
        Route::get('/', [\App\Http\Controllers\PrinterController::class, 'index'])->name('index');
        Route::get('/{printer}', [\App\Http\Controllers\PrinterController::class, 'show'])->name('show');
    });
});

// Rotas de autenticação do Breeze (/login, /logout, etc.)
if (file_exists(__DIR__ . '/auth.php')) {
    require __DIR__ . '/auth.php';
}
