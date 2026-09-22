CORETI — TECNOLOGIA DA INFORMAÇÃO

TIPO: {{ $tipo_mensagem }}

{{ $titulo }}

Olá, {{ $destinatario }}.

@if($mensagem_principal){{ $mensagem_principal }}
@endif
@if($servico || $status || $inicio || $previsao || $impacto || $protocolo)
RESUMO
@if($servico)Serviço: {{ $servico }}
@endif
@if($status)Status: {{ $status }}
@endif
@if($inicio)Início: {{ $inicio }}
@endif
@if($previsao)Previsão: {{ $previsao }}
@endif
@if($impacto)Impacto: {{ $impacto }}
@endif
@if($protocolo)Protocolo: {{ $protocolo }}
@endif
@endif
@if($orientacao)
ORIENTAÇÃO
{{ $orientacao }}
@endif
@if($url_acao)
Mais informações:
{{ $url_acao }}
@endif

CoreTI — Tecnologia da Informação

Esta é uma mensagem automática. Por favor, não responda diretamente a este e-mail.
