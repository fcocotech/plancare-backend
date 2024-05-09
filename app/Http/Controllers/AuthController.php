<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\{Role, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{

    protected $maxAttempts;
    protected $decaySeconds;
    public function __construct()
    {
        $this->maxAttempts = env('LOGIN_MAX_ATTEMPT', 3);
        $this->decaySeconds = env('LOGIN_LOCKTIME_SECONDS', 300);
    }

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
        $credentials = ['referral_code' => $username, 'password' => $password];

        // Attempt to authenticate the user
        if (Auth::attempt($credentials) && RateLimiter::availableIn('lock-username:' . $request->username) < 1) {
            // If authentication was successful, generate a token for the user
            $user = Auth::user();
            $referral_code = $user->referral_code;
            $token = $user->createToken('API Token')->plainTextToken;
            
            RateLimiter::clear('attempt-username:' . $request->username);
            RateLimiter::clear('lock-username:' . $request->username);

            $role = Role::where('id', $user->role_id)->first();
            
            $moduleIds = explode(',', $role->module_ids);
            $modules = Module::select('id', 'name', 'url')->whereIn('id', $moduleIds)->get();

            $login_user = User::select('users.*', 'u.id as parent_id')
            ->leftJoin('users as u', function ($join) use ($user) {
                $join->on('u.id', '=', \DB::raw("'" . $user->parent_referral . "'"))
                    ->orWhereNull('u.parent_referral');
            })
            ->where('users.id', $user->id)
            ->whereNull('users.deleted_at')
            ->limit(1)
            ->first();
            return response()->json(['status' => true, 'token' => $token, 'user' => $login_user, 'modules' => $modules], 200);
        } else {
            $isLocked = false;
            $seconds = 0;

            if (RateLimiter::availableIn('lock-username:' . $request->username) < 1) {
                // hit attemp-username to count the attempts
                $hit = RateLimiter::hit('attempt-username:' . $request->username, $this->decaySeconds);

                // check if hit is equal to maxAttempts : default 3;
                if ($hit >= $this->maxAttempts) {
                    RateLimiter::hit('lock-username:' . $request->username, $this->decaySeconds); // hit the lock-username
                    $isLocked = RateLimiter::tooManyAttempts('lock-username:' . $request->username, 1); // run lock-username timer
                    $seconds = $isLocked ? RateLimiter::availableIn('lock-username:' . $request->username) : 0; // lock-username seconds left
                }
            } else { // user is lock at this point
                $isLocked = (RateLimiter::availableIn('lock-username:' . $request->username) > 0) ? true : false; // check if lock-username is available otherwise false
                $seconds = RateLimiter::availableIn('lock-username:' . $request->username);
            }

            $additional_message = $isLocked ? "Account Locked" : "";
            $response_message = $isLocked ? "Account is locked, try logging in after " . $seconds . " seconds. " : "Failed to authenticate user.";

            return response([
                'status' => false,
                'message' => $response_message,
                'isLocked' => $isLocked,
                'locktime' => $seconds,
            ]);
        }
         
        // If authentication failed, check if it's because of pending status
        // if (Auth::attempt(['referral_code' => $credentials['referral_code'], 'password' => $credentials['password'], 'status' => 2])) {
        //     return response()->json(['status' => false, 'message' => 'Account status still pending!'], 200);
        // } 

        if (Auth::attempt(['referral_code' => $credentials['referral_code'], 'password' => $credentials['password']])) {
            return response()->json(['status' => false, 'message' => 'Account status inactive!'], 200);
        }
        
        // Increment login attempts if authentication fails
        // RateLimiter::hit($this->throttleKey($request), $decaySeconds);

        // If authentication failed for other reasons, return an error message
        return response()->json(['status' => false, 'message' => 'Invalid credentials'], 200);
    }
}
