<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_no',
        'branch_id',
        'user_id',
        'customer_id',
        'cash_session_id',
        'subtotal',
        'grand_total',
        'paid_amount',
        'change_amount',
        'payment_method',
        'payment_status',
        'customer_name',
        'customer_phone',
        'order_name',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class)->orderBy('paid_at')->orderBy('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class)->latest('returned_at');
    }

    public function getPaidTotalAttribute(): float
    {
        $total = $this->relationLoaded('payments')
            ? $this->payments->sum(fn (SalePayment $payment) => (float) $payment->amount)
            : $this->payments()->sum('amount');

        return (float) $total;
    }

    public function getBalanceDueAttribute(): float
    {
        return max(0, (float) $this->grand_total - $this->paid_total);
    }
}
