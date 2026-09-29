# Coleta do log de impressão — IMP01 / IMP02 / IMP03

O CoreTI **não** conecta nos servidores de impressão e **não** guarda senha de
domínio. Quem lê o log é uma tarefa agendada rodando em cada servidor, com a
conta que já tem acesso a ele, e que posta o resultado no CoreTI.

É o mesmo desenho usado para o Active Directory. A diferença prática: se o
token vazar, trocá-lo é uma linha no `.env` dos dois lados — trocar uma senha
de domínio guardada no CoreTI seria bem mais caro.

## O que instalar em cada servidor

Copie para uma pasta local, por exemplo `C:\CoretiImpressao\`:

- `coletar_impressoes.ps1`
- `diagnosticar.ps1`
- `coreti.impressao.json` (criado a partir do `.exemplo`)

```powershell
Unblock-File C:\CoretiImpressao\*.ps1
```

> Arquivo copiado de outra máquina chega bloqueado pelo Windows e o
> PowerShell recusa executá-lo. O `Unblock-File` resolve, e precisa ser
> repetido sempre que o script for recopiado.

### `coreti.impressao.json`

```json
{
  "url": "https://coreti.cocari.com.br",
  "servidor": "IMP01",
  "token": "<valor de IMPRESSAO_TOKEN no .env do CoreTI>"
}
```

Mude `servidor` em cada máquina: `IMP01`, `IMP02`, `IMP03`. É esse nome que
aparece no painel — se dois servidores usarem o mesmo, os números se somam e a
falha de um deles fica invisível.

## Primeiro: diagnosticar

```powershell
cd C:\CoretiImpressao
.\diagnosticar.ps1
```

Roda em segundos e responde as três perguntas que importam: o canal existe,
está ligado, e quantos eventos 307 há na janela. Não lê o conteúdo dos
eventos, só conta.

Se ele acusar o canal desligado, não adianta seguir: **não existe evento 307
para coletar**. O canal `Operational` vem desabilitado por padrão no Windows
Server.

## Conferir antes de agendar

```powershell
cd C:\CoretiImpressao
.\coletar_impressoes.ps1 -Simular
```

Lê o log e mostra uma amostra **sem enviar nada**. Se aparecerem eventos com
usuário e impressora preenchidos, está pronto.

Depois, um envio de verdade:

```powershell
.\coletar_impressoes.ps1
```

## Agendar

Em PowerShell **como administrador**, no próprio servidor:

```powershell
schtasks /Create ^
  /TN "CoreTI - Coleta de Impressoes" ^
  /TR "powershell.exe -NoProfile -ExecutionPolicy Bypass -File C:\CoretiImpressao\coletar_impressoes.ps1" ^
  /SC HOURLY ^
  /RU SYSTEM ^
  /RL HIGHEST ^
  /F
