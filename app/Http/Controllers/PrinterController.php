<?php

namespace App\Http\Controllers;

use App\Models\OrganizationUnit;
use App\Models\Printer;
use App\Models\PrinterSupply;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Parque de impressoras vindo da EKM Insight API.
 *
 * Só leitura: o cadastro é do portal da Simpress, não nosso. Editar aqui
 * criaria divergência que a próxima sincronização desfaz sem avisar.
 */
class PrinterController extends Controller
{
    private const POR_PAGINA = 25;

    /** Situações que a lista sabe filtrar. */
    private const SITUACOES = [
        'suprimento_critico' => 'Consumível crítico (abaixo de '.Printer::NIVEL_CRITICO.'%)',
        'suprimento_atencao' => 'Consumível em atenção (abaixo de '.Printer::NIVEL_ALERTA.'%)',
        'sem_contato' => 'Sem contato recente',
        'sem_unidade' => 'Sem unidade definida',
    ];

    /*
     | As faixas e os rótulos de tipo NÃO são definidos aqui.
     |
     | Printer::NIVEL_CRITICO / NIVEL_ALERTA e PrinterSupply::TIPOS já
     | existiam e são usados na ficha e no acessor de situação. Repetir os
     | números no controller criava duas verdades sobre o que é "crítico".
     */

    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'unidade' => ['nullable', 'integer'],
            'modelo' => ['nullable', 'string', 'max:120'],
            'situacao' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::SITUACOES))],
            'consumivel' => ['nullable', 'string', 'in:'.implode(',', array_keys(PrinterSupply::TIPOS))],
        ]);

        $busca = trim((string) ($filtros['q'] ?? ''));

        $consulta = Printer::query()->with('unit');

        if ($busca !== '') {
            $consulta->where(function ($q) use ($busca): void {
                foreach (['serial_number', 'model', 'hostname', 'ip_address', 'asset_number', 'insight_location'] as $campo) {
                    $q->orWhere($campo, 'like', "%{$busca}%");
                }
            });
        }

        if (($unidade = (int) ($filtros['unidade'] ?? 0)) > 0) {
            $consulta->where('organization_unit_id', $unidade);
        }

        if (($modelo = trim((string) ($filtros['modelo'] ?? ''))) !== '') {
            $consulta->where('model', $modelo);
        }

        $consumivel = (string) ($filtros['consumivel'] ?? '');

        $this->aplicarSituacao($consulta, (string) ($filtros['situacao'] ?? ''), $consumivel);

        /*
         | Escolher um tipo sem escolher faixa limita às impressoras que têm
         | aquele consumível. Sem isso, filtrar por "Fusor" deixaria na lista
         | equipamento que nem tem fusor monitorado, com a coluna vazia.
         */
        if ($consumivel !== '' && ($filtros['situacao'] ?? '') === '') {
            $consulta->whereHas('supplies', fn ($q) => $q->where('tipo', $consumivel));
        }

        /** @var LengthAwarePaginator<Printer> $impressoras */
        $impressoras = $consulta->orderBy('model')->orderBy('serial_number')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        return view('impressoras.index', [
            'impressoras' => $impressoras,
            'piorSuprimento' => $this->piorSuprimentoPorImpressora($impressoras->getCollection(), $consumivel),
            'unidades' => $this->unidadesComImpressora(),
            'modelos' => Printer::query()->whereNotNull('model')
                ->distinct()->orderBy('model')->pluck('model'),
            'situacoes' => self::SITUACOES,
            'consumiveis' => $this->consumiveisPresentes(),
            'consumivelSelecionado' => $consumivel,
            'filtros' => $filtros,
            'busca' => $busca,
            'resumo' => $this->resumo(),
        ]);
    }

    public function show(Printer $printer): View
    {
        $printer->load('unit');

        return view('impressoras.show', [
            'impressora' => $printer,
            // Ordem de leitura: o que está acabando primeiro.
            'suprimentos' => $printer->supplies()
                ->orderByRaw('percent_left IS NULL, percent_left asc')
                ->get(),
            'contador' => $printer->meters()->orderByDesc('billing_date')->first(),
            'contadores' => $printer->meters()->orderByDesc('billing_date')->limit(12)->get(),
            /*
             | O status do portal não serve como fila: tudo fica em NEW, e o
             | que diz se o pedido foi atendido é a data de substituição.
             */
            'pedidos' => $printer->consumableRequests()
                ->orderByDesc('solicitado_em')->limit(20)->get(),
            'semContatoRecente' => $printer->semContatoRecente(),
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Printer>  $consulta
     * @param  string  $consumivel  Restringe a faixa a um tipo (fusor, cilindro…).
     */
    private function aplicarSituacao($consulta, string $situacao, string $consumivel = ''): void
    {
        $abaixoDe = fn (int $limite) => $consulta->whereHas('supplies', function ($q) use ($limite, $consumivel): void {
            $q->where('percent_left', '<', $limite);

            if ($consumivel !== '') {
                $q->where('tipo', $consumivel);
            }
        });

        match ($situacao) {
            'sem_unidade' => $consulta->semUnidade(),
            'sem_contato' => $consulta->where(function ($q): void {
                $q->whereNull('last_contact')
                    ->orWhere('last_contact', '<=', now()->subDays(Printer::DIAS_SEM_CONTATO_ALERTA));
            }),
            'suprimento_critico' => $abaixoDe(Printer::NIVEL_CRITICO),
            'suprimento_atencao' => $abaixoDe(Printer::NIVEL_ALERTA),
            default => null,
        };
    }

    /**
     * Menor nível de suprimento de cada impressora da página.
     *
     * A lista mostra uma coluna de toner, e sem isto seria uma consulta por
     * linha. Só as impressoras da página, não o parque inteiro.
     *
     * Com um tipo escolhido, mostra o pior DAQUELE tipo: filtrar por fusor e
     * ver o nível do toner na coluna seria responder outra pergunta.
     *
     * @param  Collection<int, Printer>  $impressoras
     * @return Collection<int, PrinterSupply>
     */
    private function piorSuprimentoPorImpressora(Collection $impressoras, string $consumivel = ''): Collection
    {
        if ($impressoras->isEmpty()) {
            return collect();
        }

        /*
         | unique() antes de keyBy(), e não keyBy() direto.
         |
         | keyBy() mantém a ÚLTIMA ocorrência de cada chave: numa coleção
         | ordenada do menor para o maior, a impressora ficava com o
         | suprimento mais ALTO — o contrário do que a coluna precisa mostrar.
         | unique() guarda a primeira, que é a mais baixa.
         */
        return PrinterSupply::query()
            ->whereIn('printer_id', $impressoras->pluck('id'))
            ->whereNotNull('percent_left')
            ->when($consumivel !== '', fn ($q) => $q->where('tipo', $consumivel))
            ->orderBy('percent_left')
            ->get()
            ->unique('printer_id')
            ->keyBy('printer_id');
    }

    /**
     * Tipos que existem no parque, com o rótulo em português.
     *
     * @return array<string, string>
     */
    private function consumiveisPresentes(): array
    {
        $tipos = PrinterSupply::query()->whereNotNull('tipo')
            ->distinct()->pluck('tipo')->all();

        return array_filter(
            PrinterSupply::TIPOS,
            fn (string $tipo): bool => in_array($tipo, $tipos, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    /** Só as unidades que têm impressora — o resto vira ruído no filtro. */
    private function unidadesComImpressora(): Collection
    {
        return OrganizationUnit::query()
            ->whereIn('id', Printer::query()->whereNotNull('organization_unit_id')
                ->distinct()->pluck('organization_unit_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** @return array<string, int> */
    private function resumo(): array
    {
        return [
            'total' => Printer::query()->count(),
            'sem_unidade' => Printer::query()->semUnidade()->count(),
            'sem_contato' => Printer::query()->where(function ($q): void {
                $q->whereNull('last_contact')
                    ->orWhere('last_contact', '<=', now()->subDays(Printer::DIAS_SEM_CONTATO_ALERTA));
            })->count(),
            'suprimento_critico' => PrinterSupply::query()
                ->where('percent_left', '<', Printer::NIVEL_CRITICO)->count(),
        ];
    }
}
