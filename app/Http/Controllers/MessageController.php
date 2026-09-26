<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\FinanceForm;
use App\Models\Invoice;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Http\Request;

/** Team messages: direct, group and country channels between officers in any branch or country. */
class MessageController extends Controller
{
    public function __construct(private MessagingService $messaging) {}

    public function index(Request $request)
    {
        $this->authorize('messages.use');
        $user = $request->user();
        $conversations = $user->conversations()->with('users.branch.country', 'messages')->orderByDesc('last_message_at')->get();
        $current = $request->query('c') ? $conversations->firstWhere('id', (int) $request->query('c')) : null;
        if ($current) {
            $this->messaging->markRead($current, $user);
            $current->load('messages.user.branch.country', 'messages.attachable');
        }

        return view('messages.index', [
            'conversations' => $conversations,
            'current' => $current,
            'people' => User::where('is_active', true)->where('id', '!=', $user->id)->with('branch.country', 'roles')->orderBy('name')->get()->groupBy(fn ($u) => $u->branch?->country?->name ?? 'Global'),
            'attachables' => [
                'forms' => FinanceForm::visibleTo($user)->whereNotNull('reference')->latest('updated_at')->limit(15)->get(['id', 'reference', 'type']),
                'invoices' => Invoice::visibleTo($user)->latest('id')->limit(10)->get(['id', 'number']),
            ],
        ]);
    }

    /** Start a conversation with one person (direct) or several (group), with a first message. */
    public function store(Request $request)
    {
        $this->authorize('messages.use');
        $data = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id,is_active,1',
            'name' => 'nullable|string|max:80',
            'body' => 'required|string|max:5000',
            'attach' => 'nullable|string',
        ]);
        $me = $request->user();
        $ids = array_values(array_diff(array_map('intval', $data['user_ids']), [$me->id]));
        if (count($ids) === 1) {
            $conversation = Conversation::directBetween($me, User::findOrFail($ids[0]));
        } else {
            $conversation = Conversation::create(['type' => 'group', 'name' => $data['name'] ?: User::whereIn('id', $ids)->pluck('name')->map(fn ($n) => strtok($n, ' '))->prepend(strtok($me->name, ' '))->implode(', '), 'created_by' => $me->id]);
            $conversation->users()->attach(array_merge([$me->id], $ids));
        }
        $this->messaging->send($conversation, $me, $data['body'], $this->attachable($data['attach'] ?? null));

        return redirect()->route('messages.index', ['c' => $conversation->id]);
    }

    public function send(Conversation $conversation, Request $request)
    {
        $this->authorize('messages.use');
        $data = $request->validate(['body' => 'required_without:attach|nullable|string|max:5000', 'attach' => 'nullable|string']);
        $this->messaging->send($conversation, $request->user(), $data['body'] ?? 'Please see this record.', $this->attachable($data['attach'] ?? null));

        return redirect()->to(route('messages.index', ['c' => $conversation->id]).'#end');
    }

    private function attachable(?string $key)
    {
        if (! $key || ! str_contains($key, ':')) {
            return null;
        }
        [$kind, $id] = explode(':', $key, 2);
        $model = match ($kind) {
            'form' => FinanceForm::visibleTo(auth()->user())->find($id),
            'invoice' => Invoice::visibleTo(auth()->user())->find($id),
            default => null,
        };

        return $model;
    }
}
