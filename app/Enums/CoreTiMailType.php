<?php

namespace App\Enums;

enum CoreTiMailType: string
{
    case INFORMATION = 'INFORMAÇÃO';
    case WARNING = 'AVISO';
    case ALERT = 'ALERTA';
    case INCIDENT = 'INCIDENTE';
    case RESOLVED = 'RESOLVIDO';
    case MAINTENANCE = 'MANUTENÇÃO';

    public function color(): string
    {
        return match ($this) {
            self::INFORMATION => '#2563eb',
            self::WARNING => '#d97706',
            self::ALERT => '#ea580c',
            self::INCIDENT => '#dc2626',
            self::RESOLVED => '#16a34a',
            self::MAINTENANCE => '#7c3aed',
        };
    }

    public static function fromInput(string|self|null $type): self
    {
        if ($type instanceof self) {
            return $type;
        }

        $normalized = mb_strtoupper(trim((string) $type), 'UTF-8');
        foreach (self::cases() as $case) {
            if ($case->value === $normalized) {
                return $case;
            }
        }

        throw new \InvalidArgumentException('Tipo de e-mail CoreTI inválido.');
    }
}