```

Rodar como **SYSTEM** é proposital: essa conta já tem leitura no log do
PrintService e alcança a rede pela conta de máquina, então **nenhuma senha
precisa ser guardada na tarefa**. Uma tarefa com senha gravada é uma senha a
mais para girar quando alguém sair da equipe.

Para conferir e forçar uma execução:

```powershell
schtasks /Query /TN "CoreTI - Coleta de Impressoes" /V /FO LIST
schtasks /Run   /TN "CoreTI - Coleta de Impressoes"
```

De hora em hora é folgado de propósito: o script pergunta ao CoreTI até onde
já enviou e busca só o que falta, então uma execução frequente custa pouco.

### O CoreTI avisa se a coleta parar

Cada envio registra o contato do servidor — **inclusive quando não houve
impressão nenhuma**. Sem isso, todo domingo pareceria coletor parado, e alarme
que grita à toa deixa de ser lido.

Na tela inicial do CoreTI, em *Status das sincronizações*, a linha **Coleta de
Impressões** fica vermelha nomeando o servidor que passou **6 horas** sem
falar. Isso importa porque o log do Windows retém poucos dias: coleta parada
além disso vira histórico perdido, não apenas atrasado.

## Por que reexecutar é seguro

O CoreTI identifica cada evento por **servidor + trabalho + instante +
usuário** e descarta o que já tem. Rodar de novo sobre a mesma janela não
duplica nada.

Isso é proposital: significa que uma execução perdida — servidor reiniciando,
rede caindo, tarefa desabilitada por engano — se recupera sozinha na execução
seguinte, em vez de virar buraco permanente na estatística.

O script também volta **uma hora atrás** do último evento conhecido, para
pegar evento que entrou no log depois da leitura anterior.

## Se algo der errado

**"Configuracao nao encontrada"**
O `coreti.impressao.json` não está na mesma pasta do `.ps1`.

**HTTP 401**
Token diferente do `IMPRESSAO_TOKEN` no `.env` do CoreTI.

**HTTP 403**
O IP do servidor não está em `IMPRESSAO_ALLOWED_IPS`. Hoje a lista tem
172.17.3.46, .47 e .48.

**HTTP 503**
O CoreTI está sem `IMPRESSAO_TOKEN` configurado. O endpoint falha fechado de
propósito: ele escreve no banco e não pode liberar por falta de configuração.

**O log está cheio (tamanho igual ao máximo)**

O canal do PrintService tem tamanho fixo e, ao encher, passa a apagar o evento
mais antigo para dar lugar ao novo. Um canal de 24 MB com 48 mil registros
guarda poucas semanas num servidor movimentado.

Duas saídas, que se somam:

1. Coletar com frequência — o CoreTI guarda para sempre o que já recebeu.
2. Aumentar o canal: Visualizador de Eventos → PrintService → Operational →
   Propriedades → **Tamanho máximo do log**. 200 MB é um valor confortável.

Enquanto o log estiver cheio, todo dia que passar sem coleta é histórico
perdido em definitivo.

**A tela para depois de "Coletando desde ..." e não sai mais dali**

Provavelmente ainda está trabalhando. Ler o log e converter os eventos são as
duas partes lentas, e nas primeiras versões do script elas não davam nenhum
retorno na tela.

A primeira versão usava `Get-WinEvent`, que monta um objeto rico para cada
evento — num canal com 48 mil registros esse custo por evento domina o tempo e
a tela fica parada. A versão atual usa o leitor de eventos do .NET, que entrega
o mesmo dado sem essa camada, avisa que está lendo e conta de 1.000 em 1.000.

Se mesmo assim parar por muito tempo numa mesma linha:

1. Rode `.\diagnosticar.ps1`: ele lê uma amostra e informa o ritmo em
   eventos por segundo, o que já diz se a coleta leva segundos ou minutos.
2. Reduza a janela: `.\coletar_impressoes.ps1 -Dias 1`.
3. Se o log for enorme, limite a carga: `-MaxEventos 5000`. O que sobrar entra
   na execução seguinte, porque o script retoma de onde parou.

Vale também descartar uma causa banal: o console do Windows congela a saída
quando alguém clica dentro da janela com o QuickEdit ligado. Pressione `Esc`
ou `Enter` e veja se volta a andar.

**"No events were found"**
Não houve impressão na janela, ou o canal
`Microsoft-Windows-PrintService/Operational` está desabilitado. Confira em
Visualizador de Eventos → Aplicativos e Serviços → Microsoft → Windows →
PrintService.

> O canal **Operational** costuma vir desligado por padrão no Windows Server.
> Sem ele não há evento 307 nenhum, e o script não tem o que enviar.

## Importar histórico que já existe em planilha

O log do Windows gira e apaga os eventos antigos. Para não perder o que já foi
exportado:

```bash
php artisan impressao:importar-planilha /caminho/Relatorio_Impressoes.xlsx
php artisan impressao:importar-planilha /caminho/arquivo.xlsx --simular
```

Passa pelo mesmo caminho do envio automático, então importar duas vezes também
não duplica. Os eventos entram com a origem `PLANILHA`, para ficarem
distinguíveis do que veio direto dos servidores.

### Depois que os servidores começarem a enviar

A planilha é uma exportação dos mesmos logs. A chave que evita duplicata
inclui a origem — necessário, porque o número do trabalho recicla dentro de
cada fila e sem isso dois servidores colidiriam entre si. O efeito colateral é
que, no período em que as duas fontes se cruzam, o mesmo trabalho existe duas
vezes: uma como `PLANILHA`, outra como `IMP01`.

Depois da primeira coleta completa dos três servidores:

```bash
php artisan impressao:consolidar --simular
php artisan impressao:consolidar
```

Ele remove da importação o que os servidores já entregaram e **mantém o que só
existe na planilha** — o trecho que o log do Windows já girou e apagou, que é
justamente o histórico que se perderia.
