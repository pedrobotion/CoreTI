<#
    CoretiAdProtecao.psm1

    Regra unica que decide se uma conta do Active Directory pode ou nao ser
    excluida pelo expurgo.

    Vive em modulo separado de proposito: o script de exclusao, o de listagem e
    o de teste importam ESTA funcao. Se cada um tivesse a sua copia, a
    verificacao poderia continuar passando enquanto a exclusao usasse uma regra
    diferente - exatamente o cenario que nao pode acontecer aqui.
#>

# RIDs abaixo deste valor sao reservados a contas internas do Windows.
$script:RidMaximoReservado = 1000

$script:ContasProtegidas = @(
    'krbtgt', 'Administrator', 'Administrador', 'Guest', 'Convidado',
    'DefaultAccount', 'WDAGUtilityAccount'
)

<#
    Padroes de nome protegidos (curinga * aceito).

    Nome exato nao basta: cada controlador somente leitura (RODC) tem a sua
    propria conta krbtgt_<numero>, com RID normal (>= 1000) e sem grupo
    privilegiado. Ela nao cai em nenhum outro criterio - e apagar uma derruba a
    autenticacao Kerberos do site atendido por aquele RODC.

    O padrao cobre tambem os RODCs que forem criados depois, sem precisar
    lembrar de cadastrar cada um.
#>
$script:PadroesProtegidos = @(
    'krbtgt*',      # krbtgt e as contas krbtgt_<RODC>
    'MSOL_*'        # sincronizacao de diretorio (Entra Connect / DirSync)
)

<#
    Contas adicionais protegidas por decisao local.

    Uma por linha, em contas_protegidas_extra.txt na pasta dos scripts. Aceita
    curinga e ignora linhas vazias e comecadas por #. Permite proteger uma
    conta sem editar este modulo.
#>
function Get-CoretiPadroesExtra {
    $arquivo = Join-Path $PSScriptRoot 'contas_protegidas_extra.txt'

    if (-not (Test-Path -LiteralPath $arquivo)) { return @() }

    return @(
        Get-Content -LiteralPath $arquivo -Encoding UTF8 |
            ForEach-Object { $_.Trim() } |
            Where-Object { $_ -ne '' -and -not $_.StartsWith('#') }
    )
}

function Get-CoretiPadroesProtegidos {
    return @($script:PadroesProtegidos) + (Get-CoretiPadroesExtra)
}

<# A conta casa algum nome ou padrao protegido? #>
function Test-CoretiNomeProtegido {
    [CmdletBinding()]
    param([Parameter(Mandatory = $true)][AllowEmptyString()][string]$Nome)

    if ([string]::IsNullOrWhiteSpace($Nome)) { return $true }   # falha fechada

    if ($script:ContasProtegidas -contains $Nome) { return $true }

    foreach ($padrao in (Get-CoretiPadroesProtegidos)) {
        if ($Nome -like $padrao) { return $true }
    }

    return $false
}

# Grupos privilegiados identificados por RID, nao por nome: em dominio em
# portugues os nomes sao traduzidos ("Admins. do dominio") e podem ate ser
# renomeados. O RID nao muda nunca.
$script:RidsGruposPrivilegiados = @(
    512,  # Admins. do dominio
    516,  # Controladores de dominio
    517,  # Cert Publishers
    518,  # Admins. do esquema
    519,  # Admins. da empresa
    520,  # Proprietarios Criadores de Diretiva de Grupo
    521,  # Controladores de dominio somente leitura
    525,  # Usuarios protegidos
    498   # Controladores de dominio empresariais somente leitura
)

# Grupos internos (dominio local). O SID e fixo em qualquer dominio.
$script:SidsGruposBuiltin = @(
    'S-1-5-32-544',  # Administradores
    'S-1-5-32-548',  # Operadores de contas
    'S-1-5-32-549',  # Operadores de servidores
    'S-1-5-32-550',  # Operadores de impressao
    'S-1-5-32-551',  # Operadores de copia
    'S-1-5-32-552',  # Replicador
    'S-1-5-32-569'   # Operadores criptograficos
)

$script:CachePrivilegiados = $null
$script:GruposPrivilegiadosEncontrados = @()

function ConvertTo-CoretiFiltroLdap {
    param([string]$Texto)
    $t = $Texto -replace '\\', '\5c'
    $t = $t -replace '\(', '\28'
    $t = $t -replace '\)', '\29'
    $t = $t -replace '\*', '\2a'
    return $t
}

<#
.SYNOPSIS
    Devolve o conjunto de DistinguishedName de todas as contas que pertencem,
    direta ou indiretamente, a algum grupo privilegiado.

