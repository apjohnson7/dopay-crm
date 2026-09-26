<?php

namespace App\Http\Controllers;

use App\Support\Scope;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('audit.view');
        $q = $request->query('q');
        $logs = Scope::apply(AuditLog::with('user'))
            ->when($request->query('user'), fn ($b, $u) => $b->where('user_id', $u))
            ->when($q, fn ($b) => $b->where(fn ($w) => $w->where('action', 'like', "%$q%")->orWhere('record_label', 'like', "%$q%")))
            ->latest('created_at')->paginate(50)->withQueryString();

        return view('audit.index', ['logs' => $logs, 'users' => User::when(! $request->user()->isGlobal(), fn ($u) => $u->whereHas('branch', fn ($b) => $b->where('country_id', $request->user()->countryId())))->orderBy('name')->get(['id', 'name']), 'q' => $q]);
    }
}
