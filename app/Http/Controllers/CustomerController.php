<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::query()->when($request->filled('search'), function ($query) use ($request) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('member_no', 'like', $search)->orWhere('phone', 'like', $search));
        })->withCount('sales')->latest()->paginate(20)->withQueryString();
        return view('customers.index', compact('customers'));
    }

    public function create(): View { return view('customers.create'); }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', compact('customer'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:255'], 'address' => ['nullable', 'string', 'max:255']]);
        Customer::create($validated + ['member_no' => 'MBR-'.now()->format('ymd').'-'.Str::upper(Str::random(5))]);
        return redirect()->route('customers.index')->with('status', 'Pelanggan/member berhasil ditambahkan.');
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $customer->update($validated + ['is_active' => $request->boolean('is_active')]);
        return redirect()->route('customers.index')->with('status', 'Data pelanggan/member berhasil diperbarui.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();
        return redirect()->route('customers.index')->with('status', 'Pelanggan/member berhasil dihapus.');
    }
}
