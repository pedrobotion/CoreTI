# CoreTI — Documentação Bancada de Serviços e Administrativo

## 1. Visão geral
O CoreTI concentra processos operacionais e administrativos de TI. Nesta documentação, o foco é:

- **Bancada de Serviços**: fluxo técnico dos equipamentos (triagem, manutenção, peças, terceiros, backup, descarte, entrega).
- **Administrativo**: pendências fiscais e operacionais da Bancada (entrada fiscal, terceiros, peças, nota de saída, empresas terceirizadas e histórico).
- **Integração Bancada ↔ Administrativo**: a Bancada dispara status/eventos; o Administrativo conclui etapas que destravam o fluxo técnico.

Resumo funcional:
- A Bancada conduz o ciclo técnico do equipamento.
- O Administrativo trata etapas de apoio obrigatórias (fiscal/terceiros/peças/saída).

---

## 2. Módulos envolvidos

### Bancada de Serviços
- Equipamentos ativos
- Prontos para entrega (rota de aguardando entrega)
- Equipamentos entregues
- Backup
- Descartados
- Histórico do equipamento
- Impressão de etiquetas

### Administrativo
- Painel
- Entrada Fiscal
- Terceiros
- Peças
- Estoque Interno
- Nota de Saída
- Empresas Terceirizadas
- Histórico Administrativo
- Relatórios
- Configurações

---

## 3. Arquivos principais do projeto

### Controllers/Services/Models
- `app/Http/Controllers/BancadaServicosController.php`
- `app/Services/BancadaStatusFlowService.php`
- `app/Services/BancadaAttachmentService.php`
- `app/Models/BancadaEquipment.php`
- `app/Models/BancadaEquipmentEvent.php`
- `app/Models/BancadaEquipmentAttachment.php`
- `app/Models/BancadaEquipmentStatusHistory.php`
- `app/Models/BancadaThirdPartyCompany.php`

### Rotas
- `routes/web.php`

### Views Bancada
- `resources/views/bancada-servicos/assets-list.blade.php`
- `resources/views/bancada-servicos/awaiting-delivery.blade.php`
- `resources/views/bancada-servicos/asset-history.blade.php`
- `resources/views/bancada-servicos/print.blade.php`
- `resources/views/bancada-servicos/partials/label-printer.blade.php`

### Views Administrativo (nova organização)
- `resources/views/panels/administrativo/painel.blade.php`
- `resources/views/panels/administrativo/entrada-fiscal.blade.php`
- `resources/views/panels/administrativo/terceiros.blade.php`
- `resources/views/panels/administrativo/pecas.blade.php`
- `resources/views/panels/administrativo/estoque-interno.blade.php`
- `resources/views/panels/administrativo/nota-saida.blade.php`
- `resources/views/panels/administrativo/empresas-terceirizadas.blade.php`
- `resources/views/panels/administrativo/historico.blade.php`
- `resources/views/panels/administrativo/partials/*.blade.php`

### Menu lateral
- `resources/views/layouts/partials/sidebar-menu.blade.php`

### Migrations relevantes (Bancada/Administrativo)
- `database/migrations/2026_05_07_120000_create_bancada_tables.php`
- `database/migrations/2026_05_07_130000_add_backup_fields_to_bancada_equipments_table.php`
- `database/migrations/2026_05_14_120000_allow_duplicate_plaqueta_on_bancada_equipments.php`
- `database/migrations/2026_05_18_000100_add_terceiros_fields_to_bancada_equipments_table.php`
- `database/migrations/2026_05_18_120000_add_maintenance_workflow_fields_to_bancada_equipments_table.php`
- `database/migrations/2026_05_19_090000_add_terceiros_cnpj_to_bancada_equipments_table.php`
- `database/migrations/2026_05_19_120000_add_data_emissao_entrada_to_bancada_equipments_table.php`
- `database/migrations/2026_05_19_130000_create_bancada_stock_usages_table.php`
- `database/migrations/2026_05_19_140000_create_bancada_malote_routes_tables.php`
- `database/migrations/2026_05_27_150000_create_bancada_equipment_events_table.php`
- `database/migrations/2026_05_27_150100_create_bancada_equipment_attachments_table.php`
- `database/migrations/2026_05_27_150200_create_bancada_third_party_companies_table.php`
- `database/migrations/2026_05_27_150300_add_discard_final_fields_to_bancada_equipments_table.php`
- `database/migrations/2026_05_27_170000_add_admin_flow_tracking_fields_to_bancada_equipments_table.php`
- `database/migrations/2026_05_27_180000_add_saida_nota_fields_to_bancada_equipments_table.php`

