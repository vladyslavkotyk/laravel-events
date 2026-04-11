<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'order_id',
        'invoice_number',
        'amount',
        'issued_at',
        'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'issued_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public static function generateNumber(): string
    {
        $latest = static::latest('id')->value('invoice_number');
        $sequence = $latest ? ((int) substr($latest, 4)) + 1 : 1;

        return 'INV-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }
}
