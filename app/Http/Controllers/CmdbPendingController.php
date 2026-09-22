<?php

namespace App\Http\Controllers;

use App\Models\CmdbAsset;
use App\Models\CmdbAssetHistory;
use App\Models\CmdbDiscoveredAsset;
use App\Models\CmdbExpiration;
use App\Models\CmdbService;
use App\Models\CmdbServer;
use App\Models\CmdbSyncLog;
use App\Models\CmdbServiceSuggestionRule;
use App\Services\CmdbDiscoveryQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CmdbPendingController extends Controller
{
    public function services(Request $request, CmdbDiscoveryQueryService $discoveryQuery): View
    {
        $request->merge(['type' => 'service']);
        return $this->index($request, $discoveryQuery);
    }

    public function index(Request $request, CmdbDiscoveryQueryService $discoveryQuery): View
    {
        $items = collect();
        $search = mb_strtolower(trim((string) $request->input('q', '')));
        $type = (string) $request->input('type', '');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:40'],
            'asset_type' => ['nullable', \Illuminate\Validation\Rule::in(['virtual_machine'])],
        ]);
        $assetType = $filters['asset_type'] ?? null;

        $servers = CmdbAsset::query()
            ->with('serverDetails')
            ->whereIn('asset_type', ['server', 'virtual_machine'])
            ->get();

        foreach ($servers as $asset) {
            if ($assetType === 'virtual_machine' && $asset->asset_type !== 'virtual_machine') continue;
            $details = $asset->serverDetails;
            if (! $details || (! filled($details->service_id) && ! filled($details->service_type) && ! filled($details->service_description))) {
                $this->add($items, 'service', 'Alta', 'Serviço não informado', $asset, 'Informe o serviço principal deste ativo.');
            } elseif (! filled($details->service_id) && (filled($details->service_type) || filled($details->service_description))) {
                $this->add($items, 'legacy_service', 'Média', 'Serviço legado para revisar', $asset, 'Este ativo possui informação antiga de serviço. Selecione o serviço canônico para concluir a migração.');
            }
            if (! $asset->responsible_user_id) {
                $this->add($items, 'responsible', 'Média', 'Responsável não informado', $asset, 'Defina uma pessoa ou equipe responsável.');
            }
            if (! $asset->main_ip && ! $asset->ipAddresses()->exists()) {
                $this->add($items, 'ip', 'Média', 'IP não informado', $asset, 'Cadastre o IP principal ou um endereço relacionado.');
            }
            if (! filled($asset->location) && ! $asset->rack_id) {
                $this->add($items, 'location', 'Baixa', 'Localização não informada', $asset, 'Informe a unidade, sala ou rack.');
            }
        }

        $discoveryQuery->pending()->with('connector')->orderByDesc('discovered_at')->get()->each(function (CmdbDiscoveredAsset $item) use ($items, $assetType): void {
            if ($assetType === 'virtual_machine' && $item->source_type !== 'vcenter_virtual_machine') return;
            $items->push([
                'type' => 'discovery',
                'priority' => 'Alta',
                'title' => 'Item encontrado aguardando revisão',
                'subject' => $item->hostname ?: $item->source_external_id ?: 'Descoberta #' . $item->id,
                'description' => 'Origem: ' . ($item->connector?->name ?: 'conector') . '. Tipo: ' . ($item->object_type ?: $item->source_type) . '.',
                'url' => route('cmdb.discovered-assets.show', $item),
            ]);
        });

        CmdbSyncLog::query()->with('connector')->where('status', 'failed')->orderByDesc('started_at')->limit(20)->get()->each(function (CmdbSyncLog $log) use ($items): void {
            $items->push([
                'type' => 'sync',
                'priority' => 'Crítica',
                'title' => 'Falha de sincronização',
                'subject' => $log->connector?->name ?: 'Sincronização',
                'description' => $log->message ?: $log->error_message ?: 'Verifique a conexão e o log técnico.',
                'url' => route('cmdb.sync-logs.show', $log),
            ]);
        });

        CmdbExpiration::query()->with('asset')->whereIn('status', ['pending', 'notified', 'expired'])->whereDate('expiration_date', '<=', now()->addDays(30))->orderBy('expiration_date')->get()->each(function (CmdbExpiration $expiration) use ($items): void {
            $items->push([
                'type' => 'expiration',
                'priority' => $expiration->expiration_date?->isPast() ? 'Crítica' : 'Alta',
                'title' => 'Vencimento próximo',
                'subject' => $expiration->asset?->name ?: $expiration->title,
                'description' => 'Vencimento em ' . optional($expiration->expiration_date)->format('d/m/Y') . '.',
                'url' => route('cmdb.expirations.index'),
            ]);
        });

        $items = $items->filter(function (array $item) use ($search, $type): bool {
            if ($type !== '' && $item['type'] !== $type) return false;
            if ($search === '') return true;
            return str_contains(mb_strtolower(implode(' ', [$item['title'], $item['subject'], $item['description']])), $search);
        })->values();

        return view('cmdb.pending.index', [
            'items' => $items,
            'services' => CmdbService::query()->where('active', true)->orderBy('name')->get(),
            'filters' => $request->only(['q', 'type', 'asset_type']),
            'counts' => [
                'all' => $items->count(),
                'service' => $items->where('type', 'service')->count(),
                'legacy_service' => $items->where('type', 'legacy_service')->count(),
                'discovery' => $items->where('type', 'discovery')->count(),
                'sync' => $items->where('type', 'sync')->count(),
                'expiration' => $items->where('type', 'expiration')->count(),
            ],
        ]);
    }

    public function bulkService(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'asset_ids' => ['required', 'array', 'min:1'],
            'asset_ids.*' => ['integer', 'exists:cmdb_assets,id'],
            'service_id' => ['required', 'integer', 'exists:cmdb_services,id'],
        ]);

        $service = CmdbService::findOrFail($data['service_id']);
        $assets = CmdbAsset::query()->whereIn('id', $data['asset_ids'])->with('serverDetails')->get();

        DB::transaction(function () use ($assets, $service, $request): void {
            foreach ($assets as $asset) {
                if (! in_array($asset->asset_type, ['server', 'virtual_machine'], true)) continue;
                $details = CmdbServer::query()->firstOrCreate(['asset_id' => $asset->id]);
                $before = $details->toArray();
                $details->update(['service_id' => $service->id, 'service_type' => $service->name]);
                app(CmdbAssetHistory::class)->newQuery()->create([
                    'asset_id' => $asset->id,
                    'user_id' => $request->user()?->id,
                    'event_type' => 'bulk_service_updated',
                    'title' => 'Serviço definido em lote',
                    'description' => 'Serviço definido pelo painel de pendências.',
                    'old_values' => $before,
                    'new_values' => $details->fresh()->toArray(),
                ]);
            }
        });

        return back()->with('success', 'Serviço aplicado aos ativos selecionados.');
    }

    private function add(Collection $items, string $type, string $priority, string $title, CmdbAsset $asset, string $description): void
    {
        if ($type === 'service') {
            $name = mb_strtolower((string) ($asset->hostname ?: $asset->name));
            $rule = CmdbServiceSuggestionRule::query()->with('service')->where('active', true)->orderBy('priority')->get()->first(function (CmdbServiceSuggestionRule $rule) use ($name): bool {
                $pattern = mb_strtolower(trim($rule->pattern));
                return match ($rule->match_type) {
                    'starts_with' => str_starts_with($name, $pattern),
                    'ends_with' => str_ends_with($name, $pattern),
                    'exact' => $name === $pattern,
                    'regex' => @preg_match($rule->pattern, $name) === 1,
                    default => str_contains($name, $pattern),
                };
            });
            if ($rule?->service) {
                $description .= ' Sugestão não aplicada: ' . $rule->service->name . ' (regra configurada: ' . $rule->pattern . ').';
            }
        }

        $items->push([
            'type' => $type,
            'priority' => $priority,
            'title' => $title,
            'subject' => $asset->hostname ?: $asset->name,
            'description' => $description,
            'url' => route('cmdb.assets.edit', $asset),
            'asset_id' => $asset->id,
        ]);
    }
}
