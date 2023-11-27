<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserCommission extends Model
{
     use HasFactory, SoftDeletes;

    protected $fillable = [
        'commission_level',
        'user_id',
        'status'
    ];

    public function commission_level() {
        return $this->hasOne(Commission::class, 'level', 'commission_level');
    }
}