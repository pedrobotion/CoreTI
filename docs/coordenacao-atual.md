# Coordenação no CoreTI

## Visão geral

O módulo **Coordenação** apresenta uma estimativa dos custos de serviços de TI por colaborador e por unidade. O cálculo combina os usuários do Active Directory com os registros de e-mail Google e as licenças do Office/Microsoft 365.

O módulo não realiza cobrança, lançamento financeiro ou pagamento. Os valores são estimativas baseadas nas regras de custo cadastradas pela TI.

## Acesso e menu

O módulo é acessado por:

```text
/coordenacao
```

As rotas são protegidas pelo middleware:

```text
module_access:coordenacao
```

O acesso depende da permissão `coordenacao` em `user_module_permissions`. Contas master e administradores seguem as regras gerais de acesso do CoreTI.

Quando o usuário está dentro do módulo, a sidebar apresenta:

- Voltar ao CoreTI;
- Dashboard;
- Colaboradores;
- Unidades;
- Regras de custo;
- Exportar Excel.

## Rotas disponíveis

| Rota | Nome | Função |
|---|---|---|
| `GET /coordenacao` | `coordenacao.dashboard` | Dashboard principal |
| `GET /coordenacao/dashboard` | `coordenacao.index` | Alias do dashboard |
| `GET /coordenacao/colaboradores` | `coordenacao.collaborators` | Lista individual por colaborador |
| `GET /coordenacao/unidades` | `coordenacao.units` | Consolidado por unidade |
| `GET /coordenacao/regras-de-custo` | `coordenacao.rules` | Cadastro e manutenção das regras |
| `POST /coordenacao/regras-de-custo` | `coordenacao.rules.store` | Criação de regra |
| `PATCH /coordenacao/regras-de-custo/{rule}` | `coordenacao.rules.update` | Edição de regra |
| `PATCH /coordenacao/regras-de-custo/{rule}/toggle` | `coordenacao.rules.toggle` | Ativação/desativação |
| `GET /coordenacao/exportar` | `coordenacao.export` | Exportação XLSX |

## Arquitetura técnica

### Controller

Arquivo:

```text
app/Http/Controllers/CoordenacaoController.php
```

O controller:

- recebe filtros da requisição;
- chama o serviço de análise;
- prepara paginação das coleções em memória;
- renderiza as telas;
- valida e grava regras de custo;
- gera o arquivo Excel.

### Serviço principal

Arquivo:

```text
app/Services/CoordenacaoCostAnalysisService.php
```

Esse serviço constrói um relatório consolidado em memória. A sequência atual é:

1. Carregar as regras de custo;
2. carregar usuários do `ad_users`;
3. carregar registros de `coreti_google_emails`;
4. carregar registros de `office_licenses`;
5. unificar os registros por e-mail ou nome de usuário;
6. aplicar filtros;
7. aplicar as regras ativas para cada pessoa;
8. agrupar os resultados por unidade;
9. calcular estatísticas, grupos detectados, candidatos e avisos.

Não existe uma tabela de lançamentos de custos por colaborador. Os custos são calculados em tempo de consulta a partir das fontes atuais e das regras ativas.

## Fontes de dados

### Active Directory

Tabela:

```text
ad_users
```

É a base principal de pessoas consideradas no relatório. São utilizados principalmente:

- `nome_completo`;
- `email`;
- `nome_usuario`;
- `unidade_setor`;
- `e_ativo`;
- `ad_groups`.

Cada usuário do AD inicia com custo zero e pode receber custos de Office e de grupos AD.

### E-mails Google

Tabela/model:

```text
coreti_google_emails
```

Os registros são associados ao usuário do AD por e-mail ou nome de usuário. A existência do registro de e-mail, por si só, não gera custo. Ele serve para enriquecer a identificação e alimentar avisos quando o e-mail não possui correspondência no AD.

### Office/Microsoft 365

Tabela/model:

```text
office_licenses
```

As licenças são associadas ao usuário por e-mail. As flags atualmente consideradas são:

- `office_apps`;
- `office_business`;
- `powerbi_pro`;
- `powerbi_premium`;
- `visio_plan`.

