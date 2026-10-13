<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    use HasFactory;

    protected $fillable = ['sale_item_id', 'product_id', 'product_name', 'sku', 'quantity', 'unit_price', 'line_total'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    public function saleReturn(): BelongsTo { return $this->belongsTo(SaleReturn::class); }
    public function saleItem(): BelongsTo { return $this->belongsTo(SaleItem::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
