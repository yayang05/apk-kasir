<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    use HasFactory;

    protected $fillable = ['sale_id', 'branch_id', 'user_id', 'return_no', 'reason', 'total', 'refund_method', 'refund_reference', 'returned_at'];

    protected function casts(): array
    {
        return ['total' => 'decimal:2', 'returned_at' => 'datetime'];
    }

    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(SaleReturnItem::class); }
}
