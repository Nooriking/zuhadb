<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Expense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $search   = $request->query('search');
        $branchId = $request->query('branch_id');
        $category = $request->query('category');
        $from     = $request->query('from');
        $to       = $request->query('to');

        $expenses = Expense::query()
            ->with(['branch:id,name,code', 'creator:id,name'])
            ->notTrashed()
            ->when($search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($category, fn ($q, $v) => $q->where('category', $v))
            ->when($from, fn ($q, $v) => $q->whereDate('expense_date', '>=', $v))
            ->when($to, fn ($q, $v) => $q->whereDate('expense_date', '<=', $v))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total'      => Expense::notTrashed()->count(),
            'this_month' => (float) Expense::notTrashed()->whereMonth('expense_date', now()->month)->sum('amount_afn'),
            'total_afn'  => (float) Expense::notTrashed()->sum('amount_afn'),
        ];

        return Inertia::render('expenses/index', [
            'expenses'   => $expenses,
            'branches'   => Branch::notTrashed()->select('id', 'name', 'code')->get(),
            'categories' => Expense::CATEGORIES,
            'filters'    => [
                'search'    => $search,
                'branch_id' => $branchId,
                'category'  => $category,
                'from'      => $from,
                'to'        => $to,
            ],
            'stats' => $stats,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('expenses/create', [
            'branches'   => Branch::notTrashed()->active()->select('id', 'name', 'code')->get(),
            'categories' => Expense::CATEGORIES,
            'methods'    => Expense::PAYMENT_METHODS,
            'nextNumber' => Expense::generateExpenseNumber(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id'       => ['required', 'exists:branches,id'],
            'title'           => ['required', 'string', 'max:255'],
            'category'        => ['required', 'string', 'max:100'],
            'amount_afn'      => ['required', 'numeric', 'min:0.01'],
            'expense_date'    => ['required', 'date'],
            'payment_method'  => ['required', 'in:cash,card,bank,other'],
            'description'     => ['nullable', 'string'],
        ]);

        $data['expense_number'] = Expense::generateExpenseNumber();
        $data['created_by']     = Auth::id();
        $data['user_id']        = Auth::id();

        $expense = Expense::create($data);

        return redirect()
            ->route('expenses.index')
            ->with('success', "Expense \"{$expense->expense_number}\" created successfully.");
    }

    public function show(Expense $expense): Response
    {
        if ($expense->trash) {
            abort(404);
        }

        $expense->load(['branch:id,name,code', 'creator:id,name,email']);

        return Inertia::render('expenses/show', [
            'expense' => $expense,
        ]);
    }

    public function edit(Expense $expense): Response
    {
        if ($expense->trash) {
            abort(404);
        }

        return Inertia::render('expenses/edit', [
            'expense'    => $expense,
            'branches'   => Branch::notTrashed()->active()->select('id', 'name', 'code')->get(),
            'categories' => Expense::CATEGORIES,
            'methods'    => Expense::PAYMENT_METHODS,
        ]);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $data = $request->validate([
            'branch_id'       => ['required', 'exists:branches,id'],
            'title'           => ['required', 'string', 'max:255'],
            'category'        => ['required', 'string', 'max:100'],
            'amount_afn'      => ['required', 'numeric', 'min:0.01'],
            'expense_date'    => ['required', 'date'],
            'payment_method'  => ['required', 'in:cash,card,bank,other'],
            'description'     => ['nullable', 'string'],
        ]);

        $data['user_id'] = Auth::id();

        $expense->update($data);

        return redirect()
            ->route('expenses.index')
            ->with('success', "Expense \"{$expense->expense_number}\" updated successfully.");
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $number = $expense->expense_number;

        $expense->update(['trash' => true, 'user_id' => Auth::id()]);

        return redirect()->route('expenses.index')
            ->with('success', "Expense \"{$number}\" was moved to trash.");
    }

    public function restore(int $id): RedirectResponse
    {
        $expense = Expense::onlyTrashed()->findOrFail($id);
        $expense->update(['trash' => false, 'user_id' => Auth::id()]);

        return redirect()->route('expenses.index')
            ->with('success', "Expense \"{$expense->expense_number}\" was restored.");
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $expense = Expense::onlyTrashed()->findOrFail($id);
        $number = $expense->expense_number;
        $expense->forceDelete();

        return redirect()->route('expenses.index')
            ->with('success', "Expense \"{$number}\" was permanently deleted.");
    }

    public function trash(): Response
    {
        $expenses = Expense::onlyTrashed()
            ->with(['branch:id,name', 'creator:id,name'])
            ->orderByDesc('updated_at')
            ->paginate(20);

        return Inertia::render('expenses/trash', [
            'expenses' => $expenses,
        ]);
    }
}