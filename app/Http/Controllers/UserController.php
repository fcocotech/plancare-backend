<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\{File, Hash};
use DB;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function getCardData(Request $request) {
        $sql = "SELECT 
                    (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND is_admin = 0) as total_users,
                    (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND is_admin = 0 AND status = 'pending') as pending_users,
                    (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND is_admin = 0 AND status = 'leader') as total_leaders
                FROM users u LIMIT 1
        ";
        $totals = DB::select($sql);
        return response()->json([
            'status' => true,
            'totals' => $totals[0],
        ]);
    }

    public function get(Request $request) {
        $users = User::where('is_admin', '!=', 1);

        if($request->has('filter') && $request->filter == 'pending'){
            $users = $users->where('status', 'pending');
        }
        if($request->has('filter') && $request->filter == 'leaders'){
            $users = $users->where('status', 'leader');
        }

        $users = $users->get();

        return response()->json(['status' => true, 'users' => $users, 'params' => $request->filter]);
    }

    public function create(Request $request) { 
        $existingUser = User::where('email', $request->email)->get();
        if(count($existingUser) > 0){
            return response()->json([
                'status' => false,
                'message' => 'Email already in use.',
            ]);
        }

        $user = new User;
        $user->address          = $request->address;
        $user->birthdate        = $request->birthdate;
        $user->city             = $request->city;
        $user->email            = $request->email;
        $user->idtype           = $request->idtype;
        $user->mobile_number    = $request->mobile_number;
        $user->name             = $request->name;
        $user->nationality      = $request->nationality;
        $user->zipcode          = $request->zipcode;
        $user->sec_q1           = $request->sec_q1;
        $user->sec_q2           = $request->sec_q2;
        $user->sec_q3           = $request->sec_q3;
        $user->sec_q4           = $request->sec_q4;
        $user->sec_q5           = $request->sec_q5;
        $user->sec_q1_ans       = $request->sec_q1_ans;
        $user->sec_q2_ans       = $request->sec_q2_ans;
        $user->sec_q3_ans       = $request->sec_q3_ans;
        $user->sec_q4_ans       = $request->sec_q4_ans;
        $user->sec_q5_ans       = $request->sec_q5_ans;
        $user->referral_code    = $request->referral_code;
        $user->status           = 'pending';
        $user->password = Hash::make($request->password);

        $user->reference_code   = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), 0, 8);

        $profile_image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $request->photoprofile));
        $profile_path = storage_path('app/public/images/profiles/');
        if(!File::isDirectory($profile_path)){
            File::makeDirectory($profile_path, 0777, true, true);
        }
        $profile_name = time().'_'.$user->id.'_profile.png';
        file_put_contents($profile_path.$profile_name, $profile_image);
        $user->profile_url = env('APP_URL', '') . '/storage/images/profiles/'.$profile_name;

        $id_image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $request->photoid));
        $id_path = storage_path('app/public/images/ids/');
        if(!File::isDirectory($id_path)){
            File::makeDirectory($id_path, 0777, true, true);
        }
        $id_name = time().'_'.$user->id.'_id.png';
        file_put_contents($id_path.$id_name, $id_image);
        $user->idurl = env('APP_URL', '') . '/storage/images/ids/'.$id_name;

        $user->save();
        return response()->json(['status' => true, 'user' => $user]);
    }

    public function update(Request $request, $user_id) {
        try{
            $user = User::where('id', $user_id)->first();
            $user->address          = $request->address;
            $user->birthdate        = $request->birthdate;
            $user->city             = $request->city;
            $user->email            = $request->email;
            $user->idtype           = $request->idtype;
            $user->mobile_number    = $request->mobile_number;
            $user->name             = $request->name;
            $user->nationality      = $request->nationality;
            $user->zipcode          = $request->zipcode;
            $user->sec_q1           = $request->sec_q1;
            $user->sec_q2           = $request->sec_q2;
            $user->sec_q3           = $request->sec_q3;
            $user->sec_q4           = $request->sec_q4;
            $user->sec_q5           = $request->sec_q5;
            if (!preg_match('/\*/', $request->sec_q1_ans)) {
                $user->sec_q1_ans = $request->sec_q1_ans;
            }
            if (!preg_match('/\*/', $request->sec_q2_ans)) {
                $user->sec_q2_ans = $request->sec_q2_ans;
            }
            if (!preg_match('/\*/', $request->sec_q3_ans)) {
                $user->sec_q3_ans = $request->sec_q3_ans;
            }
            if (!preg_match('/\*/', $request->sec_q4_ans)) {
                $user->sec_q4_ans = $request->sec_q4_ans;
            }
            if (!preg_match('/\*/', $request->sec_q5_ans)) {
                $user->sec_q5_ans = $request->sec_q5_ans;
            }
            
            if($user->profile_url != $request->photoprofile) {
                $profile_image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $request->photoprofile));
                $profile_path = storage_path('app/public/images/profiles/');
                if(!File::isDirectory($profile_path)){
                    File::makeDirectory($profile_path, 0777, true, true);
                }
                $profile_name = time().'_'.$user->id.'_profile.png';
                file_put_contents($profile_path.$profile_name, $profile_image);
                $user->profile_url = env('APP_URL', '') . '/storage/images/profiles/'.$profile_name;
            }

            if($user->idurl != $request->photoid) {
                $id_image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $request->photoid));
                $id_path = storage_path('app/public/images/ids/');
                if(!File::isDirectory($id_path)){
                    File::makeDirectory($id_path, 0777, true, true);
                }
                $id_name = time().'_'.$user->id.'_id.png';
                file_put_contents($id_path.$id_name, $id_image);
                $user->idurl = env('APP_URL', '') . '/storage/images/ids/'.$id_name;
            }
            
            $user->update();

            $updatedUser = User::where('id', $user->id)->first();
            return response([
                'status' => true,
                'message' => 'Update Successful',
                'user' => $updatedUser,
            ]);
        } catch(\Exception $e){
            return response([
                'status' => false,
                'message' => 'Update Failed',
                'error_message' => $e->getMessage(),
            ]);
        }
        
    }

    public function teams(Request $request){
        $user = Auth::user();
        
        $leader = User::select('id', 'name', 'email', 'profile_url')->where('reference_code', $user->referral_code)->first();
        $members = User::select('id', 'name', 'email', 'profile_url')->where('referral_code', $user->reference_code)->get();

        return response()->json(['status' => true, 'team' => $leader, 'members' => $members]);
    }

    public function member(Request $request, $user_id) {
        $member = User::where('id', $user_id)->first();
        return response()->json(['status' => true, 'member' => $member]);
    }
}