---

## 4. Tabelas principais

### `bancada_equipments`
Campos centrais identificados no model/migrations:
- Cadastro: `tipo_equipamento`, `plaqueta`, `origem_tipo`, `unidade_setor`, `data_chegada`, `tic`, `observacao`
- Fluxo: `status`, `data_saida`
- Entrada fiscal: `entrada_status`, `nota_documento_entrada`, `nota_numero_entrada`, `data_emissao_entrada`, `nota_valor_entrada`, `nota_anexo_entrada`, `entrada_realizada_em`
- Nota saída: `nota_documento_saida`, `nota_numero_saida`, `nota_anexo_saida`, `nota_saida_emitida_em`
- Peças: `peca_nome`, `peca_quantidade`, `peca_origem`, `peca_link_compra`, `service_tag`, `peca_fluxo_status`, `peca_admin_realizado_em`, `peca_recebida_confirmada_em`
- Terceiros: `terceiros_problema`, `terceiros_empresa`, `terceiros_cnpj`, `terceiros_nota_remessa`, `terceiros_os_numero`, `terceiros_orcamento_anexo`, `terceiros_observacoes`, `terceiros_nota_orcamento`, `terceiros_orcamento_status`, `terceiros_resultado`, `terceiros_fluxo_status`, `terceiros_enviado_em`, `terceiros_valor_reparo`, `terceiros_retorno_em`
- Backup: `backup_localizacao`, `backup_pronto_emprestimo`, `backup_data_formatado`
- Descarte: `plaqueta_retirada`, `plaqueta_retirada_at`, `plaqueta_retirada_by`, `baixa_realizada`, `baixa_realizada_at`, `baixa_realizada_by`

### `bancada_equipment_events`
Registra eventos de negócio/auditoria:
- `bancada_equipment_id`
- `previous_status`
- `new_status`
- `action`
- `module`
- `performed_by`
- `observation`
- `metadata` (array/json)
- `created_at`, `updated_at`

### `bancada_equipment_attachments`
Anexos ligados a equipamento/evento:
- `bancada_equipment_id`
- `bancada_equipment_event_id` (nullable)
- `attachment_type`
- `original_name`
- `storage_disk`
- `storage_path`
- `mime_type`
- `size_bytes`
- `uploaded_by`
- `uploaded_at`

### `bancada_third_party_companies`
Empresas terceirizadas:
- `name`
- `cnpj`
- `contact`
- `is_active`
- Unique: `name + cnpj`

### Outras tabelas relevantes
- `bancada_equipment_status_histories` (histórico temporal de status)
- `bancada_stock_usages` (uso de estoque interno TI)
- `bancada_malote_routes` e `bancada_malote_route_units` (rotas de entrega)

---

## 5. Cadastro de equipamento
Campos esperados:
- Tipo do equipamento
- Plaqueta
- Data de chegada
- Origem
- Unidade/Setor
- TIC
- Observação

Tipos oficiais (validados no controller):
- Computador
- Monitor
- Nobreak
- Coletor
- Notebook
- Mi box
- Relógio Ponto
- Switch
- Televisão

Regras:
- Origem `sede` (departamento sede): inicia em `Em bancada`.
- Origem `unidade`: inicia em `Aguardando Entrada Fiscal` e fica travado até entrada fiscal.

---

## 6. Status oficiais do fluxo
Fonte principal: `app/Services/BancadaStatusFlowService.php`

Status:
- Aguardando Entrada Fiscal
- Em bancada
- Terceiros
- Aguardando peça
- Em manutenção
- Manutenção realizada
- Sem conserto
- Pronto para entrega
- Nota Fiscal Emitida
- Entregue
- Backup
- Descarte

Normalizações existentes:
- `Em Bancada` -> `Em bancada`
- `Aguardando Entrada` -> `Aguardando Entrada Fiscal`
- Entrada fiscal: `Aguardando Entrada` -> `Aguardando Entrada Fiscal`

---

## 7. Regras de transição de status
Implementadas em `BancadaStatusFlowService::assertTransition` e `availableTransitions`.

Cadastro:
- Unidade -> Aguardando Entrada Fiscal
- Sede -> Em bancada

Após entrada fiscal:
- Aguardando Entrada Fiscal -> Em bancada