O custo de cada flag é obtido nas regras de custo do tipo `office_flag`.

## Regras de correspondência

O módulo inicia com todos os usuários do AD e depois incorpora e-mails Google e registros do Office.

As correspondências externas utilizam principalmente:

1. e-mail normalizado;
2. nome de usuário/login normalizado;
3. nome do registro externo, quando aplicável.

Quando um registro de e-mail ou Office não encontra uma pessoa correspondente no AD, ele não entra na contagem de colaboradores nem no custo. Esse registro aparece na seção de avisos para correção ou análise.

O módulo não inventa unidade, colaborador ou custo para registros que não tenham correspondência segura no AD.

## Cálculo dos custos

Cada regra ativa pode ser aplicada a um colaborador. O serviço calcula:

- custo Office mensal;
- custo mensal de grupos;
- custo mensal total;
- custo pontual/equivalente não recorrente;
- regras correspondentes;
- observações e avisos.

### Ciclos de cobrança

As regras aceitam:

| Ciclo | Tratamento |
|---|---|
| Mensal | Valor integral no custo mensal |
| Anual | Valor dividido por 12 no custo mensal |
| Pontual | Não entra no custo mensal; aparece como custo não recorrente |

O valor original da regra permanece disponível para exibição e exportação.

## Regras cadastradas atualmente

Na verificação atual do banco existem **13 regras**, sendo **8 ativas**.

### Regras de Office

- Office Apps: mensal, atualmente `R$ 0,00`;
- Office Business: mensal, atualmente `R$ 0,00`;
- Power BI Pro: mensal, atualmente `R$ 0,00`;
- Power BI Premium: mensal, atualmente `R$ 0,00`;
- Visio Plan: mensal, atualmente `R$ 0,00`.

### Regras de grupos AD

- Citrix: grupo contém `Citrix`, anual, `R$ 100,00`;
- TS APP: grupo contém `TS APP`, anual, `R$ 437,31`;
- Progress: grupo contém `Progress`, mensal, atualmente `R$ 0,00`;
- Qlik Sense: inativa;
- Simpress: inativa;
- VPN: inativa;
- Home Office: inativa;
- RDS Remote Access Servers: inativa.

O valor mensal equivalente das regras anuais é calculado automaticamente pela divisão por 12.

## Dashboard

O dashboard exibe:

- total de colaboradores considerados;
- colaboradores com Office;
- colaboradores com custo em grupos;
- quantidade de regras ativas;
- custo mensal estimado;
- custo pontual;
- alerta de registros fora do AD;
- colaboradores com maior custo;
- unidades com maior custo;
- regras com maior impacto;
- frequência dos grupos AD;
- candidatos a novas regras.

Os cards e tabelas são recalculados a cada acesso ou aplicação de filtro.

## Tela de colaboradores

Rota:

```text
/coordenacao/colaboradores
```

Exibe, por pessoa:

- nome;
- e-mail;
- status;
- unidade resolvida;
- pacotes Office;
- grupos AD;
- regras aplicadas;
- custo mensal;
- custo pontual.

Filtros disponíveis:

- busca por nome, e-mail ou unidade;
- unidade;
- status ativo/inativo;
- quantidade por página.

## Tela de unidades

Rota:

```text
/coordenacao/unidades
```

Consolida os colaboradores por `resolved_unit` e mostra:

- unidade;
- quantidade de colaboradores;
- custo Office;
- custo de grupos;
- custo mensal total;
- custo pontual.

As unidades são ordenadas pelo maior custo mensal. A tela permite busca e paginação.

## Tela de regras de custo

Rota:

```text
/coordenacao/regras-de-custo
```

Model:

```text
app/Models/CoordenacaoCostRule.php
```

Tabela:

```text
coordenacao_cost_rules
```

Campos principais:

- `name`;
- `source_type`;
- `match_value`;
- `billing_cycle`;
- `amount`;
- `sort_order`;
- `is_active`;
- `notes`.

Tipos de origem:

