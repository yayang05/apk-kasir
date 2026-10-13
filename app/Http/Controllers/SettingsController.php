<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SettingsController extends Controller
{
    private const BACKUP_TABLES = [
        'users',
        'branches',
        'branch_user',
        'categories',
        'products',
        'branch_stocks',
        'stock_movements',
        'suppliers',
        'purchases',
        'purchase_items',
        'cash_sessions',
        'sales',
        'sale_payments',
        'sale_items',
    ];

    public function index(): View
    {
        $isSuperAdmin = auth()->user()->isSuperAdmin();
        $backupFiles = $isSuperAdmin
            ? collect(Storage::disk('local')->files('backups'))
                ->filter(fn (string $path) => str_ends_with($path, '.json'))
                ->sortDesc()
                ->take(10)
                ->map(fn (string $path) => [
                    'name' => basename($path),
                    'size' => Storage::disk('local')->size($path),
                    'modified_at' => Storage::disk('local')->lastModified($path),
                ])
                ->values()
            : collect();

        return view('settings.index', [
            'backupFiles' => $backupFiles,
            'isSuperAdmin' => $isSuperAdmin,
            'counts' => $isSuperAdmin ? [
                'products' => Product::query()->count(),
                'categories' => Category::query()->count(),
                'branches' => Branch::query()->count(),
                'sales' => Sale::query()->count(),
                'purchases' => Purchase::query()->count(),
            ] : null,
        ]);
    }

    public function createBackup(): StreamedResponse
    {
        $tables = collect(self::BACKUP_TABLES)
            ->filter(fn (string $table) => DB::getSchemaBuilder()->hasTable($table))
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->get()->map(fn ($row) => (array) $row)->all()])
            ->all();

        $backup = [
            'format' => 'apk-kasir-backup-v1',
            'created_at' => now()->toIso8601String(),
            'application' => config('app.name'),
            'tables' => $tables,
        ];
        $fileName = 'kasir-backup-'.now()->format('Ymd-His').'-'.str()->lower(str()->random(5)).'.json';
        $path = 'backups/'.$fileName;

        Storage::disk('local')->put(
            $path,
            json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        return Storage::disk('local')->download($path, $fileName, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function downloadBackup(string $fileName): StreamedResponse
    {
        abort_unless(
            basename($fileName) === $fileName && preg_match('/^kasir-backup-[0-9]{8}-[0-9]{6}-[a-z0-9]{5}\.json$/', $fileName),
            404
        );

        $path = 'backups/'.$fileName;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $fileName, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function exportProducts(): StreamedResponse
    {
        $fileName = 'produk-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['sku', 'barcode', 'name', 'category', 'unit', 'cost_price', 'sale_price', 'is_active']);

            Product::query()->with('category')->orderBy('sku')->chunk(500, function ($products) use ($output) {
                foreach ($products as $product) {
                    fputcsv($output, [
                        $this->safeCsvValue($product->sku),
                        $this->safeCsvValue($product->barcode ?? ''),
                        $this->safeCsvValue($product->name),
                        $this->safeCsvValue($product->category->name),
                        $this->safeCsvValue($product->unit),
                        $product->cost_price,
                        $product->sale_price,
                        $product->is_active ? 1 : 0,
                    ]);
                }
            });

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function importProducts(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'products_csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);
        $handle = fopen($validated['products_csv']->getRealPath(), 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['products_csv' => 'File CSV tidak dapat dibaca.']);
        }

        try {
            $headers = fgetcsv($handle, 100000, ',', '"', '\\');
            $headers = is_array($headers)
                ? array_map(fn ($header) => strtolower(trim(ltrim((string) $header, "\xEF\xBB\xBF"))), $headers)
                : [];
            $requiredHeaders = ['sku', 'name', 'category', 'unit', 'cost_price', 'sale_price'];

            if (array_diff($requiredHeaders, $headers) !== []) {
                throw ValidationException::withMessages([
                    'products_csv' => 'Header CSV wajib: sku, name, category, unit, cost_price, sale_price. Header barcode dan is_active bersifat opsional.',
                ]);
            }

            $rows = [];
            $line = 1;

            while (($values = fgetcsv($handle, 100000, ',', '"', '\\')) !== false) {
                $line++;

                if ($values === [null] || count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                if ($line > 2001) {
                    throw ValidationException::withMessages(['products_csv' => 'Maksimal impor adalah 2.000 baris produk.']);
                }

                if (count($values) !== count($headers)) {
                    throw ValidationException::withMessages(['products_csv' => "Jumlah kolom tidak sesuai pada baris {$line}."]);
                }

                $row = array_combine($headers, array_map(fn ($value) => trim((string) $value), $values));
                $validator = Validator::make($row, [
                    'sku' => ['required', 'alpha_dash', 'max:50'],
                    'barcode' => ['nullable', 'max:100'],
                    'name' => ['required', 'string', 'max:255'],
                    'category' => ['required', 'string', 'max:255'],
                    'unit' => ['required', 'string', 'max:30'],
                    'cost_price' => ['required', 'numeric', 'min:0'],
                    'sale_price' => ['required', 'numeric', 'min:0'],
                    'is_active' => ['nullable', Rule::in(['0', '1', 'true', 'false'])],
                ]);

                if ($validator->fails()) {
                    throw ValidationException::withMessages([
                        'products_csv' => "Data tidak valid pada baris {$line}: ".$validator->errors()->first(),
                    ]);
                }

                $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['products_csv' => 'CSV tidak berisi data produk.']);
        }

        $duplicates = collect($rows)->pluck('sku')->duplicates();

        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages(['products_csv' => 'SKU duplikat ditemukan: '.$duplicates->first().'.']);
        }

        try {
            DB::transaction(function () use ($rows) {
                $branchIds = Branch::query()->pluck('id');

                foreach ($rows as $row) {
                    $category = Category::firstOrCreate(['name' => $row['category']]);
                    $product = Product::query()->where('sku', $row['sku'])->first();
                    $barcode = ($row['barcode'] ?? '') !== '' ? $row['barcode'] : null;
                    $barcodeQuery = Product::query()->where('barcode', $barcode);

                    if ($product) {
                        $barcodeQuery->whereKeyNot($product->id);
                    }

                    if ($barcode !== null && $barcodeQuery->exists()) {
                        throw ValidationException::withMessages([
                            'products_csv' => "Barcode {$barcode} sudah digunakan produk lain.",
                        ]);
                    }

                    $product = Product::updateOrCreate(['sku' => $row['sku']], [
                        'category_id' => $category->id,
                        'barcode' => $barcode,
                        'name' => $row['name'],
                        'unit' => $row['unit'],
                        'cost_price' => $row['cost_price'],
                        'sale_price' => $row['sale_price'],
                        'is_active' => filter_var($row['is_active'] ?? '1', FILTER_VALIDATE_BOOLEAN),
                    ]);

                    foreach ($branchIds as $branchId) {
                        BranchStock::firstOrCreate([
                            'branch_id' => $branchId,
                            'product_id' => $product->id,
                        ], ['quantity' => 0]);
                    }
                }
            });
        } catch (QueryException $exception) {
            throw ValidationException::withMessages([
                'products_csv' => 'Impor dibatalkan karena ada SKU/barcode yang bentrok. Periksa data CSV lalu coba lagi.',
            ]);
        }

        return redirect()->route('settings.index')->with('status', count($rows).' produk berhasil diimpor. Stok produk baru dimulai dari nol.');
    }

    public function resetOperationalData(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'confirmation' => ['required', Rule::in(['RESET DATA OPERASIONAL'])],
        ]);

        DB::transaction(function () {
            DB::table('sales')->delete();
            DB::table('purchases')->delete();
            DB::table('stock_movements')->delete();
            DB::table('cash_sessions')->delete();
            DB::table('branch_stocks')->update(['quantity' => 0, 'updated_at' => now()]);
        });

        return redirect()->route('settings.index')->with('status', 'Data transaksi, pembelian, shift, dan riwayat stok dihapus. Akun, cabang, kategori, dan produk tetap tersimpan.');
    }

    private function safeCsvValue(string $value): string
    {
        return preg_match('/^[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}