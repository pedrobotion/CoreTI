<?php

namespace App\Services;

use App\Models\BancadaEquipment;
use Illuminate\Support\Str;

class BancadaStatusFlowService
{
    public const STATUS_AGUARDANDO_ENTRADA_FISCAL = 'Aguardando Entrada Fiscal';
    public const STATUS_EM_BANCADA = 'Em bancada';
    public const STATUS_TERCEIROS = 'Terceiros';
    public const STATUS_AGUARDANDO_PECA = 'Aguardando peça';
    public const STATUS_EM_MANUTENCAO = 'Em manutenção';
    public const STATUS_MANUTENCAO_REALIZADA = 'Manutenção realizada';
    public const STATUS_SEM_CONSERTO = 'Sem conserto';
    public const STATUS_PRONTO_ENTREGA = 'Pronto para entrega';
    public const STATUS_NOTA_FISCAL_EMITIDA = 'Nota Fiscal Emitida';
    public const STATUS_ENTREGUE = 'Entregue';
    public const STATUS_BACKUP = 'Backup';
    public const STATUS_DESCARTE = 'Descarte';

    public const ENTRY_PENDING = 'Aguardando Entrada Fiscal';
    public const ENTRY_DONE = 'Entrada Realizada';

    private const STATUS_ALIASES = [
        'Aguardando Entrada' => self::STATUS_AGUARDANDO_ENTRADA_FISCAL,
        'Aguardando Entrada Fiscal' => self::STATUS_AGUARDANDO_ENTRADA_FISCAL,
        'Em Bancada' => self::STATUS_EM_BANCADA,
        'Em bancada' => self::STATUS_EM_BANCADA,
    ];

    private const ENTRY_ALIASES = [
        'Aguardando Entrada' => self::ENTRY_PENDING,
        'Aguardando Entrada Fiscal' => self::ENTRY_PENDING,
        'Entrada Realizada' => self::ENTRY_DONE,
    ];

    /**
     * Unidades que ficam alocadas na Sede e NÃO precisam de nota de saída/entrada
     * (comparadas pelo nome já normalizado — ver normalizeFiscalUnitName). Além
     * destas, qualquer unidade com centro de custo de Sede (01.%) também é isenta
     * (ver isSedeCostCenter), o que cobre TRR, AA COCARI, Transcocari etc.
     */
    private const FISCAL_EXEMPT_UNITS = [
        'e commerce',
        'centro de distribuicao de insumos',
        'transcocari',
        'mandaguari i',
        'ubc sede',
        'unidades ubc unidade de beneficiamento de cafe',
        'unidades ubp unidade de beneficiamento de pescado',
        'departamento e assistencia tecnica',
    ];

    public function normalizeStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $trimmed = trim($status);
        if ($trimmed === '') {
            return null;
        }

