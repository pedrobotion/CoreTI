<?php

namespace App\Support;

use App\Models\CorpDevice;
use App\Models\CorpLine;
use App\Models\Equipment;
use InvalidArgumentException;

/**
 * Os dois pontos de entrada do vínculo.
 *
 * O motor é um só — um vínculo, N itens, um termo, um desvínculo —, mas quem
 * cuida de telefonia e quem cuida de equipamento são times diferentes, com
 * permissões diferentes. Em vez de duplicar controller e telas (o que o
 * gestaoTI fez, com 1.600 linhas quase idênticas), o mesmo controller atende
 * os dois contextos e cada um enxerga só os tipos de item que lhe dizem
 * respeito.
 */
class AssignmentContext
{
    public const TELEFONIA = 'telefonia';

    public const EQUIPAMENTOS = 'equipamentos';

    /** Colunas de item por tipo. */
    public const COLUNAS = [
        'linha' => 'corp_line_id',
        'aparelho' => 'corp_device_id',
        'equipamento' => 'equipment_id',
    ];

    /** @var array<string, class-string> */
    public const MODELOS = [
        'linha' => CorpLine::class,
        'aparelho' => CorpDevice::class,
        'equipamento' => Equipment::class,
    ];

    public const ROTULOS = [
        'linha' => 'Linha corporativa',
        'aparelho' => 'Aparelho celular',
        'equipamento' => 'Equipamento',
    ];

    private function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $module,
        public readonly string $routePrefix,
        public readonly string $backRoute,
        /** @var array<int, string> */
        public readonly array $kinds,
        public readonly string $itemLabel,
    ) {}

    public static function make(string $key): self
    {
        return match ($key) {
            self::TELEFONIA => new self(
                key: self::TELEFONIA,
                label: 'Telefonia Corporativa',
                module: 'telefonia',
                routePrefix: 'administrativo.telefonia.assignments.',
                backRoute: 'administrativo.telefonia.lines.index',
                kinds: ['linha', 'aparelho'],
                itemLabel: 'Vínculo',
            ),
            self::EQUIPAMENTOS => new self(
                key: self::EQUIPAMENTOS,
                label: 'Equipamentos',
                module: 'equipamentos',
                routePrefix: 'service-desk.equipamentos.assignments.',
                backRoute: 'service-desk.equipamentos.index',
                kinds: ['equipamento'],
                itemLabel: 'Entrega',
            ),
            default => throw new InvalidArgumentException("Contexto de vínculo desconhecido: {$key}."),
        };
    }

    public function route(string $name, mixed $parameters = []): string
    {
        return route($this->routePrefix.$name, $parameters);
    }

    public function routeIs(string $pattern): bool
    {
        return request()->routeIs($this->routePrefix.$pattern);
    }

    /**
     * Colunas de item que este contexto manipula.
     *
     * @return array<int, string>
     */
    public function colunas(): array
    {
        return array_map(fn (string $kind) => self::COLUNAS[$kind], $this->kinds);
    }

    public function aceita(string $kind): bool
    {
        return in_array($kind, $this->kinds, true);
    }

    public function podeGerenciar(): bool
    {
        return (bool) request()->user()?->canModule($this->module, 'manage');
    }

    /** @return class-string */
    public static function modelo(string $kind): string
    {
        return self::MODELOS[$kind] ?? throw new InvalidArgumentException("Tipo de item desconhecido: {$kind}.");
    }

    public static function coluna(string $kind): string
    {
        return self::COLUNAS[$kind] ?? throw new InvalidArgumentException("Tipo de item desconhecido: {$kind}.");
    }
}
