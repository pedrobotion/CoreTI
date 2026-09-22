<?php

namespace App\Services;

use App\Enums\CoreTiMailType;

class CoreTiMailTemplateService
{
    public function prepare(array $data): array
    {
        $type = CoreTiMailType::fromInput($data['tipo_mensagem'] ?? null);
        $title = trim((string) ($data['titulo'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('O título do e-mail CoreTI é obrigatório.');
        }

        $url = $this->safeUrl($data['url_acao'] ?? null);

        return [
            'tipo_mensagem' => $type->value,
            'type' => $type->value,
            'cor_tipo' => $type->color(),
            'cor_status' => $this->safeColor($data['cor_status'] ?? $type->color(), $type->color()),
            'titulo' => $title,
            'assunto' => $this->subject($type, $title),
            'destinatario' => trim((string) ($data['destinatario'] ?? '')) ?: 'usuário',
            'mensagem_principal' => trim((string) ($data['mensagem_principal'] ?? '')),
            'servico' => trim((string) ($data['servico'] ?? '')),
            'status' => trim((string) ($data['status'] ?? '')),
            'inicio' => $this->optional($data['inicio'] ?? null),
            'previsao' => $this->optional($data['previsao'] ?? null),
            'impacto' => $this->optional($data['impacto'] ?? null),
            'protocolo' => $this->optional($data['protocolo'] ?? null),
            'orientacao' => $this->optional($data['orientacao'] ?? null),
            'url_acao' => $url,
            'texto_botao' => $url ? ($this->optional($data['texto_botao'] ?? null) ?: 'Acessar o CoreTI') : null,
            'data_hora' => trim((string) ($data['data_hora'] ?? now()->format('d/m/Y H:i'))),
            'logo_url' => $this->safeUrl(config('coreti.mail_logo_url')),
        ];
    }

    public function subject(CoreTiMailType|string $type, string $title): string
    {
        $type = CoreTiMailType::fromInput($type);
        return '[CoreTI] [' . $type->value . '] ' . trim($title);
    }

    private function optional(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value !== '' && $value !== '-' ? $value : null;
    }

    private function safeUrl(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '' || ! filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $value : null;
    }

    private function safeColor(mixed $value, string $fallback): string
    {
        $value = trim((string) $value);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? $value : $fallback;
    }
}