        return self::STATUS_ALIASES[$trimmed] ?? $trimmed;
    }

    public function normalizeEntryStatus(?string $status): string
    {
        $trimmed = trim((string) $status);
        if ($trimmed === '') {
            return self::ENTRY_PENDING;
        }

        return self::ENTRY_ALIASES[$trimmed] ?? $trimmed;
    }

    public function initialStatusForOrigin(string $origin): string
    {
        return $this->initialStatusFor($origin);
    }

    public function initialStatusFor(string $origin, ?string $unitName = null): string
    {
        return $origin === 'sede' || $this->isFiscalExemptUnit($unitName)
            ? self::STATUS_EM_BANCADA
            : self::STATUS_AGUARDANDO_ENTRADA_FISCAL;
    }

    public function initialEntryStatusForOrigin(string $origin): string
    {
        return $this->initialEntryStatusFor($origin);
    }

    public function initialEntryStatusFor(string $origin, ?string $unitName = null): string
    {
        return $origin === 'sede' || $this->isFiscalExemptUnit($unitName) ? self::ENTRY_DONE : self::ENTRY_PENDING;
    }

    public function requiresFiscalFlow(BancadaEquipment $equipment): bool
    {
        if (($equipment->origem_tipo ?? 'unidade') !== 'unidade') {
            return false;
        }

        // Centro de custo de Sede (01.%) = unidade alocada na Sede: sem nota/rota.
        if ($this->isSedeCostCenter($equipment->cost_center_code)) {
            return false;
        }

        return ! $this->isFiscalExemptUnit($equipment->unidade_setor);
    }

    public function isFiscalExemptUnit(?string $unitName): bool
    {
        $normalized = $this->normalizeFiscalUnitName($unitName);
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, self::FISCAL_EXEMPT_UNITS, true);
    }

    /** Centro de custo de Sede: código no formato 01.xxx. */
    public function isSedeCostCenter(?string $code): bool
    {
        return $code !== null && str_starts_with(trim($code), '01.');
    }

    public function isOperationalLocked(BancadaEquipment $equipment): bool
    {
        if (! $this->requiresFiscalFlow($equipment)) {
            return false;
        }

        return $this->normalizeEntryStatus($equipment->entrada_status) === self::ENTRY_PENDING;
    }

    public function assertTransition(BancadaEquipment $equipment, string $toStatus): void
    {
        $from = $this->normalizeStatus($equipment->status) ?? self::STATUS_EM_BANCADA;
        $to = $this->normalizeStatus($toStatus) ?? '';

        if ($to === '' || $from === $to) {
            return;
        }

        if ($this->isOperationalLocked($equipment)) {
            throw new \DomainException('Este equipamento está aguardando entrada fiscal pelo Administrativo.');
        }

        if (in_array($from, [self::STATUS_ENTREGUE, self::STATUS_DESCARTE], true)) {
            throw new \DomainException("Equipamento em {$from} não pode voltar ao fluxo operacional.");
        }

        $allowed = match ($from) {
            self::STATUS_EM_BANCADA => [
                self::STATUS_EM_MANUTENCAO,
            ],
            self::STATUS_EM_MANUTENCAO => [
                self::STATUS_TERCEIROS,
                self::STATUS_AGUARDANDO_PECA,
                self::STATUS_BACKUP,
                self::STATUS_MANUTENCAO_REALIZADA,
                self::STATUS_SEM_CONSERTO,
            ],
            self::STATUS_MANUTENCAO_REALIZADA => [
                self::STATUS_PRONTO_ENTREGA,
                self::STATUS_BACKUP,
            ],
            self::STATUS_SEM_CONSERTO => [
                self::STATUS_DESCARTE,
            ],
            self::STATUS_PRONTO_ENTREGA => ! $this->requiresFiscalFlow($equipment)
                ? [self::STATUS_ENTREGUE]
                : [self::STATUS_NOTA_FISCAL_EMITIDA],
            self::STATUS_NOTA_FISCAL_EMITIDA => [self::STATUS_ENTREGUE],
            self::STATUS_BACKUP => [],
            self::STATUS_TERCEIROS => [self::STATUS_MANUTENCAO_REALIZADA, self::STATUS_SEM_CONSERTO],
            self::STATUS_AGUARDANDO_PECA => [self::STATUS_MANUTENCAO_REALIZADA],
            self::STATUS_AGUARDANDO_ENTRADA_FISCAL => [self::STATUS_EM_BANCADA],
            default => [],
        };

        if ($from === self::STATUS_TERCEIROS) {
            if (! $this->thirdPartyPhysicalReturnRegistered($equipment)) {
                throw new \DomainException('Este equipamento está aguardando retorno físico do terceiro.');
            }

            if ($to === self::STATUS_MANUTENCAO_REALIZADA && ! $this->thirdPartyResultIsApproved($equipment)) {
                throw new \DomainException('Este equipamento está aguardando confirmação de reparo aprovado.');
            }

            if ($to === self::STATUS_SEM_CONSERTO && ! $this->thirdPartyResultIsRejected($equipment)) {
                throw new \DomainException('Este equipamento está aguardando confirmação de reparo reprovado.');
            }
        }

        if ($from === self::STATUS_AGUARDANDO_PECA && $to === self::STATUS_MANUTENCAO_REALIZADA) {
            $origin = (string) ($equipment->peca_origem ?? '');
            $flow = (string) ($equipment->peca_fluxo_status ?? '');
            if ($origin !== 'estoque_ti' && $flow !== 'recebida_confirmada') {
                throw new \DomainException('Antes de concluir, confirme o recebimento da peça.');
            }
        }

        if ($from === self::STATUS_PRONTO_ENTREGA && $this->requiresFiscalFlow($equipment) && $to === self::STATUS_ENTREGUE) {
            throw new \DomainException('Equipamentos de unidade precisam de nota de saída antes da entrega.');
        }

        if (! in_array($to, $allowed, true)) {
            throw new \DomainException("Transição inválida: {$from} -> {$to}.");
        }
    }

    public function availableTransitions(BancadaEquipment $equipment): array
    {
        $from = $this->normalizeStatus($equipment->status) ?? self::STATUS_EM_BANCADA;

        if ($this->isOperationalLocked($equipment)) {
            return [];
        }

        if (in_array($from, [self::STATUS_ENTREGUE, self::STATUS_DESCARTE, self::STATUS_BACKUP], true)) {
            return [];
        }

        if ($from === self::STATUS_AGUARDANDO_ENTRADA_FISCAL) {
            return [];
        }

        if ($from === self::STATUS_TERCEIROS) {
            if (! $this->thirdPartyPhysicalReturnRegistered($equipment)) {
                return [];
            }

            if ($this->thirdPartyResultIsApproved($equipment)) {
                return [self::STATUS_MANUTENCAO_REALIZADA];
            }

            if ($this->thirdPartyResultIsRejected($equipment)) {
                return [self::STATUS_SEM_CONSERTO];
            }

            return [];
        }

        if ($from === self::STATUS_AGUARDANDO_PECA) {
            $origin = (string) ($equipment->peca_origem ?? '');
            $flow = (string) ($equipment->peca_fluxo_status ?? '');

            if ($origin === 'estoque_ti' || $flow === 'recebida_confirmada') {
                return [self::STATUS_MANUTENCAO_REALIZADA];
            }

            return [];
        }

        if ($from === self::STATUS_PRONTO_ENTREGA && $this->requiresFiscalFlow($equipment)) {
            return [];
        }

        return match ($from) {
            self::STATUS_EM_BANCADA => [
                self::STATUS_EM_MANUTENCAO,
            ],
            self::STATUS_EM_MANUTENCAO => [
                self::STATUS_TERCEIROS,
                self::STATUS_AGUARDANDO_PECA,
                self::STATUS_BACKUP,
                self::STATUS_MANUTENCAO_REALIZADA,
                self::STATUS_SEM_CONSERTO,
            ],
            self::STATUS_MANUTENCAO_REALIZADA => [
                self::STATUS_PRONTO_ENTREGA,
                self::STATUS_BACKUP,
            ],
            self::STATUS_SEM_CONSERTO => [
                self::STATUS_DESCARTE,
            ],
            self::STATUS_PRONTO_ENTREGA => [self::STATUS_ENTREGUE],
            self::STATUS_NOTA_FISCAL_EMITIDA => [self::STATUS_ENTREGUE],
            default => [],
        };
    }

    private function thirdPartyPhysicalReturnRegistered(BancadaEquipment $equipment): bool
    {
        return filled($equipment->terceiros_retorno_fisico_em);
    }

    private function thirdPartyResultIsApproved(BancadaEquipment $equipment): bool
    {
        return in_array((string) ($equipment->terceiros_resultado ?? ''), ['aprovada', 'aprovado'], true)
            || (string) ($equipment->terceiros_orcamento_status ?? '') === 'aprovado';
    }

    private function thirdPartyResultIsRejected(BancadaEquipment $equipment): bool
    {
        return in_array((string) ($equipment->terceiros_resultado ?? ''), ['sem_conserto', 'negada', 'reprovada', 'reprovado'], true)
            || (string) ($equipment->terceiros_orcamento_status ?? '') === 'reprovado';
    }

    private function normalizeFiscalUnitName(?string $unitName): string
    {
        $value = Str::ascii(mb_strtolower(trim((string) $unitName)));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';

        return trim(preg_replace('/\s+/', ' ', $value) ?: '');
    }
}
