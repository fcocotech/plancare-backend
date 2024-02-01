<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Models\{User, PasswordResetToken};

class ForgotPasswordController extends Controller
{
    public function forgotPassword(Request $request) {
        $existingUser = User::where('referral_code', $request->user_id)->first();
        if(!$existingUser){
            return response()->json(['status' => true]); 
        }

        $token = Str::random(32);
        $existingToken = DB::table('password_reset_tokens')
            ->where('email', $existingUser->email)
            ->where('user_referral', $request->user_id)
            ->first();

        $result = null;
        if ($existingToken) {
            // Update existing entry instead of adding a new one
            $result = DB::table('password_reset_tokens')
                ->where('email', $existingUser->email)
                ->where('user_referral', $request->user_id)
                ->update([
                    'token' => $token,
                    'created_at' => now(),
                ]);
        } else {
            // Add a new entry
            $result = DB::table('password_reset_tokens')->insert([
                'email' => $existingUser->email,
                'token' => $token,
                'user_referral' => $request->user_id,
                'created_at' => now(),
            ]);
        }

        if($existingUser->email){
            Mail::send('emails.forgot-password', [
                'action_url' => env('FRONTEND_URL').'reset-password/'.$token,
                'name' => $existingUser->name
            ], function ($message) use ($existingUser) {
                $message->to($existingUser->email)->subject('Action Required: Password Reset');
            });
        }

        return response()->json(['status' => true]);
    }

    public function passwordUpdate(Request $request) {
        // verify token
        $token = PasswordResetToken::where('token', $request->token)->first();
        if(!$token){
            return response()->json(['status' => false, 'message' => 'Token not valid!']);
        }

        // user to change password
        $user = User::where('referral_code', $token->user_referral)->first();
        if(!$user){
            return response()->json(['status' => false, 'message' => 'The token provided is not valid!']);
        }

        // do update
        $user->password = Hash::make($request->new_password);
        $user->update();

        return response()->json(['status' => true, 'message' => 'Password update successful.']);
    }
}
