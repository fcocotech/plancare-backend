<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Login a user and generate a token.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $username = $request->username;
        $password = $request->password;

        if (Auth::attempt(['email' => $username, 'password' => $password])) {
            $user = Auth::user();
            $token = $user->createToken('API Token')->plainTextToken;

            return response()->json(['token' => $token], 200);
        }

        return response()->json(['status' => false, 'message' => 'Invalid credentials'], 401);
    }
}
