<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\Role;
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
            
            $role = Role::where('id', $user->role_id)->first();
            
            $moduleIds = explode(',', $role->module_ids);
            $modules = Module::select('id', 'name', 'url')->whereIn('id', $moduleIds)->get();
            return response()->json(['status' => true, 'token' => $token, 'user' => $user, 'modules' => $modules], 200);
        }
        
        // If authentication failed, check if it's because of pending status
        if (Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password'], 'status' => 'pending'])) {
            return response()->json(['status' => false, 'message' => 'Account status still pending!'], 200);
        } 

        if (Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            return response()->json(['status' => false, 'message' => 'Account status inactive!'], 200);
        } 

        // If authentication failed for other reasons, return an error message
        return response()->json(['status' => false, 'message' => 'Invalid credentials'], 200);
    }

}