De Em bancada:
- Terceiros
- Aguardando peça
- Em manutenção
- Backup

De Em manutenção:
- Manutenção realizada
- Sem conserto

De Manutenção realizada:
- Pronto para entrega
- Backup

De Sem conserto:
- Descarte

De Pronto para entrega:
- Sede -> Entregue
- Unidade -> Nota Fiscal Emitida

De Nota Fiscal Emitida:
- Entregue

De Terceiros:
- retorno positivo -> Manutenção realizada
- retorno negativo -> Sem conserto

De Aguardando peça:
- após pré-condições -> Manutenção realizada

Backup/Descarte/Entregue:
- fora do fluxo operacional comum (bloqueios por regra)

---

## 8. Fluxo de Entrada Fiscal
Quando ocorre:
- Equipamento de origem `unidade`.

Tela:
- Administrativo > Entrada Fiscal (`/administrativo/entrada-fiscal`).

Campos no modal:
- Docto de entrada
- Número da nota
- Data de emissão
- Valor
- Anexo da nota

Regras:
- Enquanto pendente, equipamento fica travado para transições técnicas (`isOperationalLocked`).
- Ao concluir:
  - `entrada_status = Entrada Realizada`
  - `status = Em bancada`
  - evento `entrada_fiscal_realizada`
  - anexo, se enviado, salvo via attachment service (`attachment_type: nota_entrada`)

Observação de nomenclatura:
- UI usa “Docto” em vários pontos. “Documento” existe em nomes de coluna/método (`nota_documento_*`).

---

## 9. Fluxo de Terceiros

### 9.1 Envio para terceiros pela Bancada
- Técnico escolhe `Terceiros` e informa problema obrigatório.
- Gera status `Terceiros` e pendência no Administrativo.
- Evento: `enviado_para_terceiros_solicitado`.

### 9.2 Registro de envio pelo Administrativo
Campos atuais no modal:
- Empresa
- CNPJ
- Nota remessa
- OS (opcional)
- Observações
- Anexo

Regra implementada:
- Evento `terceiro_enviado` (via `administrativeProcess`, ação `terceiros_envio`).

Observação (divergência com fluxo desejado):
- Hoje o campo OS aparece na etapa de envio.
- Preenchimento automático/readonly de CNPJ por seleção de empresa: **pendente de validação/implementação completa**.

### 9.3 Retorno do terceiro
- Ações administrativas:
  - `terceiros_retorno_positivo` -> `Manutenção realizada`
  - `terceiros_retorno_negativo` -> `Sem conserto`
- Eventos:
  - `terceiro_retorno_positivo`
  - `terceiro_retorno_negativo`

---

## 10. Empresas Terceirizadas
Tela:
- Administrativo > Empresas Terceirizadas (`/administrativo/empresas-terceirizadas`)

Funcionalidades atuais:
- Cadastrar
- Editar
- Ativar/Desativar

Regras:
- Não há exclusão física no fluxo padrão atual.
- Empresas inativas não aparecem no select operacional de envio para terceiros (lista ativa).
- Empresas inativas continuam em registros antigos (referência textual no equipamento/eventos).

Rotas:
- Store: `POST /administrativo/terceiros/empresas` (`bancada-servicos.admin.third-party-companies.store`)
- Update: `PATCH /administrativo/terceiros/empresas/{company}` (`bancada-servicos.admin.third-party-companies.update`)
- Toggle: `PATCH /administrativo/terceiros/empresas/{company}/toggle` (`bancada-servicos.admin.third-party-companies.toggle`)

---

## 11. Fluxo de Aguardando peça
Quando técnico seleciona `Aguardando peça`:
- Campos: peça, quantidade, origem.

Origens:
- `cd`
- `compra_internet`
- `estoque_ti`
- `dell`

### Estoque do CD
- Vai para Administrativo > Peças.
- ADM marca requisição.
- Bancada confirma recebimento.
- Segue para Manutenção realizada.

### Compra pela internet
- Exige link de compra.
- ADM marca pedido.
- Bancada confirma recebimento.
- Segue para Manutenção realizada.

### Fornecedor Dell
- Exige ServiceTag.
- ADM marca pedido.
- Bancada confirma recebimento.
- Segue para Manutenção realizada.

### Estoque Interno da TI
- Não trava pendência administrativa de pedido externo.
- Vai para tela de Estoque Interno (consulta/reposição).
- Pode seguir para Manutenção realizada.

