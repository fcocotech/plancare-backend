<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Models\User;

class ForgotPasswordController extends Controller
{
    public function forgotPassword(Request $request) {
        $request->validate(['email' => 'required|email']);
        $email = $request->email;

        $existingUser = User::where('email', $request->email)->first();
        if(!$existingUser){
            return response()->json(['status' => true]); 
        }

        $token = Str::random(32);
        $existingToken = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        $result = null;
        if ($existingToken) {
            // Update existing entry instead of adding a new one
            $result = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->update([
                    'token' => $token,
                    'created_at' => now(),
                ]);
        } else {
            // Add a new entry
            $result = DB::table('password_reset_tokens')->insert([
                'email' => $email,
                'token' => $token,
                'created_at' => now(),
            ]);
        }

        Mail::send('emails.forgot-password', [
            'action_url' => env('FRONTEND_URL').'reset-password?t='.$token,
            'name' => $existingUser->name
        ], function ($message) use ($email) {
            $message->to($email)->subject('Action Required: Password Reset');
        });

        return response()->json(['status' => true]);
    }
}
