<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        return view('branches.index', [
            'branches' => Branch::query()->withCount('users')->orderBy('name')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('branches.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'alpha_dash', 'max:30', 'unique:branches,code'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        DB::transaction(function () use ($validated) {
            $branch = Branch::create($validated);
            $productIds = Product::query()->pluck('id');

            $branch->stocks()->createMany(
                $productIds->map(fn (int $productId) => ['product_id' => $productId])->all()
            );
        });

        return redirect()->route('branches.index')->with('status', 'Cabang berhasil ditambahkan.');
    }

    public function edit(Branch $branch): View
    {
        return view('branches.edit', compact('branch'));
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'alpha_dash', 'max:30', Rule::unique('branches', 'code')->ignore($branch)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $branch->update($validated);

        return redirect()->route('branches.index')->with('status', 'Data cabang berhasil diperbarui.');
    }

    public function toggleStatus(Branch $branch): RedirectResponse
    {
        $branch->update(['is_active' => ! $branch->is_active]);

        return redirect()->route('branches.index')->with('status', 'Status cabang berhasil diperbarui.');
    }
}