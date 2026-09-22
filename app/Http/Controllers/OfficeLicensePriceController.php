<?php

namespace App\Http\Controllers;

use App\Models\OfficeLicensePrice;
use App\Support\OfficeLicenseSkuCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OfficeLicensePriceController extends Controller
{
    public function index(): View
    {
        $latestPrices = OfficeLicensePrice::query()
            ->active()
            ->whereNull('valid_until')
            ->orderByDesc('valid_from')
            ->get()
            ->unique('sku_id')
            ->keyBy('sku_id');

        return view('service-desk.licensing.office-prices', [
            'prices' => OfficeLicensePrice::query()
                ->orderBy('sku_id')
                ->orderByDesc('valid_from')
                ->get(),
            'skuCatalog' => $this->availableSkuCatalog(),
            'latestPrices' => $latestPrices,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->has('prices')) {
            $data = $request->validate([
                'prices' => ['required', 'array'],
                'prices.*' => ['nullable', 'string', 'max:30'],
                'currency' => ['nullable', 'string', 'max:10'],
                'valid_from' => ['nullable', 'date'],
                'competence' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
                'back_to_rateio' => ['nullable', 'boolean'],
            ]);

            $currency = mb_strtoupper((string) ($data['currency'] ?? 'BRL'));
            $validFrom = $data['valid_from'] ?? now()->startOfMonth()->toDateString();
            $saved = 0;

            foreach ($this->availableSkuCatalog() as $sku) {
                $skuId = (string) $sku['sku_id'];
                $rawPrice = trim((string) data_get($data, "prices.{$skuId}", ''));

                if ($rawPrice === '') {
                    continue;
                }

                $price = $this->parseMoney($rawPrice);
                $current = OfficeLicensePrice::query()
                    ->where('sku_id', $skuId)
                    ->where('active', true)
                    ->whereNull('valid_until')
                    ->orderByDesc('valid_from')
                    ->first();

                if ($current) {
                    $current->update([
                        'sku_name' => $sku['name'],
                        'monthly_price' => $price,
                        'currency' => $currency,
                        'valid_from' => $validFrom,
                        'updated_by' => Auth::id(),
                    ]);
                } else {
                    OfficeLicensePrice::create([
                        'sku_id' => $skuId,
                        'sku_name' => $sku['name'],
                        'monthly_price' => $price,
                        'currency' => $currency,
                        'valid_from' => $validFrom,
                        'valid_until' => null,
                        'active' => true,
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]);
                }

                $saved++;
            }

            if ($request->boolean('back_to_rateio')) {
                return redirect()
                    ->route('administrativo.licensing.office', array_filter([
                        'competence' => $data['competence'] ?? null,
                    ]))
                    ->with('success', "Preços atualizados: {$saved}. Gere ou atualize o rateio para recalcular os valores.");
            }

            return redirect()->route('administrativo.licensing.office-prices.index')
                ->with('success', "Preços atualizados: {$saved}.");
        }

        $data = $request->validate([
            'sku_id' => ['required', 'string', 'max:100'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'active' => ['nullable', 'boolean'],
        ]);

        $sku = $this->availableSkuCatalog()->firstWhere('sku_id', (string) $data['sku_id']);
        if (! $sku) {
            return back()->withInput()->with('error', 'Produto Office não reconhecido.');
        }

        OfficeLicensePrice::create([
            'sku_id' => $sku['sku_id'],
            'sku_name' => $sku['name'],
            'monthly_price' => $data['monthly_price'],
            'currency' => mb_strtoupper((string) $data['currency']),
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'] ?? null,
            'active' => (bool) ($data['active'] ?? true),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('administrativo.licensing.office-prices.index')
            ->with('success', 'Preço cadastrado.');
    }

    private function availableSkuCatalog()
    {
        return OfficeLicenseSkuCatalog::billable();
    }

    public function update(Request $request, OfficeLicensePrice $price): RedirectResponse
    {
        $data = $request->validate([
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'active' => ['nullable', 'boolean'],
        ]);

        $price->update([
            'monthly_price' => $data['monthly_price'],
            'currency' => mb_strtoupper((string) $data['currency']),
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'] ?? null,
            'active' => (bool) ($data['active'] ?? false),
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('administrativo.licensing.office-prices.index')
            ->with('success', 'Preço atualizado.');
    }

    public function endValidity(OfficeLicensePrice $price): RedirectResponse
    {
        $price->update([
            'active' => false,
            'valid_until' => now()->toDateString(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('administrativo.licensing.office-prices.index')
            ->with('success', 'Vigência encerrada.');
    }

    private function parseMoney(string $value): float
    {
        $value = trim($value);
        if ($value === '') {
            return 0.0;
        }

        $normalized = str_replace(['R$', ' '], '', $value);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        return max(0.0, (float) $normalized);
    }
}
