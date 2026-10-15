<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = ['store_id', 'variant_id', 'order_id', 'delta', 'reason'];
}
