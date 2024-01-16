<?php

namespace App\Http\Controllers;

use App\Models\{UserOtp, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class UserOtpController extends Controller
{
    public function generateOTP(Request $request) {
        $new_code = rand(100000, 999999);
        $user = Auth::user();

        if(!$user){
            return response()->json(['status' => false]);
        }

        UserOtp::create([
            'user_id' => $user->id
           ,'phone' => $user->phone
           ,'email' => $user->email
           ,'secret_code_encrypted' => encrypt($new_code)
           ,'trace'=> $new_code
           ,'expiry' => \Carbon\Carbon::now()->addMinute(30)->format('Y-m-d H:i:s')
           ,'ip' => empty( $_SERVER['REMOTE_ADDR'] ) ? '' : $_SERVER['REMOTE_ADDR']
           ,'user_agent' => empty( $_SERVER['HTTP_USER_AGENT'] ) ? '' : $_SERVER['HTTP_USER_AGENT']
        ]);

        self::send_sms( $user, $new_code );
        self::send_email( $user, $new_code );

        return response()->json(['status' => true, 'message' => 'One-Time PIN was sent!']);
    }

    public function send_email($user, $new_code){
        if( empty( $user->email ) ) return false;
        
        Mail::send('emails.otp', [
            'user' => $user, 'code' => $new_code,
        ], function ($message) use ($user) {
            $message->to($user->email)->subject('PlancarePH One-Time PIN');
        });
		
        return true;
    }

    public function send_sms( $user, $new_code ){
        if( empty( $user->phone ) ) return false;
        $message = "Your PlancarePH OTP is {$new_code}. Never share your OTP";
        $senderid = 'PlancarePH';

        // SMS FUNCTION TO SEND
        return true;
    }

    public function verifyOTP($user_id, $code) {
        if( $code ){
            $user_otps = UserOtp::where('user_id', $user_id)
              ->where( 'expiry', '>=', \Carbon\Carbon::now() )
              ->get();
                
            $is_correct_code = false;  
            
            foreach( $user_otps as $item ){
              $ucode = decrypt( $item->secret_code_encrypted );
              if( $ucode == $code ){
                $is_correct_code = true;
                $item->delete();
              }
            }

            return $is_correct_code; // wrong OTP if false otherwise correct
        }
    }
}
