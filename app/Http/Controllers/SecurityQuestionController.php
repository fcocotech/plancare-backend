<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SecurityQuestion;

class SecurityQuestionController extends Controller
{
    public function get(Request $request) {
        $securityQuestions = SecurityQuestion::all();
        return response()->json($securityQuestions);
    }
}
