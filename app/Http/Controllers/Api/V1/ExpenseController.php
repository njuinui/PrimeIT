<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Approvals\ApprovalEngine;
use App\Domain\Finance\ExpenseNumber;
use App\Domain\Finance\FinanceScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\DecideExpenseRequest;
use App\Http\Requests\Finance\StoreExpenseRequest;
use App\Http\Resources\V1\ExpenseResource;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Models\User;
use App\Notifications\Finance\ExpenseDecidedNotification;
use App\Notifications\Finance\ExpenseSubmittedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    private const RELATIONS = ['category', 'project', 'department', 'submitter.employee', 'decider.employee'];

    public function __construct(
        private readonly ApprovalEngine $approvals,
        private readonly ExpenseNumber $numbers,
        private readonly FinanceScope $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('finance.view');

        $user     = $request->user();
        $search   = trim((string) $request->query('search'));
        $status   = (string) $request->query('status');
        $category = $this->uuidToId(ExpenseCategory::class, (string) $request->query('category'));
        $project  = $this->uuidToId(Project::class, (string) $request->query('project'));
        $from     = (string) $request->query('from');
        $to       = (string) $request->query('to');

        $expenses = $this->scope->visibleExpenses($user)
            ->with(self::RELATIONS)
            ->withSum('payments as paid_amount', 'amount')
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('title', 'like', "%{$search}%")
                ->orWhere('expense_number', 'like', "%{$search}%")))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($category !== null, fn ($q) => $q->where('expense_category_id', $category))
            ->when($project !== null, fn ($q) => $q->where('project_id', $project))
            ->when($from !== '' && strtotime($from) !== false, fn ($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to !== '' && strtotime($to) !== false, fn ($q) => $q->whereDate('expense_date', '<=', $to))
            ->when($request->boolean('mine'), fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return $this->paginated($expenses, ExpenseResource::class);
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $this->authorize('expenses.create');

        $user     = $request->user();
        $employee = $user->employee()->first();

        $data = collect($request->validated())->except('receipt')->all();

        $receiptPath = null;
        $receiptName = null;

        if ($request->hasFile('receipt')) {
            $receipt      = $request->file('receipt');
            $receiptName  = $receipt->getClientOriginalName();
            $receiptPath  = $receipt->store('receipts', 'local');
        }

        // Department attribution: project's department, else the submitter's.
        $departmentId = $data['project_id'] !== null
            ? (Project::query()->where('id', $data['project_id'])->value('department_id') ?? $employee?->department_id)
            : $employee?->department_id;

        $expense = Expense::create([
            ...$data,
            'expense_number' => $this->numbers->next(),
            'user_id'        => $user->id,
            'employee_id'    => $employee?->id,
            'department_id'  => $departmentId,
            'currency'       => 'XAF', // single workspace currency for v1
            'status'         => 'draft',
            'receipt_path'   => $receiptPath,
            'receipt_name'   => $receiptName,
        ]);

        $expense->recordHistory($user, 'created', null, 'draft');

        $expense->loadMissing(self::RELATIONS);

        return $this->success(new ExpenseResource($expense), 'Expense saved as draft.', 201);
    }

    public function show(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('view', $expense);

        $expense->loadMissing([...self::RELATIONS, 'history.user', 'payments.recorder'])
            ->loadSum('payments as paid_amount', 'amount');

        return $this->success(new ExpenseResource($expense));
    }

    public function update(StoreExpenseRequest $request, Expense $expense): JsonResponse
    {
        $this->authorize('update', $expense);

        $data = collect($request->validated())->except('receipt')->all();

        if ($request->hasFile('receipt')) {
            $receipt = $request->file('receipt');

            $data['receipt_path'] = $receipt->store('receipts', 'local');
            $data['receipt_name'] = $receipt->getClientOriginalName();
        }

        $expense->update($data);

        return $this->success(
            new ExpenseResource($expense->fresh()->loadMissing(self::RELATIONS)),
            'Expense updated.',
        );
    }

    public function submit(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('submit', $expense);

        $from = $expense->status;

        $expense->forceFill(['status' => 'submitted'])->save();
        $expense->recordHistory($request->user(), 'submitted', $from, 'submitted');

        $this->approvals->startFor($expense->fresh(), $request->user());

        // Every approver is notified (email + in-app, preference-aware, realtime).
        User::permission('expenses.approve')
            ->where('id', '!=', $request->user()->id)
            ->get()
            ->each(fn (User $approver) => $approver->notify(
                new ExpenseSubmittedNotification($expense->fresh(), $request->user()),
            ));

        return $this->success(
            new ExpenseResource($expense->fresh()->loadMissing(self::RELATIONS)),
            'Expense submitted for approval.',
        );
    }

    public function approve(DecideExpenseRequest $request, Expense $expense): JsonResponse
    {
        return $this->decide($request, $expense, true);
    }

    public function reject(DecideExpenseRequest $request, Expense $expense): JsonResponse
    {
        return $this->decide($request, $expense, false);
    }

    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('delete', $expense);

        $this->approvals->cancelFor($expense);
        $expense->delete();

        return $this->success(message: 'Expense deleted.');
    }

    public function receipt(Request $request, Expense $expense): mixed
    {
        $this->authorize('view', $expense);

        if ($expense->receipt_path === null || ! Storage::disk('local')->exists($expense->receipt_path)) {
            return $this->error('No receipt attached to this expense.', 404);
        }

        return Storage::disk('local')->download(
            $expense->receipt_path,
            $expense->receipt_name ?? 'receipt',
        );
    }

    // ── Internals ─────────────────────────────────────────────

    private function decide(DecideExpenseRequest $request, Expense $expense, bool $approved): JsonResponse
    {
        $this->authorize('decide', $expense);

        if ($expense->status !== 'submitted') {
            return $this->error(
                sprintf('Only submitted expenses can be %s — this one is %s.', $approved ? 'approved' : 'rejected', $expense->status),
                409,
            );
        }

        $user = $request->user();

        $expense->forceFill([
            'status'       => $approved ? 'approved' : 'rejected',
            'decided_by'   => $user->id,
            'decided_at'   => now(),
            'decision_note' => $request->validated('note'),
        ])->save();

        $expense->recordHistory(
            $user,
            $approved ? 'approved' : 'rejected',
            'submitted',
            $approved ? 'approved' : 'rejected',
            $request->validated('note'),
        );

        $submitter = $expense->submitter()->first();

        if ($submitter !== null && $submitter->id !== $user->id) {
            $submitter->notify(new ExpenseDecidedNotification($expense->fresh(), $user, $approved));
        }

        return $this->success(
            new ExpenseResource($expense->fresh()->loadMissing(self::RELATIONS)),
            $approved ? 'Expense approved.' : 'Expense rejected.',
        );
    }

    private function uuidToId(string $model, string $uuid): ?int
    {
        if ($uuid === '') {
            return null;
        }

        return $model::query()->where('uuid', $uuid)->value('id');
    }
}