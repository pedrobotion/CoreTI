<?php

namespace App\Http\Controllers;

use App\Models\CmdbService;
use App\Models\CmdbServiceSuggestionRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmdbServiceSuggestionRuleController extends Controller
{
    public function index(): View
    {
        return view('cmdb/service-rules/index', [
            'rules' => CmdbServiceSuggestionRule::query()->with('service')->orderBy('priority')->orderBy('id')->get(),
            'services' => CmdbService::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pattern' => ['required', 'string', 'max:120'],
            'match_type' => ['required', 'in:contains,starts_with,ends_with,exact,regex'],
            'service_id' => ['required', 'exists:cmdb_services,id'],
            'priority' => ['required', 'integer', 'min:0', 'max:9999'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $data['active'] = true;
        $data['created_by'] = $request->user()?->id;
        CmdbServiceSuggestionRule::create($data);
        return back()->with('success', 'Regra de sugestão criada.');
    }

    public function update(Request $request, CmdbServiceSuggestionRule $rule): RedirectResponse
    {
        $data = $request->validate([
            'pattern' => ['required', 'string', 'max:120'],
            'match_type' => ['required', 'in:contains,starts_with,ends_with,exact,regex'],
            'service_id' => ['required', 'exists:cmdb_services,id'],
            'priority' => ['required', 'integer', 'min:0', 'max:9999'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $data['updated_by'] = $request->user()?->id;
        $rule->update($data);
        return back()->with('success', 'Regra de sugestão atualizada.');
    }

    public function toggle(CmdbServiceSuggestionRule $rule): RedirectResponse
    {
        $rule->update(['active' => ! $rule->active]);
        return back()->with('success', 'Status da regra atualizado.');
    }
}
