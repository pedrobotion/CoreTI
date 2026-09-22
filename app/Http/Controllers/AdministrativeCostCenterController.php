<?php

namespace App\Http\Controllers;

use App\Models\CoretiCostCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdministrativeCostCenterController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $centers = CoretiCostCenter::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->orderBy('code')
            ->paginate(30)
            ->withQueryString();

        $catalogCodes = CoretiCostCenter::query()->pluck('code')->map(fn ($code) => trim((string) $code))->flip();
        $missingCostCenters = DB::table('ad_users')
            ->selectRaw('TRIM(centro_custo) as code, COUNT(*) as users_total')
            ->whereNotNull('centro_custo')
            ->whereRaw("TRIM(centro_custo) <> ''")
            ->when($search !== '', fn ($query) => $query->whereRaw('TRIM(centro_custo) like ?', ["%{$search}%"]))
            ->groupByRaw('TRIM(centro_custo)')
            ->orderBy('code')
            ->get()
            ->filter(fn ($row) => ! $catalogCodes->has((string) $row->code))
            ->values();

        return view('panels.administrativo.centros-custo', compact('centers', 'search', 'missingCostCenters'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()?->id;
        CoretiCostCenter::query()->create($data);

        return back()->with('success', 'Centro de custo cadastrado.');
    }

    public function update(Request $request, CoretiCostCenter $center): RedirectResponse
    {
        $data = $this->validated($request, $center);
        $data['updated_by'] = $request->user()?->id;
        $center->update($data);

        return back()->with('success', 'Centro de custo atualizado.');
    }

    public function toggle(Request $request, CoretiCostCenter $center): RedirectResponse
    {
        $center->update(['active' => ! $center->active, 'updated_by' => $request->user()?->id]);

        return back()->with('success', $center->active ? 'Centro de custo ativado.' : 'Centro de custo desativado.');
    }

    public function destroy(CoretiCostCenter $center): RedirectResponse
    {
        $center->delete();

        return back()->with('success', 'Centro de custo removido do catálogo.');
    }

    private function validated(Request $request, ?CoretiCostCenter $center = null): array
    {
        /*
         | O código é a chave que liga este catálogo ao resto do sistema:
         | e-mails, licenças e o AD procuram o centro de custo por "unicoop.área".
         |
         | Fora desse formato o registro não dá erro em lugar nenhum — ele
         | simplesmente some das seleções, porque quem consome filtra o que não
         | consegue separar em unicoop e área. Já aconteceu de código e nome
         | entrarem trocados e o cadastro ficar invisível. Melhor recusar aqui.
         */
        $request->merge(['code' => $this->normalizeCode($request->input('code'))]);

        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^\d{2}\.\d{3}$/',
                Rule::unique('coreti_cost_centers', 'code')->ignore($center?->id)->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ], [
            'code.regex' => 'O código deve ser a unicoop e a área, no formato 01.226. Confira se o código e o nome não entraram trocados.',
        ], ['code' => 'código', 'name' => 'nome']);
    }

    /** Aceita "1.226", "01/226" e "01 . 226" como o mesmo "01.226". */
    private function normalizeCode(mixed $code): mixed
    {
        if (! is_string($code) || ! preg_match('/^\s*(\d{1,2})\s*[.\/-]\s*(\d{1,3})\s*$/', $code, $m)) {
            return $code;
        }

        return str_pad($m[1], 2, '0', STR_PAD_LEFT) . '.' . str_pad($m[2], 3, '0', STR_PAD_LEFT);
    }
}
