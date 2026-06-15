<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CircuitController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ServiceDeskController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\BancadaServicosController;
use App\Http\Controllers\JiraProjectController;
use App\Http\Controllers\LicensingController;
use App\Http\Controllers\UnidadeDigitalController;

// Raiz: visitante -> /login | autenticado -> /home
Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('home')
        : redirect()->route('login');
});

// Área autenticada
Route::middleware(['auth', 'active'])->group(function () {
    // Home principal
    Route::get('/home', HomeController::class)->name('home');

    // Alias: qualquer redirect para 'dashboard' cai em /home
    Route::redirect('/dashboard', '/home')->name('dashboard');

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

    Route::prefix('service-desk')->name('service-desk.')->middleware('module_access:servicedesk')->group(function () {
        Route::get('/', [ServiceDeskController::class, 'dashboard'])->name('dashboard');
        Route::get('/chamados', [ServiceDeskController::class, 'tickets'])->name('tickets');
        Route::get('/minha-fila', [ServiceDeskController::class, 'myQueue'])->name('my-queue');
        Route::post('/minha-fila/emails', [ServiceDeskController::class, 'storeMyQueueEmail'])->name('my-queue.emails.store');
        Route::delete('/minha-fila/emails/{queueEmail}', [ServiceDeskController::class, 'destroyMyQueueEmail'])->name('my-queue.emails.destroy');
        Route::get('/emails/sede', [ServiceDeskController::class, 'emails'])->defaults('scope', 'sede')->name('emails.sede');
        Route::get('/emails/unidades', [ServiceDeskController::class, 'emails'])->defaults('scope', 'unidades')->name('emails.unidades');
        Route::get('/emails/cerrado', [ServiceDeskController::class, 'emails'])->defaults('scope', 'cerrado')->name('emails.cerrado');
        Route::get('/emails/genericos', [ServiceDeskController::class, 'emails'])->defaults('scope', 'genericos')->name('emails.genericos');
        Route::get('/emails/export', [ServiceDeskController::class, 'exportEmails'])->name('emails.export');
        Route::get('/emails/lookup/collaborator', [ServiceDeskController::class, 'lookupCollaborator'])->name('emails.lookup.collaborator');
        Route::get('/emails/{scope}/lookup/centro-custo', [ServiceDeskController::class, 'lookupCostCenter'])->whereIn('scope', ['sede', 'unidades', 'cerrado', 'genericos'])->name('emails.lookup.cost-center');
        Route::post('/emails/{scope}', [ServiceDeskController::class, 'storeEmail'])->whereIn('scope', ['sede', 'unidades', 'cerrado', 'genericos'])->name('emails.store');
        Route::get('/emails/{scope}/{email}/edit', [ServiceDeskController::class, 'editEmail'])->whereIn('scope', ['sede', 'unidades', 'cerrado', 'genericos'])->name('emails.edit');
        Route::put('/emails/{scope}/{email}', [ServiceDeskController::class, 'updateEmail'])->whereIn('scope', ['sede', 'unidades', 'cerrado', 'genericos'])->name('emails.update');
        Route::patch('/emails/{scope}/{email}/toggle', [ServiceDeskController::class, 'toggleEmail'])->whereIn('scope', ['sede', 'unidades', 'cerrado', 'genericos'])->name('emails.toggle');
        Route::delete('/emails/{scope}/{email}', [ServiceDeskController::class, 'destroyEmail'])->whereIn('scope', ['sede', 'unidades', 'cerrado', 'genericos'])->name('emails.destroy');
        Route::get('/office', [LicensingController::class, 'officeLicensing'])->name('office');
        Route::post('/workspace-sync/now', [ServiceDeskController::class, 'syncWorkspaceNow'])->name('workspace-sync.now');
        Route::post('/workspace-sync/preview', [ServiceDeskController::class, 'refreshWorkspacePreview'])->name('workspace-sync.preview');
        Route::post('/workspace-sync/apply', [ServiceDeskController::class, 'applyWorkspaceSync'])->name('workspace-sync.apply');
        Route::view('/configuracoes', 'service-desk.placeholder', [
            'title' => 'Configurações',
            'description' => 'Parâmetros, filas, categorias e permissões do módulo.',
        ])->name('settings');
    });

    Route::prefix('service-desk/jira-projetos')->name('jira-projects.')->middleware(['admin', 'module_access:administrativo'])->group(function () {
        Route::get('/', [JiraProjectController::class, 'index'])->name('index');
        Route::get('/create', [JiraProjectController::class, 'create'])->name('create');
        Route::post('/', [JiraProjectController::class, 'store'])->name('store');
        Route::get('/{jiraProject}/edit', [JiraProjectController::class, 'edit'])->name('edit');
        Route::put('/{jiraProject}', [JiraProjectController::class, 'update'])->name('update');
        Route::delete('/{jiraProject}', [JiraProjectController::class, 'destroy'])->name('destroy');
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
    Route::middleware(['admin', 'module_access:administrativo'])->group(function () {
        // Paineis (atalhos principais)
        Route::get('/administrativo', [BancadaServicosController::class, 'administrativePanel'])->name('administrativo');
        Route::view('/governanca', 'panels.governanca')->name('governanca');
        Route::view('/indicadores', 'panels.indicadores')->name('indicadores');
        Route::get('/unidade-digital', [UnidadeDigitalController::class, 'index'])->name('unidade-digital');
        Route::get('/unidade-digital/status', [UnidadeDigitalController::class, 'status'])->name('unidade-digital.status');
        Route::view('/monitoramento', 'panels.monitoramento')->name('monitoramento');

        // Subpaginas Administrativo
        Route::get('/administrativo/visao-geral', [BancadaServicosController::class, 'administrativeOverview'])->name('administrativo.visao-geral');
        Route::get('/administrativo/entrada-fiscal', [BancadaServicosController::class, 'administrativeEntryFiscal'])->name('administrativo.entrada-fiscal');
        Route::get('/administrativo/terceiros', [BancadaServicosController::class, 'administrativeThirdParties'])->name('administrativo.terceiros');
        Route::get('/administrativo/pecas', [BancadaServicosController::class, 'administrativeParts'])->name('administrativo.pecas');
        Route::get('/administrativo/estoque-interno', [BancadaServicosController::class, 'administrativeInternalStock'])->name('administrativo.estoque-interno');
        Route::get('/administrativo/nota-saida', [BancadaServicosController::class, 'administrativeOutboundNote'])->name('administrativo.nota-saida');
        Route::get('/administrativo/empresas-terceirizadas', [BancadaServicosController::class, 'administrativeThirdPartyCompanies'])->name('administrativo.empresas-terceirizadas');
        Route::get('/administrativo/historico', [BancadaServicosController::class, 'administrativeHistory'])->name('administrativo.historico');
        Route::post('/administrativo/terceiros/empresas', [BancadaServicosController::class, 'storeThirdPartyCompany'])->name('bancada-servicos.admin.third-party-companies.store');
        Route::patch('/administrativo/terceiros/empresas/{company}', [BancadaServicosController::class, 'updateThirdPartyCompany'])->name('bancada-servicos.admin.third-party-companies.update');
        Route::patch('/administrativo/terceiros/empresas/{company}/toggle', [BancadaServicosController::class, 'toggleThirdPartyCompany'])->name('bancada-servicos.admin.third-party-companies.toggle');
        Route::view('/administrativo/relatorios', 'panels.administrativo.relatorios')->name('administrativo.relatorios');
        Route::view('/administrativo/configuracoes', 'panels.administrativo.configuracoes')->name('administrativo.configuracoes');
        Route::view('/administrativo/solicitacoes', 'panels.administrativo.solicitacoes')->name('administrativo.solicitacoes');
        Route::prefix('/administrativo/licenciamento')->name('administrativo.licensing.')->group(function () {
            Route::get('/email', [LicensingController::class, 'email'])->name('email');
            Route::get('/jira', [LicensingController::class, 'jira'])->name('jira');
            Route::get('/office', [LicensingController::class, 'officeRateio'])->name('office');
            Route::get('/office/lookup/matricula', [LicensingController::class, 'lookupOfficeByMatricula'])->name('office.lookup.matricula');
            Route::post('/office', [LicensingController::class, 'storeOffice'])->name('office.store');
            Route::post('/office/importar-matriculas', [LicensingController::class, 'importOfficeMatriculasByEmail'])->name('office.import-matriculas');
            Route::put('/office/{officeLicense}', [LicensingController::class, 'updateOffice'])->name('office.update');
            Route::patch('/office/{officeLicense}/status', [LicensingController::class, 'toggleOfficeStatus'])->name('office.toggle');
            Route::delete('/office/{officeLicense}', [LicensingController::class, 'destroyOffice'])->name('office.destroy');
        });

        // Subpaginas Governanca
        Route::view('/governanca/visao-geral', 'panels.governanca.visao-geral')->name('governanca.visao-geral');
        Route::view('/governanca/politicas', 'panels.governanca.politicas')->name('governanca.politicas');
        Route::view('/governanca/auditorias', 'panels.governanca.auditorias')->name('governanca.auditorias');
        Route::view('/governanca/riscos', 'panels.governanca.riscos')->name('governanca.riscos');

        // Subpaginas Indicadores
        Route::view('/indicadores/visao-geral', 'panels.indicadores.visao-geral')->name('indicadores.visao-geral');
        Route::view('/indicadores/tendencias', 'panels.indicadores.tendencias')->name('indicadores.tendencias');
        Route::view('/indicadores/metas', 'panels.indicadores.metas')->name('indicadores.metas');
        Route::view('/indicadores/exportacao', 'panels.indicadores.exportacao')->name('indicadores.exportacao');

        // Subpaginas Unidade Digital
        Route::view('/unidade-digital/visao-geral', 'panels.unidade-digital.visao-geral')->name('unidade-digital.visao-geral');
        Route::view('/unidade-digital/projetos', 'panels.unidade-digital.projetos')->name('unidade-digital.projetos');
        Route::view('/unidade-digital/demandas', 'panels.unidade-digital.demandas')->name('unidade-digital.demandas');
        Route::view('/unidade-digital/roadmap', 'panels.unidade-digital.roadmap')->name('unidade-digital.roadmap');

        // Subpaginas Monitoramento
        Route::view('/monitoramento/visao-geral', 'panels.monitoramento.visao-geral')->name('monitoramento.visao-geral');
        Route::view('/monitoramento/alertas', 'panels.monitoramento.alertas')->name('monitoramento.alertas');
        Route::view('/monitoramento/disponibilidade', 'panels.monitoramento.disponibilidade')->name('monitoramento.disponibilidade');
        Route::view('/monitoramento/incidentes', 'panels.monitoramento.incidentes')->name('monitoramento.incidentes');

        Route::get('/admin', [AdminController::class, 'index'])->name('admin.dashboard');
        Route::post('/admin/approve/{user}', [AdminController::class, 'approveUser'])->name('admin.approve');
        Route::post('/admin/reject/{user}', [AdminController::class, 'rejectUser'])->name('admin.reject');
        Route::post('/admin/reset-password/{user}', [AdminController::class, 'resetPassword'])->name('admin.reset-password');
        Route::patch('/admin/role/{user}', [AdminController::class, 'updateRole'])->name('admin.update-role');
        Route::patch('/admin/module-access/{user}', [AdminController::class, 'updateModuleAccess'])->name('admin.update-module-access');
        Route::delete('/admin/delete/{user}', [AdminController::class, 'deleteUser'])->name('admin.delete');
    });
});

// Rotas de autenticação do Breeze (/login, /logout, etc.)
if (file_exists(__DIR__ . '/auth.php')) {
    require __DIR__ . '/auth.php';
}
