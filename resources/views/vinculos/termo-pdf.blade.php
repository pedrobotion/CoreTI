{{--
    Termo de responsabilidade — documento impresso e assinado.

    Renderizado pelo dompdf, que não roda JavaScript e suporta um subconjunto
    de CSS: por isso o estilo é inline e a estrutura é simples, sem Tailwind.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Termo de Responsabilidade #{{ $assignment->id }}</title>
    <style>
        @page { margin: 2.2cm 2cm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; line-height: 1.5; }
        h1 { font-size: 15px; text-align: center; margin: 0 0 4px; text-transform: uppercase; }
        .sub { text-align: center; font-size: 10px; color: #555; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #999; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #eee; font-size: 10px; text-transform: uppercase; }
        .dados td { border: none; padding: 2px 0; }
        .dados .rot { color: #555; width: 32%; }
        .texto { text-align: justify; margin: 14px 0; }
        .texto li { margin-bottom: 6px; }
        .assinatura { margin-top: 48px; text-align: center; }
        .linha-assinatura { border-top: 1px solid #333; width: 68%; margin: 0 auto; padding-top: 5px; }
        .rodape { margin-top: 26px; font-size: 9px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <h1>Termo de Responsabilidade</h1>
    <p class="sub">COCARI · {{ $ctx->label }} · Operação {{ $assignment->scope_label }} · Registro nº {{ $assignment->id }}</p>

    <table class="dados">
        <tr><td class="rot">Colaborador</td><td><strong>{{ $assignment->collaborator?->name }}</strong></td></tr>
        <tr><td class="rot">Matrícula</td><td>{{ $assignment->collaborator_matricula }}</td></tr>
        @if ($unidadeColaborador)
            <tr><td class="rot">Unidade</td><td>{{ $unidadeColaborador }}</td></tr>
        @endif
        <tr><td class="rot">Data da entrega</td><td>{{ $assignment->data_vinculo?->format('d/m/Y') }}</td></tr>
        <tr><td class="rot">Vigência até</td><td>{{ $assignment->data_vencimento?->format('d/m/Y') }}</td></tr>
    </table>

    <table>
        <thead>
            <tr><th style="width: 26%">Tipo</th><th>Identificação do item</th></tr>
        </thead>
        <tbody>
            @foreach ($assignment->items as $item)
                <tr>
                    <td>{{ $item->tipoLabel() }}</td>
                    <td>{{ $item->descricao() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($assignment->observacoes)
        <p><strong>Observações:</strong> {{ $assignment->observacoes }}</p>
    @endif

    <div class="texto">
        <p>Declaro ter recebido da COCARI o(s) item(ns) discriminado(s) acima, em perfeito estado de conservação e funcionamento, comprometendo-me a:</p>
        <ol>
            <li>Utilizá-lo(s) exclusivamente no exercício das minhas atividades profissionais;</li>
            <li>Zelar pela sua guarda e conservação, respondendo por danos decorrentes de uso indevido, negligência ou extravio;</li>
            <li>Comunicar imediatamente ao setor de TI qualquer defeito, perda, furto ou roubo;</li>
            <li>Não ceder, emprestar ou transferir o(s) item(ns) a terceiros;</li>
            <li>Devolvê-lo(s) à COCARI quando solicitado ou no encerramento do meu contrato de trabalho.</li>
        </ol>
        <p>Estou ciente de que a não devolução ou a devolução com danos não decorrentes do uso normal poderá ensejar o ressarcimento do prejuízo, nos termos da legislação vigente.</p>
    </div>

    <div class="assinatura">
        <p>Mandaguari, _____ de __________________ de {{ now()->year }}.</p>
        <div class="linha-assinatura">
            {{ $assignment->collaborator?->name }}<br>
            Matrícula {{ $assignment->collaborator_matricula }}
        </div>
    </div>

    <p class="rodape">Documento gerado pelo CoreTI em {{ now()->format('d/m/Y H:i') }} · Registro nº {{ $assignment->id }}</p>
</body>
</html>