- `office_flag`: verifica uma flag de licença Office;
- `ad_group_contains`: verifica se o grupo AD contém o padrão;
- `ad_group_exact`: exige correspondência exata do grupo AD.

A comparação de grupos é normalizada para reduzir diferenças de maiúsculas, minúsculas, acentos e espaços.

É possível:

- cadastrar regra;
- editar regra;
- ativar ou desativar regra;
- definir valor;
- escolher ciclo de cobrança;
- definir ordem de aplicação;
- registrar observação.

## Avisos e registros fora do AD

O serviço gera avisos para registros existentes em `coreti_google_emails` ou `office_licenses` que não possuem correspondência no AD.

Esses registros:

- permanecem nas tabelas de origem;
- não entram no cálculo do custo;
- aparecem na dashboard e na tela de colaboradores;
- são exportados na aba de avisos.

Na verificação atual foram encontrados:

- 115 avisos de e-mail Google;
- 618 avisos de Office;
- 733 avisos no total.

## Exportação Excel

Rota:

```text
/coordenacao/exportar
```

Arquivo gerado:

```text
coordenacao_relatorio_YYYYMMDD_HHMMSS.xlsx
```

O arquivo possui as abas:

- **Resumo**: indicadores gerais e totais financeiros;
- **Colaboradores**: detalhamento individual;
- **Unidades**: consolidação por unidade;
- **Regras**: regras cadastradas e utilização;
- **Grupos AD**: frequência dos grupos e candidatos;
- **Avisos**: registros de e-mail/Office fora do AD.

Os filtros aplicados na tela também são enviados para a exportação quando o botão é acionado a partir de uma tela filtrada.

## Snapshot verificado atualmente

Na consulta realizada em **03/08/2026**, o relatório retornou:

| Indicador | Valor |
|---|---:|
| Pessoas consideradas | 3.988 |
| Pessoas com Office | 97 |
| Pessoas com custo em grupos | 1.871 |
| Regras ativas | 8 |
| Regras totais | 13 |
| Custo mensal estimado | R$ 54.369,17 |
| Custo pontual | R$ 0,00 |
| Custo Office mensal | R$ 0,00 |
| Custo de grupos mensal | R$ 54.369,17 |
| Unidades | 129 |
| Grupos AD detectados | 527 |
| Avisos totais | 733 |

Esses valores são dinâmicos e podem mudar conforme os dados do AD, Google, Office e as regras de custo.

## Limitações atuais

- O módulo é uma estimativa operacional, não um módulo contábil.
- Não há persistência de um fechamento mensal do custo.
- Não há histórico de versões do relatório ou das regras aplicadas.
- O custo Office está zerado nas regras atuais verificadas; para gerar valor, os preços precisam ser cadastrados.
- Grupos AD só geram custo quando existe regra ativa correspondente.
- Registros sem correspondência no AD são excluídos do cálculo e apenas alertados.
- A unidade usada no consolidado é o valor resolvido em `resolved_unit`; não há uma tabela própria de centros financeiros da Coordenação.
- A paginação das telas de colaboradores e unidades ocorre sobre coleções calculadas em memória.

## Arquivos principais

```text
app/Http/Controllers/CoordenacaoController.php
app/Services/CoordenacaoCostAnalysisService.php
app/Models/CoordenacaoCostRule.php
database/migrations/2026_06_30_000001_create_coordenacao_cost_rules_table.php
database/migrations/2026_06_30_000002_add_coordenacao_to_user_module_permissions_table.php
resources/views/coordenacao/dashboard.blade.php
resources/views/coordenacao/collaborators.blade.php
resources/views/coordenacao/units.blade.php
resources/views/coordenacao/rules.blade.php
resources/views/coordenacao/partials/nav.blade.php
```

## Resumo operacional

Atualmente, a Coordenação funciona como uma camada de análise sobre as bases existentes:

```text
ad_users
   + coreti_google_emails
   + office_licenses
   + regras coordenacao_cost_rules
          ↓
estimativa por colaborador
          ↓
consolidado por unidade
          ↓
dashboard e Excel
```

O sistema preserva as bases de origem e recalcula os valores conforme os dados atuais e as regras ativas.
