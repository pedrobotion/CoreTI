<?php

namespace App\Http\Controllers;

use App\Models\CoreTiNotificationRecipient;
use App\Models\CoreTiNotificationRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoreTiNotificationSettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.notifications', [
            'recipients' => CoreTiNotificationRecipient::query()->with('rules')->orderBy('name')->get(),
            'rules' => CoreTiNotificationRule::query()->with('recipients')->orderBy('id')->get(),
        ]);
    }

    public function storeRecipient(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:coreti_notification_recipients,email'],
        ]);
        $data['active'] = true;
        CoreTiNotificationRecipient::create($data);
        return back()->with('success', 'Destinatário adicionado.');
    }

    public function updateRecipient(Request $request, CoreTiNotificationRecipient $recipient): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:coreti_notification_recipients,email,' . $recipient->id],
        ]);
        $recipient->update($data);
        return back()->with('success', 'Destinatário atualizado.');
    }

    public function toggleRecipient(CoreTiNotificationRecipient $recipient): RedirectResponse
    {
        $recipient->update(['active' => ! $recipient->active]);
        return back()->with('success', 'Status do destinatário atualizado.');
    }

    public function updateRules(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rules' => ['nullable', 'array'],
            'rules.*' => ['nullable', 'array'],
            'rules.*.*' => ['integer', 'exists:coreti_notification_recipients,id'],
        ]);

        foreach ((array) ($data['rules'] ?? []) as $eventKey => $recipientIds) {
            $rule = CoreTiNotificationRule::query()->where('event_key', $eventKey)->first();
            if (! $rule) {
                continue;
            }
            $recipientIds = array_map('intval', (array) $recipientIds);
            $rule->recipients()->sync($recipientIds);
        }

        return back()->with('success', 'Roteamento dos avisos atualizado.');
    }
}
