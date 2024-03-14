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
        'processed_by'
    ];

    public function product() {
        return $this->hasOne(Product::class, 'id', 'product_id');
    }
    public function purchasedby() {
        return $this->hasOne(User::class, 'id', 'purchased_by')->select('id', 'name','email');
    }
    public function processed_by_user() {
        return $this->hasOne(User::class, 'id', 'processed_by')->select('id', 'name');
    }

    public function transaction() {
        return $this->hasOne(Transaction::class, 'id', 'transaction_id');
    }

    public function referrer_user() {
        return $this->hasOne(User::class, 'id', 'referrer_id');
    }

}
