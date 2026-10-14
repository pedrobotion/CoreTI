<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertas de impressoras</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f6f9;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#f3f6f9;padding:24px 12px;">
    <tr><td align="center">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:680px;background-color:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 4px 16px rgba(15,23,42,0.08);">
            {{-- Cabeçalho --}}
            <tr><td style="background-color:#0f172a;padding:24px 28px;">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
                    <td>
                        @if($logo_url)
                            <img src="{{ $logo_url }}" alt="CoreTI" width="120" style="display:block;max-width:120px;height:auto;border:0;">
                        @else
                            <div style="font-size:26px;font-weight:700;color:#ffffff;">Core<span style="color:#3b82f6;">TI</span></div>
                        @endif
                        <div style="font-size:13px;color:#cbd5e1;margin-top:4px;">Tecnologia da Informação</div>
                    </td>
                    <td align="right" style="font-size:12px;color:#cbd5e1;vertical-align:top;">{{ $data_hora }}</td>
                </tr></table>
            </td></tr>
            <tr><td style="background-color:#dc2626;padding:11px 28px;"><div style="font-size:13px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:.5px;">INCIDENTE</div></td></tr>

            <tr><td style="padding:30px 28px;">
                <h1 style="margin:0 0 18px;font-size:24px;line-height:1.3;color:#111827;">Alertas de impressoras{{ ($regiao ?? null) ? ' — '.$regiao : '' }}</h1>
                <p style="margin:0 0 6px;font-size:15px;line-height:1.7;color:#4b5563;">Olá, equipe de Tecnologia da Informação.</p>
                <p style="margin:0 0 24px;font-size:15px;line-height:1.7;color:#4b5563;">
                    {{ $total }} alerta(s) de hardware aberto(s) nas impressoras
                    @if($criticos > 0)(<strong style="color:#dc2626;">{{ $criticos }} crítico(s)</strong>)@endif.
                    Cada cartão abaixo traz o equipamento, onde ele está e o erro explicado.
                </p>

                {{-- Um cartão por impressora --}}
                @foreach($alertas as $a)
                    @php($critico = ($a['severidade'] ?? '') === 'critico')
                    @php($cor = $critico ? '#dc2626' : '#f59e0b')
                    @php($selo = $critico ? '🔴 Crítico' : '🟠 Aviso')
                    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border:1px solid #e5e7eb;border-left:4px solid {{ $cor }};border-radius:8px;margin-bottom:16px;">
                        <tr><td style="padding:14px 18px 10px;">
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
                                <td style="font-size:15px;font-weight:700;color:#111827;">{{ $a['titulo'] }}</td>
                                <td align="right" style="font-size:12px;font-weight:700;color:{{ $cor }};white-space:nowrap;">{{ $selo }}</td>
                            </tr></table>
                            <div style="font-size:12px;color:#9ca3af;margin-top:2px;">Código: {{ $a['codigo'] }}</div>
                        </td></tr>
                        <tr><td style="padding:0 18px 12px;">
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#f8fafc;border-radius:6px;">
                                <tr>
                                    <td style="padding:8px 12px;font-size:13px;color:#6b7280;width:110px;">Impressora:</td>
                                    <td style="padding:8px 12px;font-size:13px;color:#111827;font-weight:600;">{{ $a['nome'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 12px;font-size:13px;color:#6b7280;">Nº de série:</td>
                                    <td style="padding:8px 12px;font-size:13px;color:#111827;">{{ $a['serie'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 12px;font-size:13px;color:#6b7280;">Unidade/Local:</td>
                                    <td style="padding:8px 12px;font-size:13px;color:#111827;">{{ $a['local'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 12px;font-size:13px;color:#6b7280;">IP na rede:</td>
                                    <td style="padding:8px 12px;font-size:13px;color:#111827;font-family:'Courier New',monospace;">{{ $a['ip'] }}</td>
                                </tr>
                            </table>
                        </td></tr>
                        <tr><td style="padding:0 18px 16px;">
                            <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:3px;">O que é</div>
                            <div style="font-size:13px;line-height:1.6;color:#4b5563;margin-bottom:10px;">{{ $a['o_que_e'] }}</div>
                            <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:3px;">O que fazer</div>
                            <div style="font-size:13px;line-height:1.6;color:#4b5563;">{{ $a['acao'] }}</div>
                        </td></tr>
                    </table>
                @endforeach

                @if($url_acao)
                    <table cellpadding="0" cellspacing="0" role="presentation" style="margin:8px auto 8px;"><tr><td align="center" style="background-color:#2563eb;border-radius:6px;"><a href="{{ $url_acao }}" style="display:inline-block;padding:13px 22px;font-size:14px;font-weight:700;color:#ffffff;text-decoration:none;">Abrir impressoras no CoreTI</a></td></tr></table>
                @endif
                <p style="margin:18px 0 0;font-size:13px;line-height:1.6;color:#9ca3af;">Alertas de suprimento (toner/papel baixo) não entram neste e-mail — eles ficam no painel de impressões.</p>
            </td></tr>

            <tr><td style="background-color:#f8fafc;border-top:1px solid #e5e7eb;padding:20px 28px;"><p style="margin:0 0 6px;font-size:13px;font-weight:700;color:#374151;">CoreTI — Tecnologia da Informação</p><p style="margin:0;font-size:12px;line-height:1.6;color:#9ca3af;">Esta é uma mensagem automática. Por favor, não responda diretamente a este e-mail.</p></td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
