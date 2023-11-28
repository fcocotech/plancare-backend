<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{User, Referral, Commission, UserCommission};
use Illuminate\Support\Facades\{File, Hash};
use DB;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public $debugger = [];

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
        $users = User::select('users.*')
            ->selectRaw('COALESCE(SUM(tr.amount), 0) as total_commissions')
            ->leftJoin('transactions as tr', function ($join) {
                $join->on('tr.user_id', '=', 'users.id')
                    ->where('tr.payment_method', 'Commissions');
            })
            ->where('users.is_admin', '!=', 1);

        if ($request->filled('filter')) {
            switch ($request->filter) {
                case 'pending':
                    $users->where('users.status', 'pending');
                    break;
                case 'leaders':
                    $users->where('users.status', 'leader');
                    break;
            }
        }

        $users = $users->groupBy('users.id')->get();

        return response()->json(['status' => true, 'users' => $users, 'params' => $request->filter]);
    }

    public function getRateAndLevel(Request $request, $referrerUserId) {
        // Get the referrer's referrer (parent)
        $parentReferrer = Referral::where('referred_user_id', $referrerUserId)->first();
    
        // If there is no referrer or reached the root (ADMIN), return the rate_id and current level as 1
        if (!$parentReferrer) {
            $rateId = Commission::where('level', 1)->value('id');
            return ['rate_id' => $rateId, 'level' => 1];
        }
    
        // Recursively traverse up the referral levels
        $parentResult = getRateAndLevel($parentReferrer->referrer_user_id);
    
        // Increment the level as we move down the recursion
        $currentLevel = $parentResult['level'] + 1;
    
        // Get the rate_id for the current level
        $rateId = Commission::where('level', $currentLevel)->value('id');
    
        return response()->json(['rate_id' => $rateId, 'level' => $currentLevel]);
    }    

    

    public function create(Request $request) { 
        $existingUser = User::where('email', $request->email)->get();
        if(count($existingUser) > 0){
            return response()->json([
                'status' => false,
                'message' => 'Email already in use.',
            ]);
        }

        // referral Valid
        $referrerUser = User::where('reference_code', $request->referral_code)->first();
        if(!$referrerUser){
            return response()->json([
                'status' => false,
                'message' => 'Make sure you have a valid referral code',
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

        $newUser = $this->assignReferrer($user, $request->referral_code, 1); // recursion start here

        return response()->json(['status' => true, 'user' => $newUser, 'debugger' => $this->debugger]);
    }

    public function assignReferrer(User $newUser, $initialReferrerCode = null, $initialReferrerId = 0, $depth = 1) {
        $checker = "";
        $referrals = null;
        $newReferrerCode = '';
        // check if the depth exceeds the limit
        if ($depth >= 16) {
            return 'recursion exceeds limit!'; // exit recursion if depth exceeds the limit
        }
    
        $referrer = $this->getReferrerNodeCount($initialReferrerCode);

        if ($referrer) { // node has slot available
            $newUser->referral_code = $referrer->reference_code;
            // $newUser->level = $depth;
            $newUser->save();
            $this->calculateCommissions($referrer->reference_code, $newUser->id);

            // $referral = new Referral;
            // $referral->referrer_code = $initialReferrerCode;
            // $referral->referrer_user_id = $initialReferrerId;
            // $referral->referred_user_id = $newUser->id;

            // $commissions = Commission::where('level', $newUser->level)->first();
            // $referral->rate_id = $commissions->id;


            // $referral->save();
            array_push($this->debugger, ['referrer' => $referrer]);
            return $newUser;
        } else { // no slot available find another child nodes with available slot
            if ($depth >= 2 ) {
                    $referrals = User::where('referral_code', $initialReferrerCode)->whereNull('deleted_at')->orderBy('created_at', 'asc')->get();
                    array_push($this->debugger, ['referrals' => $referrals]);
                    foreach($referrals as $referral){
                        $initialReferrerCode = $referral->reference_code;
                        $initialReferrerId = $referral->id;
                        $referrer = $this->getReferrerNodeCount($initialReferrerCode);
                        if($referrer) { 
                            $newReferrerCode = $referral->reference_code;
                            // add user
                            $newUser->referral_code = $newReferrerCode;
                            $newUser->save();
                            
                            $this->calculateCommissions($newReferrerCode, $newUser->id);
                            return $newUser;
                        }
                    }
                    if(!$referrer && $newReferrerCode == ''){
                        $this->assignReferrer($newUser, $initialReferrerCode, $initialReferrerId, $depth + 1);
                    }
            } else {
                $this->assignReferrer($newUser, $initialReferrerCode, $initialReferrerId, $depth + 1);
            }
        }

        array_push($this->debugger, ['referrer' => $referrer, 'newReferrerCode' => $newReferrerCode, 'depth' => $depth]);
    }

    protected function getReferrerNodeCount($initialReferrerCode) {
        return User::where('reference_code', $initialReferrerCode)->whereRaw('(SELECT COUNT(*) FROM users AS u WHERE `u`.`referral_code` = "'.$initialReferrerCode.'" AND u.deleted_at IS NULL) < 4')
        ->first();
    }

    protected function calculateCommissions($referral_code, $id, $depth = 1) {
        if ($depth >= 16) {
            return 'recursion exceeds limit!'; // exit recursion if depth exceeds the limit
        }

        $user = User::where('reference_code', $referral_code)->whereNull('deleted_at')->first();
        $this->saveUserCommission($user, $depth, $id);
        $referral_code = $user->referral_code;
        array_push($this->debugger, ['commission_user' => $user->reference_code, 'commission_level' => $depth, 'referral_code' => $referral_code]);
        if(isset($user->referral_code)){
            $this->calculateCommissions($referral_code, $id, $depth + 1);
        }
    }

    protected function saveUserCommission(User $user, $commission_level, $commission_from) {
        $user_commission = new UserCommission;
        $user_commission->commission_level = $commission_level;
        $user_commission->commission_from = $commission_from;
        $user_commission->user_id = $user->id;
        $user_commission->save();
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

    public function team(Request $request, $user_id){        
        $leader = User::select('id', 'name', 'email', 'profile_url', 'reference_code')->where('id', $user_id)->first();
        $members = User::select('id', 'name', 'email', 'profile_url')->where('referral_code', $leader->reference_code)->get();

        $leader->members = $members;
        return response()->json(['status' => true, 'team' => $leader]);
    }

    public function member(Request $request, $user_id) {
        $member = User::where('id', $user_id)->first();
        return response()->json(['status' => true, 'member' => $member]);
    }
}
