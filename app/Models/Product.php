<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'sku', 'price', 'stock', 'description'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reserveStock(int $quantity): bool
    {
        return self::where('id', $this->id)
            ->where('stock', '>=', $quantity)
            ->update(['stock' => \DB::raw("stock - {$quantity}")]) > 0;
    }

    public function releaseStock(int $quantity): void
    {
        $this->increment('stock', $quantity);
    }
}
