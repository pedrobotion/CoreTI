# Auditoria de backups — acesso SSH ao servidor auditado

O CoreTI audita backups conectando por SSH e executando **apenas leitura**.
Nenhuma etapa exige `root`.

## Por que um wrapper, e não forced-command por arquivo

Um `command="stat ... /backup/dbmon/dbmon.onl"` no `authorized_keys` prende a
chave a um único arquivo. Com vários backups (`sdb1/dbmon`, `sdb1/erp`,
`sdb2/folha`…) seria uma chave por arquivo — inviável de operar e de rotacionar.

O wrapper resolve isso: a chave fica restrita a **um** programa, que aceita um
conjunto fechado de operações sobre uma allowlist de diretórios. Uma identidade
SSH atende todos os backups daquele servidor.

O wrapper está versionado no projeto em `scripts/coreti-backup-audit`.

## Passos no servidor auditado (ex.: sdb1)

### 1. Usuário dedicado, sem senha e sem privilégio

```bash
sudo useradd -r -m -s /bin/bash -c "Auditoria de backup CoreTI" coreti-monitor
sudo passwd -l coreti-monitor          # bloqueia autenticação por senha
```

### 2. Instalar o wrapper

Copie `scripts/coreti-backup-audit` do projeto para o servidor e:

```bash
sudo install -o root -g root -m 0755 coreti-backup-audit /usr/local/bin/coreti-backup-audit
```

Ajuste, dentro do arquivo, o array `DIRETORIOS_PERMITIDOS` para os diretórios
de backup daquele servidor. O wrapper recusa caminho fora da allowlist, `..`,
symlink apontando para fora, metacaracteres de shell e qualquer operação que
não seja `ping`, `stat` ou `progress-log`.

### 3. Autorizar a chave restrita ao wrapper

```bash
sudo -u coreti-monitor mkdir -p /home/coreti-monitor/.ssh
sudo -u coreti-monitor chmod 700 /home/coreti-monitor/.ssh

# Uma única linha. A chave só consegue executar o wrapper.
printf '%s %s\n' \
  'restrict,command="/usr/local/bin/coreti-backup-audit"' \
  'ssh-ed25519 COLE_AQUI_A_CHAVE_PUBLICA_DO_CORETI coreti@auditoria-backup' \
  | sudo -u coreti-monitor tee -a /home/coreti-monitor/.ssh/authorized_keys

sudo -u coreti-monitor chmod 600 /home/coreti-monitor/.ssh/authorized_keys
```

`restrict` já desliga port forwarding, agent forwarding, X11 e alocação de tty.

### 4. Permissão de leitura, sem excesso

Use `X` maiúsculo: concede `x` (atravessar) **apenas a diretórios**, nunca
marcando arquivos de dados como executáveis.

```bash
sudo setfacl -R  -m u:coreti-monitor:rX /backup/dbmon   # itens existentes
sudo setfacl -R -d -m u:coreti-monitor:rX /backup/dbmon # herança para novos
```

Confira o resultado:

```bash
getfacl /backup/dbmon
sudo -u coreti-monitor stat -c '%s|%Y' /backup/dbmon/dbmon.onl
```

### 5. Validar a partir do CoreTI

```bash
# Direto:
ssh -o BatchMode=yes coreti-monitor@172.17.1.10 'stat /backup/dbmon/dbmon.onl'

# Pelo CoreTI:
php artisan backups:verificar --todos
```

Ou o botão **Testar conexão SSH** na tela do alvo, que executa apenas `ping`.

### 6. Ativar o wrapper no CoreTI

```
BACKUP_SSH_WRAPPER=/usr/local/bin/coreti-backup-audit
```

Sem essa variável o CoreTI monta `stat` diretamente — o que exige que a chave
NÃO esteja presa a um forced-command.

## Rotação da chave

Substituir a linha em `authorized_keys` de cada servidor. Como a restrição está
no wrapper e não no comando da chave, trocar a chave não exige revisar caminho
nenhum.
