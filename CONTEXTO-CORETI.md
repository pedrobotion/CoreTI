# CoreTI — Contexto do Projeto (briefing para novo chat)

> Cole este arquivo no início de uma nova conversa para dar contexto ao Claude sem reexplicar tudo.
> Mantido em `/var/www/html/coreti/CONTEXTO-CORETI.md`. Última revisão: 2026-10-14.

## O que é
Sistema interno de TI da **Cocari** (cooperativa). Gestão de bancada de serviços, impressoras, licenciamento (Office/e-mail), backups/certificados, telefonia, dashboards analíticos, integração com Jira e Grafana.

- **Produção:** https://coreti.cocari.com.br (`APP_ENV=production`)
- **Código:** `/var/www/html/coreti`
- **Stack:** Laravel 12.31, PHP 8.3, Blade + Alpine.js, Tailwind (Vite, CSS pré-compilado), Node 20.7.0
- **Banco:** `intranet_infra` (MySQL/MariaDB), conexão `mysql`
- **Sistema irmão (Atlas):** `/var/www/html/cocari_gestaoTI` — https://atlas.cocari.com.br (monitoramento de impressoras do Cerrado, Zabbix)

## ⚠️ Regras de operação (NÃO violar)
1. **Produção é ao vivo.** Nunca gravar/apagar em `intranet_infra` sem: (a) backup das linhas afetadas, (b) confirmação explícita do usuário, (c) verificação depois. Para testar lógica de escrita, usar transação com `rollBack()`.
2. **`config:clear` derruba o site** (roda com config cacheada; www-data não lê `.env`). Depois de qualquer mexida em `config/`, rodar **`php artisan config:cache`** e conferir `/login` = 200. Sintoma de erro: 500 "No application encryption key".
3. **Classe Tailwind nova só existe após `npm run build`** (CSS pré-compilado via Vite). Antes de usar uma classe, confirmar que ela já existe no CSS compilado (`public/build/assets/*.css`); senão, buildar.
4. **Rotas:** se `bootstrap/cache/routes-*.php` existir, rota nova exige `route:cache`. (Hoje produção **não** cacheia rotas — rota nova vale na hora.)
5. **Nunca combinar middleware `admin` com `module_access:*`** na mesma rota — vira admin-only e ignora o módulo (dá 403 pra quem tem o módulo). Usar só `module_access:*`.
6. **Jira:** não cancelar nem excluir chamados. Atualizar campo é permitido.
7. **APIs externas com teto diário:** não disparar requisições descontroladas à Simpress/EKM Insight.
8. **Credenciais:** `gas_usuario.csv` tem hashes bcrypt — nunca exibir. Identidade do usuário (ai@cocari.com.br) só para autoria/atribuição.
9. **OpCache** revalida PHP em ~2s (edição de `.php` vale sozinha). Blade recompila por mtime.

## Acesso: módulo × escopo (importante)
- **Módulo** (`config/modules.php`, middleware `module_access:<modulo>`, `EnsureModuleAccess`) **abre a tela**. Admin/master têm bypass.
- **Escopo operacional** (trait `HasOperationalScope`, `->visibleTo($user)`, tabela `user_scopes`, `config/scopes.php`: sede=Paraná, cerrado=Goiás/Minas) **filtra os dados**. Sem escopo, a tela abre **zerada** (`whereRaw('1=0')`, falha fechada).
- Ambos liberados na **tela de acessos do admin** (`admin.dashboard` / `AdminController`). Dashboards: **1 módulo por painel** (`dashboard_*`, grupo "Dashboards").

## Mapa de sistemas (arquivos-chave)
- **Bancada de Serviços:** `BancadaServicosController`, models `Bancada*`. Rotas de malote: `bancada_malote_routes` + `_units` (nome é texto livre; excluir só quando inativa — FK `nullOnDelete`/`cascadeOnDelete`). Pendências administrativas: comando `bancada:verificar-pendencias` (Jira + e-mail + Grafana); chamados vão p/ equipe **"Time Administrativo de TI"** (override por chamada em `JiraTicketOpener`).
- **Impressoras:** comando `impressoras:alertas` (varredura diária Insight → e-mail crítico/aviso). Explicação de erros em `CatalogoErrosImpressora`. E-mail `PrinterAlertsMail`. **Roteamento por região:** Paraná→felipe.munekata; Cerrado→marcos.ferreira+joao.moraes (classificação por `ClassificadorCerrado`, config `insight.cerrado`). Bloco IP Cerrado: 172.20.78–92.x.
- **Licenciamento Office (rateio):** `/administrativo/licenciamento/office`. Nomes de centro de custo/unidade/departamento vêm do catálogo **novo** `coreti_cost_centers` (tela `/centros-custo`), chave = **código `UU.AAA`**, resolvidos em leitura por `App\Support\Licensing\CatalogoCentrosCusto`. Catálogo antigo da intranet (`organization_*`) está **obsoleto**. Export Excel por `OfficeAllocationExportService`.
- **Backups/Certificados:** comandos próprios; abrem chamado Jira via `JiraTicketOpener` (equipe global = índice 5 = "Time Network & Data Center", id 10524).
- **Jira:** `JiraTicketOpener->abrir($titulo,$desc,$contexto,$extra,$equipeNome?)`. Campo equipe = `customfield_10087`, contexto 10192. Opções: 10526 Administrativo de TI, 10524 Network & Data Center, 10521 Bancada, 10523 Cerrado, 10522 Campo, 10525 Service Desk.
- **Dashboards:** `AnalyticsDashboardController` + `app/Services/Dashboards/*`. "Service Desk Apresentação" = deck mensal ao vivo da tabela `jiras` (seletor mês/período, 5 abas).
- **Grafana:** arquivos em `grafana/*.json`, datasource `8TO3O9PVz`, formato BR.

## Como trabalhar aqui
- Responder em **português (PT-BR)**.
- Antes de editar `config/`, Tailwind ou rotas, lembrar das regras acima.
- Confirmar fatos no código (arquivo:linha) antes de afirmar; versões/caminhos podem mudar.
- Memórias persistentes do Claude ficam em `/root/.claude/projects/-var-www-html-coreti/memory/` (índice em `MEMORY.md`).
- Branch atual de trabalho recente: `feat/telefonia-equipamentos` (base: `main`).

## Estado recente (últimas tarefas concluídas)
- Rateio Office passou a usar o catálogo novo de centros de custo (resolução por código em leitura, sem gravar; Excel ajustado; consolidado agregado por `UU.AAA`).
- Rotas de malote: nome virou campo de texto livre (com sugestões) + botão excluir (só rota inativa).
- Bancada: chamados de pendência corrigidos para "Time Administrativo de TI" (código + 10 chamados retroativos TIC-33095..33104).
