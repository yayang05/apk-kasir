<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['member_no', 'name', 'phone', 'email', 'address', 'loyalty_points', 'is_active'];

    protected function casts(): array { return ['loyalty_points' => 'integer', 'is_active' => 'boolean']; }

    public function sales(): HasMany { return $this->hasMany(Sale::class); }
}
