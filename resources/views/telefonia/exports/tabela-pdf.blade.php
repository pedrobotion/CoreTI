{{-- Tabela genérica em PDF, usada por todas as exportações de telefonia e equipamentos. --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 1.4cm 1cm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #222; }
        .cabecalho { text-align: center; margin-bottom: 12px; }
        .cabecalho h1 { font-size: 13px; margin: 0 0 3px; color: #222; text-transform: uppercase; }
        .cabecalho p { font-size: 8px; color: #666; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #4472C4; color: #fff; padding: 5px 4px; text-align: left; border: 1px solid #ccc; font-size: 8px; }
        td { padding: 4px; border: 1px solid #ddd; vertical-align: top; }
        tr:nth-child(even) td { background: #F2F2F2; }
        tfoot td { background: #E7E6E6; font-weight: bold; border: 1px solid #999; }
        .rodape { position: fixed; bottom: -0.8cm; left: 0; right: 0; text-align: center; font-size: 7px; color: #888; }
    </style>
</head>
<body>
    <div class="cabecalho">
        <h1>{{ $titulo }}</h1>
        <p>{{ trim(($subtitulo ? $subtitulo.' · ' : '').'Gerado em '.now()->format('d/m/Y H:i')) }} · {{ count($linhas) }} registro(s)</p>
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($cabecalhos as $cabecalho)
                    <th>{{ $cabecalho }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $registro)
                <tr>
                    @foreach (array_values($registro) as $valor)
                        <td>{{ $valor }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($cabecalhos) }}" style="text-align:center; padding:16px; color:#888">Nenhum registro.</td></tr>
            @endforelse
        </tbody>
        @if ($rodape)
            <tfoot>
                <tr>
                    @foreach (array_values($rodape) as $valor)
                        <td>{{ $valor }}</td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="rodape">CoreTI · COCARI</div>
</body>
</html>