Eventos relevantes no código:
- `aguardando_peca_solicitado`
- `requisicao_cd_realizada`
- `pedido_internet_realizado`
- `pedido_dell_realizado`
- `peca_cd_recebida`
- `peca_internet_recebida`
- `peca_dell_recebida`
- `peca_estoque_interno_utilizada`

---

## 12. Fluxo de manutenção
- Em bancada -> Em manutenção
- Em manutenção -> Manutenção realizada / Sem conserto
- Manutenção realizada -> Pronto para entrega / Backup

Observação UX:
- Legibilidade/contraste do botão de envio para entrega: **pendente de validação visual contínua**.

---

## 13. Fluxo de Pronto para entrega e Nota de Saída
Sede:
- Pronto para entrega -> Entregue

Unidade:
- Pronto para entrega exige etapa administrativa de nota de saída.
- Campos administrativos:
  - Docto da nota de saída
  - Número da nota
  - Anexo da nota
- Após registrar, equipamento vai para `Nota Fiscal Emitida`.
- Depois, Bancada conclui em `Entregue`.

Evento:
- `nota_saida_emitida`

---

## 14. Equipamentos Entregues
- Status final `Entregue`.
- Fora da lista principal.
- Consulta/histórico.
- Regra impede retorno ao fluxo operacional comum.

---

## 15. Backup
Status:
- `Backup`

Tela:
- Bancada > Backup

Regras/funcionalidades atuais:
- Fora do fluxo operacional comum.
- Controle de disponibilidade:
  - Disponível para empréstimo
  - Indisponível
- Campos de backup:
  - Localização
  - Data backup
  - Pronto para empréstimo
- Alternância rápida de disponibilidade.
- Edição de backup (modal) altera somente dados de backup (e observação do equipamento via método atual).
- Edição cadastral básica altera somente dados básicos.

Eventos:
- `backup_disponibilidade_atualizada`
- Eventos de edição de backup: **verificar no projeto** (não há evento explícito dedicado no `updateBackupData`).

---

## 16. Descarte
Status:
- `Descarte`

Tela:
- Bancada > Descartados

Regras atuais:
- Fora da lista principal.
- Controle visual simplificado: `Baixa realizada`.
- `plaqueta_retirada` mantido apenas como compatibilidade interna e sincronizado com baixa.
- Não retorna ao fluxo operacional comum.

Evento:
- `descarte_baixa_realizada_atualizada`

---

## 17. Histórico do equipamento
Consulta:
- `bancada-servicos.assets.history` (`/bancada-servicos/equipamentos/{equipment}/historico`)

Exibe:
- histórico de status (`bancada_equipment_status_histories`)
- eventos (`bancada_equipment_events`)
- usuário executor
- observação
- metadados
- anexos vinculados

Regra recomendada aplicada no projeto:
- Ações relevantes devem gerar evento.

---

## 18. Anexos
Service:
- `app/Services/BancadaAttachmentService.php`

Regras observadas:
- Anexos gravados em storage com metadados no banco.
- Download via rota protegida por autorização.
- `downloadAttachment` verifica acesso por módulo/master.

Tipos de anexo no fluxo (observados/esperados):
- `nota_entrada`
- `nota_saida`
- anexos de terceiros/orçamento/remessa (nome técnico pode variar por `attachment_type`; verificar no projeto)

Rota de download:
- `GET /bancada-servicos/anexos/{attachment}/download`
- Nome: `bancada-servicos.attachments.download`

---

## 19. Layout Administrativo
Organização atual do menu/telas:
- Painel
- Entrada Fiscal
- Terceiros
- Peças
- Estoque Interno
- Nota de Saída
- Empresas Terceirizadas
- Histórico
- Relatórios
- Configurações

Licenciamento (mantido):
- E-mail
- Jira
- Rateio Office

Painel ficou resumido (cards) e operações estão em telas separadas.

---

## 20. Layout Bancada
Telas/listas:
- Equipamentos ativos
- Aguardando entrega
- Entregues
- Backup
- Descartados

Distribuição por status:
- Ativos: status operacionais
- Entregues: `Entregue`
- Backup: `Backup`
- Descartados: `Descarte`

---

## 21. Impressão de etiquetas/ZPL
Pontos do projeto:
- `printLabel()` e view `resources/views/bancada-servicos/print.blade.php`
- `printBackupTemplate()` para etiqueta de backup
- integração UI em `resources/views/bancada-servicos/partials/label-printer.blade.php`
- `public/vendor/qz-tray.js`

