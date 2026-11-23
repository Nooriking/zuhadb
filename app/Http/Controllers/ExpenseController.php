<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenses,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $branchId = $request->query('branch_id');
        $category = $request->query('category');
        $currencyId = $request->query('currency_id');
        $from = $request->query('from');
        $to = $request->query('to');
        $user = $request->user();

        if ($user?->isBranchScoped()) {
            $branchId = $user->branch_id;
        }

        $scoped = fn ($q) => $q
            ->when($branchId, fn ($query, $v) => $query->where('branch_id', $v));

        $expenses = Expense::query()
            ->with(['branch:id,name,code', 'creator:id,name', 'currency:id,code,name,symbol'])
            ->notTrashed()
            ->tap($scoped)
            ->when($search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->when($category, fn ($q, $v) => $q->where('category', $v))
            ->when($currencyId, fn ($q, $v) => $q->where('currency_id', $v))
            ->when($from, fn ($q, $v) => $q->whereDate('expense_date', '>=', $v))
            ->when($to, fn ($q, $v) => $q->whereDate('expense_date', '<=', $v))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => Expense::notTrashed()->tap($scoped)->count(),
            'this_month' => (float) Expense::notTrashed()->tap($scoped)
                ->whereMonth('expense_date', now()->month)
                ->whereYear('expense_date', now()->year)
                ->sum('amount_afn'),
            'total_afn' => (float) Expense::notTrashed()->tap($scoped)->sum('amount_afn'),
            'today_afn' => (float) Expense::notTrashed()->tap($scoped)
                ->whereDate('expense_date', today())
                ->sum('amount_afn'),
            'today_count' => Expense::notTrashed()->tap($scoped)
                ->whereDate('expense_date', today())
                ->count(),
        ];

        return Inertia::render('expenses/index', [
            'expenses' => $expenses,
            'branches' => Branch::notTrashed()->visibleTo($user)->select('id', 'name', 'code')->get(),
            'categories' => Expense::CATEGORIES,
            'currencies' => Currency::formOptions(),
            'filters' => [
                'search' => $search,
                'branch_id' => $branchId,
                'category' => $category,
                'currency_id' => $currencyId,
                'from' => $from,
                'to' => $to,
            ],
            'stats' => $stats,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('expenses/create', [
            'branches' => Branch::notTrashed()->active()->visibleTo($request->user())->select('id', 'name', 'code')->get(),
            'categories' => Expense::CATEGORIES,
            'methods' => Expense::PAYMENT_METHODS,
            'currencies' => Currency::formOptions(),
            'nextNumber' => Expense::generateExpenseNumber(),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $expense = $this->expenses->create($request->validated(), Auth::id());

        return redirect()
            ->route('expenses.index')
            ->with('success', "Expense \"{$expense->expense_number}\" created successfully.");
    }

    public function show(Request $request, Expense $expense): Response
    {
        if ($expense->trash) {
            abort(404);
        }

        $user = $request->user();
        if ($user?->isBranchScoped() && (int) $expense->branch_id !== (int) $user->branch_id) {
            abort(403, 'You are not allowed to view expenses from another branch.');
        }

        $expense->load(['branch:id,name,code', 'creator:id,name,email', 'currency:id,code,name,symbol']);

        return Inertia::render('expenses/show', [
            'expense' => $expense,
        ]);
    }

    public function edit(Request $request, Expense $expense): Response
    {
        if ($expense->trash) {
            abort(404);
        }

        $user = $request->user();
        if ($user?->isBranchScoped() && (int) $expense->branch_id !== (int) $user->branch_id) {
            abort(403, 'You are not allowed to edit expenses from another branch.');
        }

        $expense->load('currency:id,code,name,symbol');

        return Inertia::render('expenses/edit', [
            'expense' => $expense,
            'branches' => Branch::notTrashed()->active()->visibleTo($user)->select('id', 'name', 'code')->get(),
            'categories' => Expense::CATEGORIES,
            'methods' => Expense::PAYMENT_METHODS,
            'currencies' => Currency::formOptions([(int) $expense->currency_id]),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        if ($expense->trash) {
            abort(404);
        }

        $user = $request->user();
        if ($user?->isBranchScoped() && (int) $expense->branch_id !== (int) $user->branch_id) {
            abort(403, 'You are not allowed to edit expenses from another branch.');
        }

        $expense = $this->expenses->update($expense, $request->validated(), Auth::id());

        return redirect()
            ->route('expenses.index')
            ->with('success', "Expense \"{$expense->expense_number}\" updated successfully.");
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $number = $expense->expense_number;

        $this->expenses->void($expense, Auth::id());

        return redirect()->route('expenses.index')
            ->with('success', "Expense \"{$number}\" was moved to trash.");
    }

    public function restore(int $id): RedirectResponse
    {
        $expense = Expense::trashFlagged()->findOrFail($id);
        $expense->update(['trash' => false, 'user_id' => Auth::id()]);

        return redirect()->route('expenses.index')
            ->with('success', "Expense \"{$expense->expense_number}\" was restored.");
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $expense = Expense::trashFlagged()->findOrFail($id);
        $number = $expense->expense_number;
        $expense->forceDelete();

        return redirect()->route('expenses.index')
            ->with('success', "Expense \"{$number}\" was permanently deleted.");
    }

    public function trash(): Response
    {
        $expenses = Expense::trashFlagged()
            ->with(['branch:id,name', 'creator:id,name', 'currency:id,code,symbol'])
            ->orderByDesc('updated_at')
            ->paginate(20);

        return Inertia::render('expenses/trash', [
            'expenses' => $expenses,
        ]);
    }
}
