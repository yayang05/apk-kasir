<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::query()
            ->with('category')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search')->trim().'%';
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', $search)
                        ->orWhere('sku', 'like', $search)
                        ->orWhere('barcode', 'like', $search);
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('products.index', compact('products'));
    }

    public function create(): View
    {
        return view('products.create', [
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $product = DB::transaction(function () use ($request) {
            $product = Product::create($this->validated($request));
            $branchIds = Branch::query()->pluck('id');

            $product->branchStocks()->createMany(
                $branchIds->map(fn (int $branchId) => ['branch_id' => $branchId])->all()
            );

            return $product;
        });

        return redirect()->route('products.index')->with('status', "Produk {$product->name} berhasil ditambahkan.");
    }

    public function edit(Product $product): View
    {
        return view('products.edit', [
            'product' => $product,
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->update($this->validated($request, $product));

        return redirect()->route('products.index')->with('status', 'Produk berhasil diperbarui.');
    }

    public function toggleStatus(Product $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        return redirect()->route('products.index')->with('status', 'Status produk berhasil diperbarui.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($product)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products', 'barcode')->ignore($product)],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:30'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'min_stock' => ['sometimes', 'numeric', 'decimal:0,3', 'min:0'],
            'max_stock' => ['sometimes', 'numeric', 'decimal:0,3', 'gte:min_stock'],
        ]);
    }
}
