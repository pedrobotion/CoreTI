<?php

namespace App\Http\Controllers;

use App\Models\TiCard;
use App\Models\TiCardPurchase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TiCreditCardController extends Controller
{
    private const BILLING_TYPES = [
        'mensalidade' => 'Mensalidade',
        'anualidade' => 'Anualidade',
        'avulsa' => 'Compra avulsa',
    ];

    private const CURRENCIES = [
        'BRL' => 'Real',
        'USD' => 'Dólar',
        'EUR' => 'Euro',
    ];

    public function index(Request $request): View
    {
        if (! Schema::hasTable('ti_cards') || ! Schema::hasTable('ti_card_purchases')) {
            return view('panels.administrativo.cartao-ti', [
                'setupPending' => true,
                'cards' => collect(),
                'purchases' => new LengthAwarePaginator([], 0, 10, 1),
                'activeCards' => collect(),
                'search' => '',
                'cardFilter' => '',
                'stats' => [
                    'cards_total' => 0,
                    'cards_active' => 0,
                    'purchases_total' => 0,
                    'total_spent' => 0,
                    'with_invoice' => 0,
                ],
                'billingTypes' => self::BILLING_TYPES,
                'currencies' => self::CURRENCIES,
            ]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'card_id' => ['nullable', 'integer', 'exists:ti_cards,id'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50'],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $cardFilter = (int) ($filters['card_id'] ?? 0);
        $perPage = (int) ($filters['per_page'] ?? 10);

        $cardsQuery = TiCard::query()
            ->withCount('purchases')
            ->withSum('purchases as purchases_total_value', 'purchase_value');

        $cards = $cardsQuery
            ->orderBy('name')
            ->get();

        $purchasesQuery = TiCardPurchase::query()
            ->with(['card', 'invoiceUploader'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($sq) use ($search): void {
                    $sq->where('purchase_name', 'like', "%{$search}%")
                        ->orWhere('observacao', 'like', "%{$search}%")
                        ->orWhereHas('card', function ($cardQuery) use ($search): void {
                            $cardQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('last4_digits', 'like', "%{$search}%");
                        });
                });
            })
            ->when($cardFilter > 0, fn ($query) => $query->where('ti_card_id', $cardFilter));

        $purchases = $purchasesQuery
            ->orderByDesc('purchase_date')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $stats = [
            'cards_total' => $cards->count(),
            'cards_active' => $cards->where('active', true)->count(),
            'purchases_total' => TiCardPurchase::query()->count(),
            'total_spent' => (float) TiCardPurchase::query()->sum('purchase_value'),
            'with_invoice' => TiCardPurchase::query()->whereNotNull('invoice_storage_path')->where('invoice_storage_path', '<>', '')->count(),
        ];

        return view('panels.administrativo.cartao-ti', [
            'setupPending' => false,
            'cards' => $cards,
            'activeCards' => $cards->where('active', true)->values(),
            'purchases' => $purchases,
            'search' => $search,
            'cardFilter' => $cardFilter,
            'stats' => $stats,
            'billingTypes' => self::BILLING_TYPES,
            'currencies' => self::CURRENCIES,
        ]);
    }

    public function createCard(): View
    {
        return view('panels.administrativo.cartao-ti-card-form', [
            'card' => null,
            'action' => route('administrativo.cartao-ti.cards.store'),
            'method' => 'POST',
            'title' => 'Novo cartão',
        ]);
    }

    public function storeCard(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'last4_digits' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'observacao' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);

        TiCard::create([
            'name' => trim($data['name']),
            'last4_digits' => trim($data['last4_digits']),
            'observacao' => trim((string) ($data['observacao'] ?? '')) ?: null,
            'active' => (bool) ($data['active'] ?? true),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('administrativo.cartao-ti.index')->with('success', 'Cartão cadastrado com sucesso.');
    }

    public function editCard(TiCard $card): View
    {
        return view('panels.administrativo.cartao-ti-card-form', [
            'card' => $card,
            'action' => route('administrativo.cartao-ti.cards.update', $card),
            'method' => 'PATCH',
            'title' => 'Editar cartão',
        ]);
    }

    public function updateCard(Request $request, TiCard $card): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'last4_digits' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'observacao' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);

        $card->update([
            'name' => trim($data['name']),
            'last4_digits' => trim($data['last4_digits']),
            'observacao' => trim((string) ($data['observacao'] ?? '')) ?: null,
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : $card->active,
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('administrativo.cartao-ti.index')->with('success', 'Cartão atualizado com sucesso.');
    }

    public function toggleCard(TiCard $card): RedirectResponse
    {
        $card->update([
            'active' => ! $card->active,
            'updated_by' => Auth::id(),
        ]);

        return back()->with('success', $card->active ? 'Cartão ativado.' : 'Cartão desativado.');
    }

    public function createPurchase(): View
    {
        return view('panels.administrativo.cartao-ti-purchase-form', [
            'purchase' => null,
            'cards' => TiCard::query()->where('active', true)->orderBy('name')->get(),
            'action' => route('administrativo.cartao-ti.purchases.store'),
            'method' => 'POST',
            'title' => 'Novo lançamento',
        ]);
    }

    public function storePurchase(Request $request): RedirectResponse
    {
        $data = $this->validatePurchase($request);

        $purchase = TiCardPurchase::create([
            'ti_card_id' => (int) $data['ti_card_id'],
            'purchase_name' => trim($data['purchase_name']),
            'purchase_value' => $this->parseBrazilianMoney($data['purchase_value']),
            'billing_type' => $data['billing_type'],
            'currency' => $data['currency'],
            'purchase_date' => $data['purchase_date'] ?? null,
            'observacao' => trim((string) ($data['observacao'] ?? '')) ?: null,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        if ($request->hasFile('invoice_file')) {
            $this->storeInvoiceAttachment($purchase, $request->file('invoice_file'));
        }

        return redirect()->route('administrativo.cartao-ti.index')->with('success', 'Lançamento cadastrado com sucesso.');
    }

    public function editPurchase(TiCardPurchase $purchase): View
    {
        return view('panels.administrativo.cartao-ti-purchase-form', [
            'purchase' => $purchase->load('card'),
            'cards' => TiCard::query()->orderBy('name')->get(),
            'action' => route('administrativo.cartao-ti.purchases.update', $purchase),
            'method' => 'PATCH',
            'title' => 'Editar lançamento',
        ]);
    }

    public function updatePurchase(Request $request, TiCardPurchase $purchase): RedirectResponse
    {
        $data = $this->validatePurchase($request);

        $purchase->update([
            'ti_card_id' => (int) $data['ti_card_id'],
            'purchase_name' => trim($data['purchase_name']),
            'purchase_value' => $this->parseBrazilianMoney($data['purchase_value']),
            'billing_type' => $data['billing_type'],
            'currency' => $data['currency'],
            'purchase_date' => $data['purchase_date'] ?? null,
            'observacao' => trim((string) ($data['observacao'] ?? '')) ?: null,
            'updated_by' => Auth::id(),
        ]);

        if ($request->hasFile('invoice_file')) {
            $this->storeInvoiceAttachment($purchase, $request->file('invoice_file'));
        }

        return redirect()->route('administrativo.cartao-ti.index')->with('success', 'Lançamento atualizado com sucesso.');
    }

    public function downloadInvoice(TiCardPurchase $purchase): BinaryFileResponse
    {
        abort_unless($purchase->invoice_storage_path && Storage::disk('local')->exists($purchase->invoice_storage_path), 404, 'Fatura não encontrada.');

        return response()->download(
            Storage::disk('local')->path($purchase->invoice_storage_path),
            $purchase->invoice_original_name ?: basename($purchase->invoice_storage_path)
        );
    }

    private function validatePurchase(Request $request): array
    {
        return $request->validate([
            'ti_card_id' => ['required', 'integer', 'exists:ti_cards,id'],
            'purchase_name' => ['required', 'string', 'max:255'],
            'purchase_value' => ['required', 'string', 'max:40'],
            'billing_type' => ['required', 'in:mensalidade,anualidade,avulsa'],
            'currency' => ['required', 'in:BRL,USD,EUR'],
            'purchase_date' => ['nullable', 'date'],
            'observacao' => ['nullable', 'string'],
            'invoice_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);
    }

    private function parseBrazilianMoney(?string $value): float
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }

        $normalized = str_replace(['.', ' '], '', $raw);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function storeInvoiceAttachment(TiCardPurchase $purchase, \Illuminate\Http\UploadedFile $file): void
    {
        if ($purchase->invoice_storage_path && Storage::disk('local')->exists($purchase->invoice_storage_path)) {
            Storage::disk('local')->delete($purchase->invoice_storage_path);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'fatura';
        $path = $file->storeAs(
            'administrativo/cartao-ti/faturas',
            Str::uuid() . '-' . $safeName . '.' . $extension,
            'local'
        );

        $purchase->update([
            'invoice_original_name' => $file->getClientOriginalName(),
            'invoice_storage_path' => $path,
            'invoice_mime_type' => $file->getMimeType(),
            'invoice_uploaded_at' => now(),
            'invoice_uploaded_by' => Auth::id(),
        ]);
    }
}
