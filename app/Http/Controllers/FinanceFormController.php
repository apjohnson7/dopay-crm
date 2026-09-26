<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Document;
use App\Models\ExpenseCategory;
use App\Models\FinanceForm;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\FinanceFormService;
use App\Support\Scope;
use App\Support\SecondApproval;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class FinanceFormController extends Controller
{
    public function __construct(private FinanceFormService $forms, private ApprovalService $approvals) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status', 'all');
        $type = $request->query('type');
        $awaiting = $this->approvals->awaiting($user);
        $query = FinanceForm::visibleTo($user)->with('preparer', 'country', 'branch', 'signatures')->latest('updated_at');
        Scope::apply($query);
        $groups = [
            'drafts' => ['draft', 'returned'], 'in_approval' => ['in_approval'], 'open' => ['approved', 'awaiting_liquidation'],
            'closed' => ['paid', 'reimbursed', 'liquidated', 'settled', 'void'],
        ];
        $list = $status === 'mine' ? $awaiting : $query->when($type, fn ($q) => $q->where('type', $type))
            ->when(isset($groups[$status]), fn ($q) => $q->whereIn('status', $groups[$status]))->paginate(25)->withQueryString();

        return view('forms.index', [
            'forms' => $list, 'awaiting' => $awaiting, 'status' => $status, 'type' => $type,
            'intercompany' => FinanceForm::visibleTo($user)->where('type', 'J')->where('status', 'approved')->with('country')->get(),
            'advances' => Scope::apply(FinanceForm::visibleTo($user))->where('type', 'K')->whereIn('status', ['approved', 'awaiting_liquidation'])->with('preparer')->get(),
            'approvals' => $this->approvals,
        ]);
    }

    public function create(string $type, Request $request)
    {
        $this->authorize('forms.submit');
        abort_unless(config('dopay.forms.'.$type), 404);
        $user = $request->user();
        $form = new FinanceForm(['type' => $type, 'branch_id' => $user->branch_id, 'country_id' => $user->countryId(), 'status' => 'draft',
            'data' => ['date' => today()->toDateString(), 'route' => 'CFO', 'holder_id' => $user->id, 'purchaser_id' => $user->id, 'position' => $user->roleName(),
                'requester_name' => $user->name, 'year' => now()->year, 'period' => 'Monthly', 'month' => now()->month, 'quarter' => now()->quarter, 'purpose' => '1',
                'pay_method' => $type === 'K' ? 'Mobile Money' : 'Bank transfer'],
            'currency_code' => $user->country()?->currency_code]);

        return view('forms.edit', $this->editorData($form));
    }

    public function store(string $type, Request $request)
    {
        $this->authorize('forms.submit');
        $form = $this->forms->save($type, $this->input($request), $request->user());
        $this->storeUploads($request, $form);

        return $this->afterSave($request, $form);
    }

    public function show(FinanceForm $form, Request $request)
    {
        $this->authorizeView($form);
        $form->load('lines.category', 'budgetLines', 'signatures.user', 'documents', 'preparer', 'country', 'branch');
        $step = $this->approvals->currentStep($form);

        return view('forms.show', [
            'form' => $form,
            'steps' => $this->approvals->steps($form),
            'current' => $step,
            'canSign' => $step !== null && $this->approvals->canSign($request->user(), $form, $step),
            'waitingFor' => $step !== null ? $this->approvals->eligibleSigners($form, $step) : collect(),
            'ledger' => $form->type === 'G' ? $this->forms->ledger($form) : null,
            'authorizers' => SecondApproval::candidates($request->user(), $form->country_id),
            'categories' => ExpenseCategory::orderBy('position')->get(),
        ]);
    }

    public function edit(FinanceForm $form)
    {
        $this->authorizeEdit($form);
        abort_unless(in_array($form->status, ['draft', 'returned'], true), 403, 'Only drafts and returned forms can be edited.');

        return view('forms.edit', $this->editorData($form->load('lines', 'budgetLines', 'documents')));
    }

    public function update(FinanceForm $form, Request $request)
    {
        $this->authorizeEdit($form);
        $this->forms->save($form->type, $this->input($request), $request->user(), $form);
        $this->storeUploads($request, $form);

        return $this->afterSave($request, $form->refresh());
    }

    public function submit(FinanceForm $form, Request $request)
    {
        $this->authorizeEdit($form);
        $this->forms->validateForSubmit($form);
        $this->approvals->submit($form, $request->user());

        return redirect()->route('forms.show', $form)->with('status', $form->refresh()->status === 'approved' ? 'Submitted and fully approved.' : 'Submitted for approval.');
    }

    public function sign(FinanceForm $form, Request $request)
    {
        $data = $request->validate(['pin' => 'required|string', 'comment' => 'nullable|string|max:250']);
        $this->approvals->sign($form, $request->user(), $data['pin'], $data['comment'] ?? null);

        return back()->with('status', $form->refresh()->status === 'approved' ? 'Signed. The form is fully approved.' : 'Signed. Sent to the next approver.');
    }

    public function returnToPreparer(FinanceForm $form, Request $request)
    {
        $this->approvals->returnToPreparer($form, $request->user(), $request->validate(['note' => 'required|string|min:4|max:500'])['note']);

        return back()->with('status', 'Returned to the preparer.');
    }

    public function revise(FinanceForm $form, Request $request)
    {
        $this->authorizeEdit($form, allowSigned: true);
        abort_unless(in_array($form->status, ['in_approval', 'approved'], true), 403, 'Only forms in approval or approved (not yet paid) can be revised. Paid, disbursed or closed forms need a reversal.');
        SecondApproval::verify($request, 'Revise '.$form->reference, $form->country_id);
        $form->update(['status' => 'draft', 'version' => $form->version + 1, 'approved_at' => null]);

        return redirect()->route('forms.edit', $form)->with('status', 'Authorized. Saving and submitting restarts the approval chain.');
    }

    public function void(FinanceForm $form, Request $request)
    {
        $this->authorizeEdit($form, allowSigned: true);
        abort_unless(in_array($form->status, ['draft', 'returned', 'in_approval'], true), 403);
        SecondApproval::verify($request, 'Void '.$form->reference, $form->country_id);
        $form->update(['status' => 'void']);

        return back()->with('status', 'Form voided. It stays in the records.');
    }

    public function action(FinanceForm $form, string $action, Request $request)
    {
        $this->authorizeView($form);
        $user = $request->user();
        // Money actions happen in the country that pays: the form's own country, or the paying country of an interbranch memo.
        $payingCountry = $form->type === 'J' ? (int) $form->datum('paying_country_id', $form->country_id) : $form->country_id;
        abort_unless($user->canActForCountry($payingCountry), 403, 'Only the paying country can record this.');
        if ($action === 'liquidate') {
            abort_unless(in_array($user->id, [(int) $form->prepared_by, (int) $form->datum('holder_id')], true) || $user->can('payments.record'), 403, 'Only the person who received the advance, or finance, can liquidate it.');
        } else {
            $this->authorize('payments.record');
        }
        match ($action) {
            'paid' => $this->forms->markPaid($form, $user, $request->validate(['paid_on' => 'required|date'])['paid_on'], $request->input('reference')),
            'reimburse' => $this->forms->reimburse($form),
            'disburse' => $this->forms->disburse($form),
            'liquidate' => $this->liquidate($form, $request),
            'settle' => $this->forms->settle($form),
        };

        return back()->with('status', ['paid' => 'Marked as paid and posted to Expenses.', 'reimburse' => 'Float marked as reimbursed.', 'disburse' => 'Advance disbursed. Receipts are due by '.fdate($form->refresh()->datum('liquidation_date')).'.', 'liquidate' => 'Liquidation recorded.', 'settle' => 'Intercompany balance settled.'][$action]);
    }

    public function pdf(FinanceForm $form)
    {
        $this->authorizeView($form);
        $form->load('lines.category', 'budgetLines', 'signatures.user', 'preparer', 'country', 'branch');
        $landscape = $form->type === 'G' || ($form->type === 'F' && $form->datum('period') !== 'Monthly');

        return Pdf::loadView('forms.pdf', ['form' => $form, 'steps' => $this->approvals->steps($form), 'ledger' => $form->type === 'G' ? $this->forms->ledger($form) : null])
            ->setPaper('a4', $landscape ? 'landscape' : 'portrait')->download($form->reference.'.pdf');
    }

    private function liquidate(FinanceForm $form, Request $request): void
    {
        abort_unless($form->type === 'K' && $form->status === 'awaiting_liquidation', 422, 'Only disbursed cash advances can be liquidated.');
        $data = $request->validate(['spent' => 'required|numeric|min:0|max:'.(float) $form->total, 'receipts' => 'required|array|min:1|max:20', 'receipts.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png,xlsx']);
        foreach ($request->file('receipts') as $file) {
            $this->storeDocument($form, $file, 'Liquidation receipt');
        }
        $this->forms->liquidate($form, (float) $data['spent'], $request->user());
    }

    private function afterSave(Request $request, FinanceForm $form)
    {
        if ($request->boolean('submit')) {
            return $this->submit($form, $request);
        }

        return redirect()->route('forms.show', $form)->with('status', 'Draft saved · '.$form->reference);
    }

    private function input(Request $request): array
    {
        return $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'currency_code' => 'nullable|exists:currencies,code',
            'data' => 'array',
            'lines' => 'array|max:200',
            'lines.*.amount' => 'nullable|numeric|min:0|max:999999999999',
            'lines.*.inflow' => 'nullable|numeric|min:0|max:999999999999',
            'lines.*.outflow' => 'nullable|numeric|min:0|max:999999999999',
            'lines.*.category' => ['nullable', function ($attr, $v, $fail) { if ($v !== 'inflow' && ! \App\Models\ExpenseCategory::whereKey((int) $v)->exists()) { $fail('Choose a category from the list.'); } }],
            'lines.*.date' => 'nullable|date',
            'lines.*.description' => 'nullable|string|max:500',
            'lines.*.account_details' => 'nullable|string|max:250',
            'lines.*.memo_ref' => 'nullable|string|max:60',
            'lines.*.party' => 'nullable|string|max:190',
            'lines.*.notes' => 'nullable|string|max:250',
            'lines.*.purpose' => 'nullable|string|max:500',
            'budget' => 'array',
            'budget.*' => 'array',
            'budget.*.*' => 'nullable|numeric|min:0|max:999999999999',
            'budget_notes' => 'array',
            'budget_notes.*' => 'nullable|string|max:250',
            'attachments.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png,docx,xlsx',
        ]);
    }

    private function storeUploads(Request $request, FinanceForm $form): void
    {
        foreach ((array) $request->file('attachments') as $file) {
            $this->storeDocument($form, $file, 'Supporting document');
        }
    }

    private function storeDocument(FinanceForm $form, $file, string $category): void
    {
        $path = $file->store('forms/'.$form->id, 'local');
        Document::create(['name' => $file->getClientOriginalName(), 'disk' => 'local', 'path' => $path, 'mime' => $file->getMimeType(), 'size' => $file->getSize(),
            'category' => $category, 'documentable_type' => $form->getMorphClass(), 'documentable_id' => $form->id, 'uploaded_by' => auth()->id()]);
    }

    /** Drafts are changed by their preparer (or a forms administrator) in the form's own country. */
    private function authorizeEdit(FinanceForm $form, bool $allowSigned = false): void
    {
        $this->authorizeView($form);
        $user = auth()->user();
        abort_unless($user->canActForCountry($form->country_id), 403, 'This form belongs to another country.');
        abort_unless($user->id === (int) $form->prepared_by || $user->can('forms.admin'), 403, 'Only the preparer or a forms administrator can change this form.');
        if (! $allowSigned) {
            abort_unless(in_array($form->status, ['draft', 'returned'], true), 403, 'Signed forms can only be changed through an authorized revision.');
        }
    }

    private function authorizeView(FinanceForm $form): void
    {
        $user = auth()->user();
        $step = $this->approvals->currentStep($form);
        $awaitingMe = $step !== null && $this->approvals->canSign($user, $form, $step);
        abort_unless($awaitingMe || FinanceForm::visibleTo($user)->whereKey($form->id)->exists(), 403, 'This form belongs to another country.');
    }

    private function editorData(FinanceForm $form): array
    {
        $user = auth()->user();
        $branches = Branch::with('country')->where('is_active', true)->orderBy('name')->get()->filter(fn ($b) => $user->canActForCountry($b->country_id));
        $countryId = $form->country_id ?? $user->countryId();

        return [
            'form' => $form,
            'def' => config('dopay.forms.'.$form->type),
            'branches' => $branches,
            'countries' => Country::where('is_active', true)->orderBy('name')->get(),
            'categories' => ExpenseCategory::orderBy('position')->get(),
            'colleagues' => User::where('is_active', true)->whereHas('branch', fn ($q) => $q->where('country_id', $countryId))->orderBy('name')->get(),
            'memos' => FinanceForm::visibleTo($user)->where('type', 'A')->whereNotNull('reference')->latest()->limit(50)->get(['id', 'reference', 'data', 'total', 'currency_code']),
            'certifications' => FinanceForm::visibleTo($user)->where('type', 'C')->where('status', 'approved')->get(['id', 'reference']),
            'currencies' => \App\Models\Currency::orderBy('code')->pluck('code'),
        ];
    }
}
