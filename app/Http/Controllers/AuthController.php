<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\{Role, User};
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
        $credentials = ['referral_code' => $username, 'password' => $password, 'status' => 1];

        // Attempt to authenticate the user
        if (Auth::attempt($credentials)) {
            // If authentication was successful, generate a token for the user
            $user = Auth::user();
            $referral_code = $user->referral_code;
            $token = $user->createToken('API Token')->plainTextToken;
            
            $role = Role::where('id', $user->role_id)->first();
            
            $moduleIds = explode(',', $role->module_ids);
            $modules = Module::select('id', 'name', 'url')->whereIn('id', $moduleIds)->get();

            $login_user = User::select('users.*', 'u.id as parent_id')
            ->leftJoin('users as u', function ($join) use ($user) {
                $join->on('u.reference_code', '=', \DB::raw("'" . $user->referral_code . "'"))
                    ->orWhereNull('u.reference_code');
            })
            ->where('users.id', $user->id)
            ->whereNull('users.deleted_at')
            ->limit(1)
            ->first();
            return response()->json(['status' => true, 'token' => $token, 'user' => $login_user, 'modules' => $modules], 200);
        }
        
        // If authentication failed, check if it's because of pending status
        if (Auth::attempt(['referral_code' => $credentials['referral_code'], 'password' => $credentials['password'], 'status' => 2])) {
            return response()->json(['status' => false, 'message' => 'Account status still pending!'], 200);
        } 

        if (Auth::attempt(['referral_code' => $credentials['referral_code'], 'password' => $credentials['password']])) {
            return response()->json(['status' => false, 'message' => 'Account status inactive!'], 200);
        } 

        // If authentication failed for other reasons, return an error message
        return response()->json(['status' => false, 'message' => 'Invalid credentials'], 200);
    }

}
