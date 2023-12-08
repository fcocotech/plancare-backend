<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductPurchase extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'product_purchases'; 
    protected $fillable = [
        'product_id',
        'purchased_by',
        'referrer_id',
    ];
}
