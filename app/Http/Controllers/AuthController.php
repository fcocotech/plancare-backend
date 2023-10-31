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

        // Add the status to the credentials array
        $credentials = ['email' => $username, 'password' => $password, 'status' => 'active'];

        // Attempt to authenticate the user
        if (Auth::attempt($credentials)) {
            // If authentication was successful, generate a token for the user
            $user = Auth::user();
            $token = $user->createToken('API Token')->plainTextToken;

            // Return the token and user details in the response
            return response()->json(['token' => $token, 'user' => $user], 200);
        }
        
        // If authentication failed, check if it's because of pending status
        if (Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            return response()->json(['status' => false, 'message' => 'Account Status still pending!'], 401);
        } 

        // If authentication failed for other reasons, return an error message
        return response()->json(['status' => false, 'message' => 'Invalid credentials'], 401);
    }

}
