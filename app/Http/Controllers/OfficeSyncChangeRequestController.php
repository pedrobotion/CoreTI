<?php

namespace App\Http\Controllers;

use App\Models\OfficeSyncChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OfficeSyncChangeRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = OfficeSyncChangeRequest::query()->with('officeLicense')->latest('detected_at');
        $status = (string) $request->query('status', 'pending');
        if (in_array($status, ['pending', 'approved', 'rejected', 'obsolete'], true)) {
            $query->where('status', $status);
        }
        if ($term = trim((string) $request->query('q'))) {
            $query->whereHas('officeLicense', fn ($q) => $q->where('nome', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
        }

        return view('service-desk.licensing.office-change-requests', [
            'requests' => $query->paginate(30)->withQueryString(),
            'status' => $status,
            'search' => (string) $request->query('q', ''),
        ]);
    }

    public function approve(Request $request, OfficeSyncChangeRequest $changeRequest)
    {
        abort_unless($changeRequest->status === 'pending', 422, 'Esta pendência já foi revisada.');
        $license = $changeRequest->officeLicense;
        $field = $changeRequest->field_name;
        $allowed = ['organization_unit_id', 'organization_department_id', 'organization_cost_center_id', 'organization_position_id'];
        abort_unless(in_array($field, $allowed, true), 422, 'Campo de aprovação inválido.');
        $sourceField = str_replace('_id', '_source', $field);
        $license->update([$field => $changeRequest->proposed_value !== '' ? $changeRequest->proposed_value : null, $sourceField => $request->input('mode') === 'sync' ? 'sync' : 'manual', 'organization_assignment_updated_at' => now(), 'organization_assignment_updated_by' => Auth::id()]);
        $changeRequest->update(['status' => 'approved', 'reviewed_at' => now(), 'reviewed_by' => Auth::id(), 'review_notes' => trim((string) $request->input('review_notes'))]);

        return back()->with('success', 'Alteração aprovada.');
    }

    public function reject(Request $request, OfficeSyncChangeRequest $changeRequest)
    {
        $data = $request->validate(['review_notes' => ['required', 'string', 'max:2000']]);
        abort_unless($changeRequest->status === 'pending', 422, 'Esta pendência já foi revisada.');
        $changeRequest->update(['status' => 'rejected', 'reviewed_at' => now(), 'reviewed_by' => Auth::id(), 'review_notes' => $data['review_notes']]);

        return back()->with('success', 'Alteração rejeitada e justificada.');
    }
}
