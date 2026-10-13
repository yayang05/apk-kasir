<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Validation\Rules;

class EmployeeController extends Controller
{
    public function index(): View
    {
        return view('employees.index', [
            'employees' => User::query()
                ->where('is_super_admin', false)
                ->with('branches')
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('employees.create', [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'branch_roles' => ['required', 'array', 'min:1'],
            'branch_roles.*.enabled' => ['nullable', 'boolean'],
            'branch_roles.*.role' => ['required', Rule::in(['manager', 'cashier'])],
        ]);
        $assignments = $this->activeBranchAssignments($validated['branch_roles']);

        DB::transaction(function () use ($validated, $assignments) {
            $employee = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);
            $employee->branches()->sync($assignments);
        });

        return redirect()->route('employees.index')->with('status', 'Pegawai berhasil ditambahkan.');
    }

    public function edit(User $employee): View
    {
        abort_if($employee->isSuperAdmin(), 404);
        $employee->load('branches');

        return view('employees.edit', [
            'employee' => $employee,
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orWhereIn('id', $employee->branches->modelKeys())
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, User $employee): RedirectResponse
    {
        abort_if($employee->isSuperAdmin(), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee)],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'branch_roles' => ['required', 'array', 'min:1'],
            'branch_roles.*.enabled' => ['nullable', 'boolean'],
            'branch_roles.*.role' => ['required', Rule::in(['manager', 'cashier'])],
        ]);
        $assignments = $this->activeBranchAssignments($validated['branch_roles'], $employee);

        DB::transaction(function () use ($validated, $assignments, $employee) {
            $employee->fill([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            if (! empty($validated['password'])) {
                $employee->password = $validated['password'];
            }

            $employee->save();
            $employee->branches()->sync($assignments);
        });

        return redirect()->route('employees.index')->with('status', 'Data pegawai berhasil diperbarui.');
    }

    public function destroy(User $employee): RedirectResponse
    {
        abort_if($employee->isSuperAdmin(), 404);

        $hasHistory = DB::table('sales')->where('user_id', $employee->id)->exists()
            || DB::table('purchases')->where('user_id', $employee->id)->exists()
            || DB::table('cash_sessions')->where('user_id', $employee->id)->exists();

        if ($hasHistory) {
            return redirect()->route('employees.index')->with('status', 'Pegawai tidak dapat dihapus karena sudah memiliki riwayat transaksi. Edit data atau cabut akses cabangnya.');
        }

        DB::transaction(function () use ($employee) {
            $employee->branches()->detach();
            $employee->delete();
        });

        return redirect()->route('employees.index')->with('status', 'Pegawai berhasil dihapus.');
    }

    private function activeBranchAssignments(array $branchRoles, ?User $employee = null): array
    {
        $assignments = collect($branchRoles)
            ->filter(fn (array $assignment) => filter_var($assignment['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->map(fn (array $assignment) => ['role' => $assignment['role']])
            ->all();

        if ($assignments === []) {
            throw ValidationException::withMessages([
                'branch_roles' => 'Pilih setidaknya satu cabang aktif untuk pegawai ini.',
            ]);
        }

        $existingBranchIds = $employee?->branches()->pluck('branches.id')->all() ?? [];
        $validIds = Branch::query()
            ->where(function ($query) use ($existingBranchIds) {
                $query->where('is_active', true)
                    ->when($existingBranchIds, fn ($query) => $query->orWhereIn('id', $existingBranchIds));
            })
            ->whereKey(array_keys($assignments))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if (count($validIds) !== count($assignments)) {
            throw ValidationException::withMessages([
                'branch_roles' => 'Salah satu cabang yang dipilih sudah tidak aktif.',
            ]);
        }

        return $assignments;
    }
}
