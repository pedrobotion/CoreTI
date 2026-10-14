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

        /*
         | Soft delete não libera o índice único do banco: a linha removida
         | continua ocupando o `code`. A validação ignora excluídos, então
         | ela passa, mas o INSERT bate no índice e estoura. Quando já existe
         | um centro removido com este código, reaproveitamos a mesma linha:
         | restauramos e regravamos o nome. Assim o recadastro funciona e as
         | ligações por código (e-mails, licenças, AD) seguem intactas.
         */
        $removido = CoretiCostCenter::onlyTrashed()
            ->where('code', $data['code'])
            ->where('name', $data['name'])
            ->first();

        if ($removido !== null) {
            $removido->restore();
            $removido->update([
                'name' => $data['name'],
                'active' => $data['active'] ?? true,
                'updated_by' => $request->user()?->id,
            ]);

            return back()->with('success', 'Centro de custo cadastrado.');
        }

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
         | e-mails, licenças e o AD procuram o centro de custo por ele.
         |
         | Um código sem forma reconhecível não dá erro em lugar nenhum — ele
         | simplesmente some das seleções, porque quem consome descarta o que
         | não consegue interpretar. Já aconteceu de código e nome entrarem
         | trocados e o cadastro ficar invisível. Melhor recusar aqui.
         |
         | São DOIS formatos válidos, não um:
         |
         |   01.226              unicoop.área, o caso comum
         |   07.881.528/0001-66  CNPJ, usado pelas empresas afiliadas
         |
         | A Rodocoop é afiliada: o "Escritório" no AD guarda o CNPJ dela em
         | vez de um centro de custo nosso. Exigir só unicoop.área impedia
         | cadastrar o nome dela no catálogo.
         */
        $code = $this->normalizeCode($request->input('code'));
        $request->merge(['code' => $code]);

        /*
         | Unicidade por (código + nome), não só por código: um mesmo centro de
         | custo pode ter várias unidades (ex.: as lojas Colari, todas 01.316).
         | O que não pode é repetir o mesmo nome sob o mesmo código.
         */
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:'.self::FORMATOS_DE_CODIGO,
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('coreti_cost_centers', 'name')
                    ->where(fn ($query) => $query->where('code', $code))
                    ->ignore($center?->id)
                    ->whereNull('deleted_at'),
            ],
            'active' => ['nullable', 'boolean'],
        ], [
            'code.regex' => 'O código deve ser a unicoop e a área (formato 01.226) ou um CNPJ (formato 07.881.528/0001-66). Confira se o código e o nome não entraram trocados.',
            'name.unique' => 'Já existe uma unidade com esse nome nesse centro de custo.',
        ], ['code' => 'código', 'name' => 'nome']);
    }

    /** unicoop.área ou CNPJ — ver a nota em {@see validated()}. */
    private const FORMATOS_DE_CODIGO = '/^(\d{2}\.\d{3}|\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2})$/';

    /**
     * Aceita "1.226", "01/226" e "01 . 226" como o mesmo "01.226".
     *
     * CNPJ passa direto: ele já tem pontuação própria e normalizá-lo como
     * unicoop.área o estragaria.
     */
    private function normalizeCode(mixed $code): mixed
    {
        if (! is_string($code)) {
            return $code;
        }

        $limpo = trim($code);

        if (preg_match('/^\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}$/', $limpo)) {
            return $limpo;
        }

        if (! preg_match('/^\s*(\d{1,2})\s*[.\/-]\s*(\d{1,3})\s*$/', $code, $m)) {
            return $code;
        }

        return str_pad($m[1], 2, '0', STR_PAD_LEFT) . '.' . str_pad($m[2], 3, '0', STR_PAD_LEFT);
    }
}
