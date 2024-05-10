<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_id', 
        'description', 
        'payment_method', 
        'amount', 
        'proof_url', 
        'processed_by', 
        'user_id', 
        'status',
        'commission_rate',
        'commission_from',
        'cleared',
        'withdrawable',
        'remarks',
        'product_id'
    ];

    public function processed_by() {
        return $this->hasOne(User::class, 'id', 'processed_by');
    }

    public function user() {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function commission_from() {
        return $this->hasOne(User::class, 'id', 'commission_from');
    }

    public function mode_of_payment() {
        return $this->hasOne(WithdrawalAccountType::class, 'id', 'payment_method');
    }

    public function product() {
        return $this->hasOne(Product::class, 'id', 'product_id');
    }

}
