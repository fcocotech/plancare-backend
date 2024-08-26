<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use App\Models\User;
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function findChildCount($parentid){
        return User::where('parent_referral',$parentid)->whereIn('status',[1,5])->count();
    }
}