.DESCRIPTION
    Consulta o AD uma vez por grupo usando LDAP_MATCHING_RULE_IN_CHAIN
    (1.2.840.113556.1.4.1941), que percorre grupos aninhados. Uma conta que
    esta em "TI-Admins", e "TI-Admins" esta em "Admins. do dominio", e
    encontrada - o memberOf direto da conta nao mostraria isso.

    O resultado fica em cache durante a sessao. Use -Recarregar apos alterar
    associacoes de grupo.

    Se a consulta falhar, lanca excecao em vez de devolver conjunto vazio: nao
    saber quem e privilegiado nao pode ser tratado como "ninguem e".
#>
function Get-CoretiPrivilegiadosDN {
    [CmdletBinding()]
    param([switch]$Recarregar)

    if ($null -ne $script:CachePrivilegiados -and -not $Recarregar) {
        return ,$script:CachePrivilegiados
    }

    if (-not (Get-Command Get-ADDomain -ErrorAction SilentlyContinue)) {
        throw 'Modulo ActiveDirectory nao carregado: nao da para verificar grupos privilegiados.'
    }

    $dominio = Get-ADDomain
    $sidDominio = [string]$dominio.DomainSID.Value
    if ([string]::IsNullOrWhiteSpace($sidDominio)) {
        throw 'Nao foi possivel obter o SID do dominio.'
    }

    $sids = @()
    foreach ($rid in $script:RidsGruposPrivilegiados) { $sids += "$sidDominio-$rid" }
    $sids += $script:SidsGruposBuiltin

    # ::new() em vez de New-Object: com StringComparer o New-Object erra a
    # sobrecarga do construtor e lanca "os tipos de argumento nao correspondem".
    $conjunto = [System.Collections.Generic.HashSet[string]]::new([StringComparer]::OrdinalIgnoreCase)
    $grupos = [System.Collections.Generic.List[object]]::new()

    foreach ($sid in $sids) {
        try {
            $grupo = Get-ADGroup -Identity $sid -ErrorAction Stop
        }
        catch {
            # Grupo inexistente neste dominio (ex.: Admins. da empresa fora da
            # floresta raiz). Nao e erro.
            continue
        }

        $dnFiltro = ConvertTo-CoretiFiltroLdap -Texto $grupo.DistinguishedName
        $membros = @(Get-ADUser -LDAPFilter "(memberOf:1.2.840.113556.1.4.1941:=$dnFiltro)")

        foreach ($m in $membros) { [void]$conjunto.Add([string]$m.DistinguishedName) }

        $grupos.Add([pscustomobject]@{
            grupo    = $grupo.Name
            sid      = $sid
            usuarios = $membros.Count
        })
    }

    $script:GruposPrivilegiadosEncontrados = $grupos
    $script:CachePrivilegiados = $conjunto

    # A virgula e obrigatoria: "return $conjunto" ENUMERA o HashSet e quem
    # chama recebe um array (ou $null, se vazio). Ai .Contains() deixa de ser o
    # do HashSet - vira busca em array, ou estoura em objeto nulo.
    return ,$conjunto
}

function Get-CoretiGruposPrivilegiados {
    if ($null -eq $script:CachePrivilegiados) { [void](Get-CoretiPrivilegiadosDN) }
    return ,$script:GruposPrivilegiadosEncontrados
}

<#
.SYNOPSIS
    Devolve o motivo pelo qual a conta e protegida, ou $null se ela pode ser
    avaliada para exclusao.

.DESCRIPTION
    Criterios independentes; basta um para proteger:

    1) RID do SID abaixo de 1000. Todo objeto do dominio tem SID terminado em
       "-<RID>". Os RIDs reservados NAO mudam nem se a conta for renomeada -
       unica protecao a prova de renomeacao, pratica comum de seguranca com a
       conta Administrador.

         500 Administrador   501 Convidado   502 krbtgt
         503 DefaultAccount  504 WDAGUtilityAccount

       Excluir a krbtgt derruba a autenticacao Kerberos do dominio inteiro.

    2) SamAccountName na lista de nomes reservados.

    3) Membro, direto ou por grupo aninhado, de algum grupo privilegiado.
       Reflete a associacao ATUAL, entao acompanha alteracoes de "Membro de".

    4) Grupo primario privilegiado. O grupo primario nao aparece em memberOf.

    adminCount NAO bloqueia. E marca historica: o AdminSDHolder a grava ao
    entrar no grupo e nunca a remove ao sair. Bloquear por ela tornaria eternas
    as contas de quem um dia foi administrador. Ela vira aviso, em
    Get-CoretiAvisosConta, e a decisao fica com quem aprova.

