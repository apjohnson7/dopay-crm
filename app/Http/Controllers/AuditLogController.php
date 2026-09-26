<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('audit.view');
        $q = $request->query('q');
        $logs = AuditLog::with('user')
            ->when($request->query('user'), fn ($b, $u) => $b->where('user_id', $u))
            ->when($q, fn ($b) => $b->where(fn ($w) => $w->where('action', 'like', "%$q%")->orWhere('record_label', 'like', "%$q%")))
            ->latest('created_at')->paginate(50)->withQueryString();

        return view('audit.index', ['logs' => $logs, 'users' => User::orderBy('name')->get(['id', 'name']), 'q' => $q]);
    }
}
