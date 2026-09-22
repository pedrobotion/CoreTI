<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $assunto }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f6f9;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#f3f6f9;padding:24px 12px;">
    <tr><td align="center">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:680px;background-color:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 4px 16px rgba(15,23,42,0.08);">
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
            <tr><td style="background-color:{{ $cor_tipo }};padding:11px 28px;"><div style="font-size:13px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:.5px;">{{ $tipo_mensagem }}</div></td></tr>
            <tr><td style="padding:30px 28px;">
                <h1 style="margin:0 0 18px;font-size:24px;line-height:1.3;color:#111827;">{{ $titulo }}</h1>
                <p style="margin:0 0 18px;font-size:15px;line-height:1.7;color:#4b5563;">Olá, {{ $destinatario }}.</p>
                @if($mensagem_principal)<p style="margin:0 0 22px;font-size:15px;line-height:1.7;color:#4b5563;">{{ $mensagem_principal }}</p>@endif

                @php($summary = [['Serviço', $servico], ['Status', $status], ['Início', $inicio], ['Previsão', $previsao], ['Impacto', $impacto], ['Protocolo', $protocolo]])
                @if(collect($summary)->contains(fn ($item) => filled($item[1])))
                    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border:1px solid #e5e7eb;border-radius:8px;margin-bottom:24px;"><tr><td style="padding:18px 20px;background-color:#f8fafc;">
                        <div style="font-size:13px;font-weight:700;color:#374151;margin-bottom:12px;">Resumo</div>
                        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                            @foreach($summary as [$label, $value])
                                @if(filled($value))<tr><td style="padding:5px 0;font-size:14px;color:#6b7280;width:160px;">{{ $label }}:</td><td style="padding:5px 0;font-size:14px;font-weight:{{ $label === 'Status' ? '600' : '400' }};color:{{ $label === 'Status' ? $cor_status : '#111827' }};">{{ $value }}</td></tr>@endif
                            @endforeach
                        </table>
                    </td></tr></table>
                @endif

                @if($orientacao)<div style="border-left:4px solid {{ $cor_tipo }};background-color:#f8fafc;padding:16px 18px;margin-bottom:24px;"><div style="font-size:13px;font-weight:700;color:#374151;margin-bottom:6px;">Orientação</div><div style="font-size:14px;line-height:1.6;color:#4b5563;">{{ $orientacao }}</div></div>@endif
                @if($url_acao)<table cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto 24px;"><tr><td align="center" style="background-color:#2563eb;border-radius:6px;"><a href="{{ $url_acao }}" style="display:inline-block;padding:13px 22px;font-size:14px;font-weight:700;color:#ffffff;text-decoration:none;">{{ $texto_botao }}</a></td></tr></table>@endif
                <p style="margin:0;font-size:14px;line-height:1.7;color:#6b7280;">Em caso de dúvidas, entre em contato com a equipe de Tecnologia da Informação.</p>
            </td></tr>
            <tr><td style="background-color:#f8fafc;border-top:1px solid #e5e7eb;padding:20px 28px;"><p style="margin:0 0 6px;font-size:13px;font-weight:700;color:#374151;">CoreTI — Tecnologia da Informação</p><p style="margin:0;font-size:12px;line-height:1.6;color:#9ca3af;">Esta é uma mensagem automática. Por favor, não responda diretamente a este e-mail.</p></td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
