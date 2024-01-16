<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};

class UserOtp extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $table = 'user_otp';

    protected $fillable = [
        'user_id',
        'phone',
        'email',
        'secret_code_encrypted',
        'trace',
        'expiry',
        'ip',
        'user_agent',
    ];

    
}
