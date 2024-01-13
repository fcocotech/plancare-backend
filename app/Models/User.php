<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name', 'email', 'password', 'birthdate', 'nationality', 'address', 'city',
        'zipcode', 'mobile_number', 'idtype', 'idurl', 'referral_code', 'profile_url', 'security_answers', 'is_admin',
        'sec_q1', 'sec_q1_ans', 'sec_q2', 'sec_q2_ans', 'sec_q3', 'sec_q3_ans', 'sec_q4', 'sec_q4_ans', 'sec_q5', 'sec_q5_ans', 'status'
        // 'reference_code',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'security_answers' => 'json'
    ];

    public function roles() {
        return $this->hasOne(Role::class, 'id', 'role_id');
    }

    public function parent() {
        return $this->hasOne(User::class,'id', 'parent_referral')->select('id', 'name','referral_code','cleared');
    }

    public function getSecQ1AnsAttribute($value) { return str_repeat('*', strlen($value)); }
    public function getSecQ2AnsAttribute($value) { return str_repeat('*', strlen($value)); }
    public function getSecQ3AnsAttribute($value) { return str_repeat('*', strlen($value)); }
    public function getSecQ4AnsAttribute($value) { return str_repeat('*', strlen($value)); }
    public function getSecQ5AnsAttribute($value) { return str_repeat('*', strlen($value)); }

    public function members(): HasMany{
        return $this->hasMany(User::class,'parent_referral');
    }
}
