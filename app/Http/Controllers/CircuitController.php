<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CircuitUnit;
use App\Models\CircuitIncident;
use App\Models\CircuitOperator;
use App\Models\Unidade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class CircuitController extends Controller
{
    private const OPERADORAS_PADRAO = [
        'Rav - Ligga',
        'Oi',
        'Visao Net',
        'Fibercom',
        'Zaaz',
        'Zazz',
        'iSuper',
        'Ligga',
        'Mega',
        'GGNet',
        'Quality Net',
        'Mafra P4 Net',
        'Cybervia',
    ];

    public function index()
    {
        return view('circuits.index');
    }

    public function units(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'operadora' => ['nullable', 'string', 'max:50'],
            'servico' => ['nullable', 'string', 'max:255'],
            'id_unidades' => ['nullable', 'integer', 'exists:unidades,id_unidades'],
            'per_page' => ['nullable', 'integer', 'in:5,10,15,25,50'],
        ]);

        $query = CircuitUnit::query()->with('unidade');
        if (Schema::hasTable('circuit_incidents')) {
            $query->with('openIncident');
        }
        $search = trim((string) ($filters['q'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 5);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('operadora', 'like', "%{$search}%")
                    ->orWhere('unidades_circuitos', 'like', "%{$search}%")
                    ->orWhere('servico', 'like', "%{$search}%")
                    ->orWhere('endereco', 'like', "%{$search}%")
                    ->orWhere('contato', 'like', "%{$search}%")
                    ->orWhere('informacoes_adicionais', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['operadora'])) {
            $query->where('operadora', $filters['operadora']);
        }

        if (! empty($filters['servico'])) {
            $query->where('servico', $filters['servico']);
        }

        if (! empty($filters['id_unidades'])) {
            $query->where('id_unidades', (int) $filters['id_unidades']);
        }

        $units = $query->orderBy('unidades_circuitos')
            ->paginate($perPage)
            ->withQueryString();

        $unidades = Unidade::orderBy('unidade')->get(['id_unidades', 'unidade']);
        $operadoras = $this->operatorOptions();
        $servicos = CircuitUnit::query()->select('servico')->distinct()->orderBy('servico')->pluck('servico');

        return view('circuits.units', [
            'units' => $units,
            'search' => $search,
            'filters' => $filters,
            'perPage' => $perPage,
            'unidades' => $unidades,
            'operadoras' => $operadoras,
            'servicos' => $servicos,
            'operadorasCadastro' => $operadoras,
        ]);
    }

    public function unitsDashboard(Request $request)
    {
        if (! Schema::hasTable('circuit_incidents')) {
            return view('circuits.units-dashboard', [
                'month' => now()->format('Y-m'),
                'stats' => [
                    'opened' => 0,
                    'resolved' => 0,
                    'pending' => 0,
                    'top_unit' => '-',
                    'top_unit_total' => 0,
                ],
                'byUnit' => collect(),
                'missingTable' => true,
            ]);
        }

        $filters = $request->validate([
            'mes' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = $filters['mes'] ?? now()->format('Y-m');
        [$year, $mon] = explode('-', $month);
        $start = now()->setDate((int) $year, (int) $mon, 1)->startOfDay();
        $end = (clone $start)->endOfMonth();

        $base = CircuitIncident::query()
            ->whereBetween('opened_at', [$start, $end]);

        $openedCount = (clone $base)->count();
        $resolvedCount = (clone $base)->whereNotNull('resolved_at')->count();
        $pendingCount = max(0, $openedCount - $resolvedCount);

        $byUnit = (clone $base)
            ->selectRaw("COALESCE(NULLIF(unidade, ''), 'Sem unidade') as unidade")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN resolved_at IS NOT NULL THEN 1 ELSE 0 END) as resolvidos')
            ->groupBy('unidade')
            ->orderByDesc('total')
            ->get();

        return view('circuits.units-dashboard', [
            'month' => $month,
            'stats' => [
                'opened' => $openedCount,
                'resolved' => $resolvedCount,
                'pending' => $pendingCount,
                'top_unit' => $byUnit->first()?->unidade ?? '-',
                'top_unit_total' => (int) ($byUnit->first()?->total ?? 0),
            ],
            'byUnit' => $byUnit,
            'missingTable' => false,
        ]);
    }

    public function createUnit()
    {
        $unidades = \App\Models\Unidade::orderBy('unidade')->get();
        $operadorasCadastro = $this->operatorOptions();
        return view('circuits.units.create', compact('unidades', 'operadorasCadastro'));
    }

    public function lookupUnitUf(Request $request)
    {
        $data = $request->validate([
            'id_unidades' => ['required', 'integer', 'exists:unidades,id_unidades'],
        ]);

        $unitId = (int) $data['id_unidades'];

        $unit = Unidade::query()
            ->where('id_unidades', $unitId)
            ->first(['endereco']);

        $endereco = trim((string) ($unit?->endereco ?? ''));
        $uf = null;

        return response()->json([
            'found' => (bool) $endereco,
            'uf' => $uf,
            'endereco' => $endereco ?: null,
        ]);
    }

    public function storeUnit(Request $request)
    {
        $data = $request->validate([
            'operadora' => ['required', 'string', 'max:100', Rule::in($this->operatorOptions()->all())],
            'servico' => 'required|string|max:255',
            'endereco' => 'required|string|max:255',
            'contato' => 'required|string|max:50',
            'contato_whatsapp' => ['nullable', 'boolean'],
            'informacoes_adicionais' => ['nullable', 'string', 'max:5000'],
            'id_unidades' => 'required|integer|exists:unidades,id_unidades',
        ]);
        $data['uf'] = '';
        $data['contato_whatsapp'] = $request->boolean('contato_whatsapp');

        $data['unidades_circuitos'] = Unidade::query()
            ->where('id_unidades', $data['id_unidades'])
            ->value('unidade');

        CircuitUnit::create($data);

        return redirect()->route('circuits.units')
            ->with('success', 'Circuito criado com sucesso!');
    }

    public function editUnit(CircuitUnit $unit)
    {
        $unidades = \App\Models\Unidade::orderBy('unidade')->get();
        $operadorasCadastro = $this->operatorOptions();
        return view('circuits.units.edit', compact('unit', 'unidades', 'operadorasCadastro'));
    }

    public function updateUnit(Request $request, CircuitUnit $unit)
    {
        $data = $request->validate([
            'operadora' => ['required', 'string', 'max:100', Rule::in($this->operatorOptions()->all())],
            'servico' => 'required|string|max:255',
            'endereco' => 'required|string|max:255',
            'contato' => 'required|string|max:50',
            'contato_whatsapp' => ['nullable', 'boolean'],
            'informacoes_adicionais' => ['nullable', 'string', 'max:5000'],
            'id_unidades' => 'required|integer|exists:unidades,id_unidades',
        ]);
        $data['uf'] = '';
        $data['contato_whatsapp'] = $request->boolean('contato_whatsapp');

        $data['unidades_circuitos'] = Unidade::query()
            ->where('id_unidades', $data['id_unidades'])
            ->value('unidade');

        $unit->update($data);

        return redirect()->route('circuits.units')
            ->with('success', 'Circuito atualizado com sucesso!');
    }

    public function destroyUnit(CircuitUnit $unit)
    {
        $unit->delete();

        return redirect()->route('circuits.units')
            ->with('success', 'Circuito removido com sucesso!');
    }

    public function storeOperator(Request $request)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:100', Rule::unique('circuit_operadoras', 'nome')],
        ]);

        CircuitOperator::create([
            'nome' => trim($data['nome']),
            'ativo' => true,
        ]);

        return redirect()->route('circuits.units')
            ->with('success', 'Operadora cadastrada com sucesso!');
    }

    public function updateOperational(Request $request, CircuitUnit $unit)
    {
        $data = $request->validate([
            'chamado_numero' => ['nullable', 'string', 'max:50'],
            'massiva_regiao' => ['required', 'in:0,1'],
            'unidade_operacional' => ['required', 'string', 'max:255'],
            'previsao_resolucao_horas' => ['required', 'in:4,6,8,12,24'],
            'marcar_resolvido' => ['nullable', 'in:1'],
        ]);

        DB::transaction(function () use ($data, $unit): void {
            $payload = [
                'chamado_numero' => trim((string) ($data['chamado_numero'] ?? '')) ?: null,
                'massiva_regiao' => (bool) ((int) $data['massiva_regiao']),
                'unidade_operacional' => trim((string) $data['unidade_operacional']),
                'previsao_resolucao_horas' => (int) $data['previsao_resolucao_horas'],
            ];

            $unit->update($payload);

            if (Schema::hasTable('circuit_incidents')) {
                $openIncident = CircuitIncident::query()
                    ->where('circuit_unit_id', $unit->id_circuitos)
                    ->whereNull('resolved_at')
                    ->latest('opened_at')
                    ->first();

                $shouldResolve = ($data['marcar_resolvido'] ?? null) === '1';
                $normalizedChamado = $payload['chamado_numero'];

                if (! $openIncident) {
                    CircuitIncident::create([
                        'circuit_unit_id' => $unit->id_circuitos,
                        'chamado_numero' => $normalizedChamado,
                        'massiva_regiao' => $payload['massiva_regiao'],
                        'unidade' => $payload['unidade_operacional'],
                        'previsao_resolucao_horas' => $payload['previsao_resolucao_horas'],
                        'opened_at' => now(),
                        'resolved_at' => $shouldResolve ? now() : null,
                    ]);
                } else {
                    $openIncident->update([
                        'chamado_numero' => $normalizedChamado,
                        'massiva_regiao' => $payload['massiva_regiao'],
                        'unidade' => $payload['unidade_operacional'],
                        'previsao_resolucao_horas' => $payload['previsao_resolucao_horas'],
                        'resolved_at' => $shouldResolve ? now() : null,
                    ]);
                }
            }
        });

        return back()->with('success', 'Dados operacionais do circuito atualizados.');
    }

    private function operatorOptions()
    {
        if (! Schema::hasTable('circuit_operadoras')) {
            return collect(self::OPERADORAS_PADRAO);
        }

        return CircuitOperator::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->pluck('nome');
    }

    public function ligga()
    {
        return redirect()->away('https://www.liggatelecom.com.br/psw-atendimentoweb/paginas/externo/chamado/acompanhamento.jsf');
    }

    public function embratel()
    {
        return redirect()->away('https://secure.embratel.com.br/AcompSolic/resultadosira.xhtml');
    }

    public function oi()
    {
        return redirect()->away('https://gestaointegrada.oi.net.br/#/!/');
    }

}
