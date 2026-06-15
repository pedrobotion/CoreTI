# CoreTI

Sistema interno em Laravel para gestão de acessos, painéis administrativos e módulo de circuitos/unidades.

## Stack

- PHP 8.2+
- Laravel 12
- SQLite (padrão local) ou outro banco suportado pelo Laravel
- Vite + TailwindCSS + Alpine.js

## Funcionalidades principais

- Autenticação de usuários (login, cadastro, recuperação de senha).
- Aprovação de contas por administrador (`is_active`).
- Controle de perfil/permissão (`role`: `admin` ou `user`).
- Gestão de usuários no painel admin:
  - Aprovação/reprovação de conta
  - Alteração de perfil
  - Reset seguro de senha por link/token
  - Exclusão de usuário
- Módulo de circuitos:
  - Listagem com filtros (texto livre, operadora, UF, serviço, unidade)
  - Paginação configurável
  - CRUD de circuitos/unidades
- Navegação lateral responsiva (desktop + mobile drawer).

## Setup local

1. Instalar dependências

```bash
composer install
npm install
```

2. Configurar ambiente

```bash
cp .env.example .env
php artisan key:generate
```

3. Banco de dados e migrações

```bash
php artisan migrate
```

4. Subir aplicação

```bash
npm run dev
php artisan serve
```

## Perfis de acesso

- `user`: acesso à área comum autenticada.
- `admin`: acesso adicional às áreas administrativas e gestão de usuários.

## Fluxo de aprovação de usuário

1. Novo cadastro entra como inativo (`is_active = false`).
2. Admin aprova no painel.
3. Somente usuário ativo consegue usar a área autenticada.

## Fluxo de reset de senha por admin

1. Admin inicia reset no painel de usuários.
2. Sistema invalida a senha atual e gera link com token.
3. Usuário redefine a senha pela tela de reset.
4. Flag de troca obrigatória é removida após redefinição.

## Testes

```bash
php artisan test
```

## Observações de manutenção

- Registrar alterações em `update.txt` no mesmo dia.
- Priorizar validações de autorização no backend (Policies/Gates), não apenas na view.