.PARAMETER Usuario
    Objeto vindo de Get-ADUser, trazendo as propriedades de
    Get-CoretiPropriedadesNecessarias.
#>
function Test-CoretiContaProtegida {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)]
        $Usuario
    )

    if ($null -eq $Usuario) {
        # Falha fechada: sem objeto, trata como protegida.
        return 'objeto de usuario ausente'
    }

    $sam = [string]$Usuario.SamAccountName

    if ($script:ContasProtegidas -contains $sam) {
        return 'conta interna do Windows (nome reservado)'
    }

    foreach ($padrao in (Get-CoretiPadroesProtegidos)) {
        if ($sam -like $padrao) {
            return "conta protegida por padrao de nome ($padrao)"
        }
    }

    if ($Usuario.SID -and ([string]$Usuario.SID.Value) -match '-(\d+)$') {
        $rid = [int]$Matches[1]
        if ($rid -lt $script:RidMaximoReservado) {
            return "conta interna do Windows (RID $rid)"
        }
    }
    elseif (-not $Usuario.SID) {
        # Sem SID nao da para avaliar o RID; nao arrisca.
        return 'SID indisponivel para verificacao'
    }

    $dn = [string]$Usuario.DistinguishedName
    if ([string]::IsNullOrWhiteSpace($dn)) {
        return 'DistinguishedName ausente'
    }

    # Lanca se nao conseguir consultar o AD - nunca devolve conjunto vazio.
    $privilegiados = Get-CoretiPrivilegiadosDN

    if ($null -eq $privilegiados) {
        throw 'Conjunto de contas privilegiadas indisponivel; abortando por seguranca.'
    }

    if ($privilegiados.Contains($dn)) {
        return 'membro de grupo privilegiado'
    }

    if ($null -ne $Usuario.PrimaryGroupID) {
        $pgid = [int]$Usuario.PrimaryGroupID
        if ($script:RidsGruposPrivilegiados -contains $pgid) {
            return "grupo primario privilegiado (RID $pgid)"
        }
    }

    return $null
}

<#
.SYNOPSIS
    Avisos que NAO bloqueiam a exclusao, apenas pedem conferencia humana.

.DESCRIPTION
    adminCount = 1 e marca deixada pelo AdminSDHolder quando a conta entra em
    grupo privilegiado. Ela NAO e removida quando a conta sai, entao permanece
    indefinidamente em quem ja foi administrador um dia.

    Bloquear por ela tornaria essas contas eternas: nunca poderiam ser
    excluidas, por mais tempo que ficassem desativadas. E ela nao descreve
    privilegio atual - quem descreve e a associacao de grupo, verificada em
    Test-CoretiContaProtegida com consulta de grupo aninhado.

    Por isso vira aviso: a conta aparece na tela do CoreTI, desmarcada por
    padrao, e uma pessoa decide.
#>
function Get-CoretiAvisosConta {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)]
        $Usuario
    )

    $avisos = @()

    if ($null -eq $Usuario) { return $avisos }

    if ($Usuario.adminCount -eq 1) {
        $avisos += 'ja foi privilegiada (adminCount=1)'
    }

    return $avisos
}

<# Conveniencia: a conta carrega a marca historica de privilegio? #>
function Test-CoretiJaFoiPrivilegiada {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)]
        $Usuario
    )

    return ($null -ne $Usuario -and $Usuario.adminCount -eq 1)
}

function Get-CoretiContasProtegidas {
    return $script:ContasProtegidas
}

function Get-CoretiRidMaximoReservado {
    return $script:RidMaximoReservado
}

function Get-CoretiRidsGruposPrivilegiados {
    return $script:RidsGruposPrivilegiados
}

# Propriedades que Get-ADUser precisa trazer para a verificacao funcionar.
function Get-CoretiPropriedadesNecessarias {
    return @('SamAccountName', 'SID', 'adminCount', 'Enabled',
             'DistinguishedName', 'PrimaryGroupID', 'memberOf')
}

Export-ModuleMember -Function Test-CoretiContaProtegida,
                              Test-CoretiNomeProtegido,
                              Get-CoretiPadroesProtegidos,
                              Get-CoretiAvisosConta,
                              Test-CoretiJaFoiPrivilegiada,
                              Get-CoretiPrivilegiadosDN,
                              Get-CoretiGruposPrivilegiados,
                              Get-CoretiContasProtegidas,
                              Get-CoretiRidMaximoReservado,
                              Get-CoretiRidsGruposPrivilegiados,
                              Get-CoretiPropriedadesNecessarias
