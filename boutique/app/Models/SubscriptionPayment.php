<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model
{
    protected $fillable = ['plan', 'months', 'amount', 'method', 'reference', 'period_start', 'period_end'];

    protected function casts(): array
    {
        return ['period_start' => 'datetime', 'period_end' => 'datetime', 'amount' => 'integer', 'months' => 'integer'];
    }
}