Regra de ouro:
- Não alterar ZPL/impressão sem validação funcional com impressora e fluxo real.

---

## 22. Controle de acesso
Implementação observada:
- Middleware `module_access:*` (`EnsureModuleAccess`) e `admin`.
- Grupo Bancada: `['admin', 'module_access:bancada']`.
- Grupo Administrativo: `['admin', 'module_access:administrativo']`.
- `User::hasModuleAccess()` concede acesso total para:
  - usuário master (`User::MASTER_USER_ID = 2`)
  - `role === 'admin'`

Conclusão:
- Padrão de usuário master ID 2 existe e está ativo no código.

---

## 23. Rotas principais

### Bancada
- `bancada-servicos.dashboard`
- `bancada-servicos.assets`
- `bancada-servicos.assets.delivered`
- `bancada-servicos.assets.backup`
- `bancada-servicos.assets.discarded`
- `bancada-servicos.awaiting-delivery`
- `bancada-servicos.assets.history`
- `bancada-servicos.attachments.download`
- `bancada-servicos.assets.update` (edição cadastral básica)
- `bancada-servicos.assets.status` (mudança de status)
- `bancada-servicos.assets.backup.update`
- `bancada-servicos.assets.backup.availability.update`
- `bancada-servicos.assets.discard.update`
- `bancada-servicos.assets.entry.completed`
- `bancada-servicos.assets.administrative.process`
- `bancada-servicos.assets.print`
- `bancada-servicos.assets.backup.print-template`

### Administrativo
- `administrativo`
- `administrativo.visao-geral`
- `administrativo.entrada-fiscal`
- `administrativo.terceiros`
- `administrativo.pecas`
- `administrativo.estoque-interno`
- `administrativo.nota-saida`
- `administrativo.empresas-terceirizadas`
- `administrativo.historico`
- `administrativo.relatorios`
- `administrativo.configuracoes`
- empresas terceirizadas:
  - `bancada-servicos.admin.third-party-companies.store`
  - `bancada-servicos.admin.third-party-companies.update`
  - `bancada-servicos.admin.third-party-companies.toggle`

---

## 24. Pendências conhecidas
- Melhorar contraste/legibilidade de alguns botões operacionais (ex.: envio para entrega) — validar visual atual.
- Modal de terceiros (envio):
  - remover OS da primeira etapa (desejado, ainda aparece no fluxo atual)
  - CNPJ automático por empresa selecionada (pendente)
  - CNPJ somente leitura (pendente)
  - revisar UX do botão registrar envio.
- Modal retorno terceiros:
  - simplificação de campos e nomenclatura “Reparo Aprovado/Reprovado” (pendente de padronização de UX).
- Responsividade de tabelas largas em mobile (uso de overflow já existe, mas UX pode melhorar).
- Padronização de badges/textos entre telas secundárias.
- Evolução futura de SLA/indicadores administrativos dedicados.

Observação:
- Correção de Fechar/Cancelar no modal de entrada fiscal foi aplicada no partial atual (`@click.stop`).

---

## 25. Cuidados para futuras alterações
Regras de ouro:
- Não alterar status diretamente sem validar transição pelo `BancadaStatusFlowService`.
- Não criar update genérico misturando campos cadastrais + fiscais + técnicos no mesmo endpoint.
- Edição cadastral altera somente dados básicos.
- Campos fiscais via fluxos administrativos apropriados.
- Campos de terceiros via fluxo de terceiros.
- Campos de peças via fluxo de peças.
- Não expor anexos publicamente; usar rota protegida.
- Não quebrar impressão de etiquetas/ZPL.
- Ação relevante deve gerar evento.
- Não excluir empresas terceirizadas fisicamente quando já houver histórico/vínculo.

---

## Resumo rápido para IA/Codex
- Use `BancadaStatusFlowService` como referência única de status/transições.
- Para editar equipamento, use endpoint cadastral (`updateAsset`) e nunca altere status por esse caminho.
- Fluxos administrativos continuam centralizados em `administrativeProcess` por `action`.
- Empresas terceirizadas: use `toggle` para ativar/desativar; não delete físico.
- Em qualquer mudança de processo, preserve eventos e histórico.
- Antes de alterar UI, valide impacto em `assets-list.blade.php` (várias telas compartilham o mesmo arquivo).
- Não mexa em ZPL/impressão sem teste funcional real.
