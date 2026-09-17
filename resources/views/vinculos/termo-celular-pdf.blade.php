{{--
    Termo de Responsabilidade — Celular Corporativo.

    Reproduz o documento oficial da COCARI: mesma logomarca, mesmo título,
    numeração contínua da série em papel, as dez cláusulas na redação
    aprovada e o mesmo bloco de assinatura.

    Renderizado pelo dompdf, que não roda JavaScript e suporta um subconjunto
    de CSS — daí o estilo inline e a estrutura simples, sem Tailwind.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Termo de Responsabilidade nº {{ $termo['numero'] }}</title>
    <style>
        @page { margin: 1.5cm 2cm 1.6cm 2cm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #000; line-height: 1.45; }
        .logo { text-align: center; margin-bottom: 14px; }
        .logo img { height: 46px; }
        h1 { font-size: 14px; text-align: center; margin: 0; text-transform: uppercase; letter-spacing: .3px; }
        .subtitulo { text-align: center; font-size: 12px; margin: 2px 0 0; }
        .numero { text-align: center; font-size: 11px; font-weight: bold; margin: 6px 0 16px; }
        .declaracao { text-align: justify; margin-bottom: 10px; }
        .destaque { font-weight: bold; }
        ol { margin: 0 0 10px; padding-left: 18px; }
        ol li { text-align: justify; margin-bottom: 5px; }
        ol ol { margin-top: 5px; padding-left: 16px; list-style-type: lower-alpha; }
        .fecho { text-align: justify; margin: 12px 0 14px; }
        .estado { margin-bottom: 18px; }
        .estado .opcao { margin-left: 14px; }
        .cidade { margin: 18px 0 30px; }
        .assinatura { text-align: center; }
        .assinatura .linha { border-top: 1px solid #000; width: 62%; margin: 0 auto 4px; }
        .assinatura .nome { font-weight: bold; text-transform: uppercase; }
        table.dados { width: 100%; margin-top: 16px; border-collapse: collapse; font-size: 10px; }
        table.dados td { padding: 2px 0; vertical-align: top; }
        /* padding-right no rótulo: sem ele "Departamento:" encosta no valor. */
        table.dados .rot { font-weight: bold; white-space: nowrap; padding-right: 10px; }
        table.dados .val { width: 34%; }
    </style>
</head>
<body>
    <div class="logo">
        <img src="{{ public_path('images/branding/cocari-logo-termo.png') }}" alt="COCARI">
    </div>

    <h1>Termo de Responsabilidade</h1>
    <p class="subtitulo">Celular Corporativo</p>
    <p class="numero">Termo nº {{ $termo['numero'] }}</p>

    @php
        /*
         | Sem rótulo, os três números do cabeçalho (IMEI, chip) ficam
         | indistinguíveis para quem lê e assina o documento.
         |
         | Aparelho antigo pode não ter IMEI. Nesse caso o número de série é o
         | que identifica o bem — e um termo precisa identificar sem ambiguidade
         | o que está sendo entregue.
         */
        $identificador = $termo['imei']
            ? ['rotulo' => 'IMEI', 'valor' => $termo['imei']]
            : ($termo['numero_serie']
                ? ['rotulo' => 'Nº de série', 'valor' => $termo['numero_serie']]
                : ['rotulo' => 'IMEI', 'valor' => 'não informado']);
    @endphp
    <p class="declaracao">
        Declaro, sob as penas da lei, ter recebido da COCARI – Cooperativa Agropecuária e Industrial, um aparelho celular,
        modelo <span class="destaque">{{ $termo['modelo'] ?: '____________________' }}</span>
        --- {{ $identificador['rotulo'] }}: <span class="destaque">{{ $identificador['valor'] }}</span>
        --- Chip: <span class="destaque">{{ $termo['chip'] ?: '____________________' }}</span>
        --- obrigando-me a utilizá-lo de acordo com as seguintes condições:
    </p>

    <ol>
        <li>O aparelho celular e seus acessórios são de propriedade da COCARI, e deve ser usado durante o exercício da função do Usuário, não podendo ser cedido ou transferido a terceiros;</li>
        <li>Constitui obrigação do Usuário, zelar pelo aparelho celular recebido, de modo a mantê-lo sob sua guarda e segurança e em perfeitas condições de funcionamento, sem danos ao aparelho e nos demais acessórios;</li>
        <li>Em caso de danos ao aparelho e acessórios, é responsabilidade do usuário realizar a manutenção, sem ônus para a COCARI. O aparelho deve se manter sempre em perfeitas condições de uso.</li>
        <li>
            No caso de extravio, furto ou roubo do aparelho celular ou de seus acessórios, o Usuário deve:
            <ol>
                <li>Comunicar imediatamente o Departamento de Tecnologia da Informação da COCARI, para providenciar junto a Empresa de Telefonia Celular, o bloqueio provisório até que o chip de acesso seja reposto;</li>
                <li>Apresentar à COCARI, em até 24 horas, o Boletim de Ocorrência Policial, para que seja remetido à Empresa de Telefonia Celular, para bloqueio das chamadas, como condição para a continuidade do bloqueio das ligações telefônicas;</li>
            </ol>
        </li>
        <li>Quando solicitado, é obrigação do Usuário devolver o aparelho celular e seus acessórios à COCARI, em perfeitas condições de uso;</li>
        <li>A COCARI reserva o direito de auditar as contas sempre que julgar necessário; e realizar o gerenciamento remoto do aparelho;</li>
        <li>O número do aparelho celular pertence a COCARI, ou seja, não é pessoal do Usuário, ficando, desta forma, proibida a transferência do número para o Usuário;</li>
        <li>Normalmente a cada três anos, fica a critério da COCARI efetuar a troca e o destino do aparelho usado;</li>
        <li>Além das condições do presente Termo, o Usuário se obriga a cumprir as demais regras estabelecidas pela COCARI em Instrução Normativa ({{ $termo['instrucao_normativa'] }}) referente ao Uso de Celulares e Política de Segurança da Informação, bem como a atender a legislação externa aplicável.</li>
    </ol>

    <p class="fecho">
        E, para todos os efeitos legais, firmo o presente Termo de Responsabilidade, na qualidade de Usuário.
    </p>

    @php
        $marca = fn (bool $ativo) => $ativo ? '(X)' : '(&nbsp;&nbsp;)';
        $estado = $termo['estado'];
    @endphp
    <div class="estado">
        <p style="margin: 0 0 4px;"><span class="destaque">Estado do aparelho celular:</span></p>
        <p class="opcao" style="margin: 0;">{!! $marca($estado['tipo'] === 'novo') !!} Novo</p>
        <p class="opcao" style="margin: 0;">{!! $marca($estado['tipo'] === 'troca_vencimento') !!} Troca Venc.</p>
        <p class="opcao" style="margin: 0;">
            {!! $marca($estado['tipo'] === 'usado') !!} Usado, Número do Termo anterior:
            <span class="destaque">{{ $estado['termo_anterior'] ?? '__________' }}</span>
        </p>
    </div>

    <p class="cidade">{{ $termo['cidade'] }}, {{ $termo['data']->format('d/m/Y') }}</p>

    <div class="assinatura">
        <div class="linha"></div>
        <p class="nome" style="margin: 0;">{{ $termo['colaborador'] }}</p>
    </div>

    <table class="dados">
        <tr>
            <td class="rot">Área:</td>
            <td colspan="3">{{ $termo['area'] }}</td>
        </tr>
        <tr>
            <td class="rot">Matrícula:</td>
            <td class="val">{{ $termo['matricula'] }}</td>
            <td class="rot">Celular:</td>
            <td class="val">{{ $termo['chip'] ?: '—' }}</td>
        </tr>
        <tr>
            <td class="rot">Local:</td>
            <td class="val">{{ $termo['local'] }}</td>
            <td class="rot">Departamento:</td>
            <td class="val">{{ $termo['departamento'] }}</td>
        </tr>
        <tr>
            <td class="rot">Cargo:</td>
            <td colspan="3">{{ $termo['cargo'] }}</td>
        </tr>
    </table>
</body>
</html>
