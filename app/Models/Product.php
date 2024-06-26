<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'products'; 
    protected $fillable = [
        'name',
        'description',
        'price',
        'is_active',
        'is_shop_active',
        'photo_url',
        'category_id',
    ];

    public function category() {
        return $this->hasOne(ProductCategory::class, 'id', 'category_id');
    }
}
