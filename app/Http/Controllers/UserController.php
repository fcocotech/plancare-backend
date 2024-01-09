<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{User, Referral, Commission, UserCommission, Product, ProductPurchase,Transaction};
use Illuminate\Support\Facades\{File, Hash};
// use DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;


class UserController extends Controller
{
    public $debugger = [];

    public function getCardData(Request $request) {
        $sql = "SELECT 
                    (SELECT COUNT(id) FROM users WHERE deleted_at IS NULL AND is_admin = 0 AND status !='3') as total_users,
                    (SELECT COUNT(id) FROM users WHERE deleted_at IS NULL AND is_admin = 0 AND status = '2') as pending_users,
                    (SELECT COUNT(id) FROM users WHERE deleted_at IS NULL AND is_admin = 0 AND status = '1') as total_active
                FROM users u LIMIT 1
        ";
        $totals = DB::select($sql);
        return response()->json([
            'status' => true,
            'totals' => $totals[0],
        ]);
    }

    public function checkCurrentPassword(Request $request){
        $user = Auth::user();
        $inputPassword = $request->password;
        if(Hash::check($inputPassword, $user->password)){
            return response()->json([
                'status' => true,
                'message' => "Current password",
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => "Wrong current password",
            ]);
        }
    }

    public function get(Request $request) {

        $users = array("profile"=>User::select('users.id','users.name','users.email','users.referral_code','users.status','rf.referral_code as referredby')
        ->selectRaw('COALESCE(SUM(tr.amount), 0) as total_commissions')
        ->selectRaw('(SELECT p.name FROM product_purchases pp
                        LEFT JOIN products p ON pp.product_id = p.id
                        WHERE pp.purchased_by = users.id
                        ORDER BY pp.created_at ASC
                        LIMIT 1) as product_name')
        ->selectRaw('(SELECT p.price FROM product_purchases pp
                        LEFT JOIN products p ON pp.product_id = p.id
                        WHERE pp.purchased_by = users.id
                        ORDER BY pp.created_at ASC
                        LIMIT 1) as product_price')
        ->selectRaw('(SELECT pp.id FROM product_purchases pp
                        WHERE pp.purchased_by = users.id
                    ) as product_purchase_id')
        // ->selectRaw('(SELECT r.referral_code FROM users r WHERE r.id = users.parent_referral) sa referred_by')
        ->leftJoin('users as rf', 'rf.id', '=', 'users.parent_referral')
        ->leftJoin('transactions as tr', function ($join) {
            $join->on('tr.user_id', '=', 'users.id')
                ->where('tr.trans_type', '2');
        })
        ->where('users.is_admin', '!=', 1));

        if ($request->filter!=null) {
           $users["profile"]->where('users.status', $request->filter);//gets all active user
        }

        $users["profile"] = $users["profile"]->groupBy('users.id','users.name','users.email','users.referral_code','users.status','rf.referral_code')->get();

        return response()->json(['status' => true, 'users' => $users["profile"], 'params' => $request->filter]);
    }

    public function getMembers(Request $request) {

        $users =User::select('users.id','users.name','users.email','users.referral_code','users.status')
        ->selectRaw('COALESCE(SUM(tr.amount), 0) as total_commissions')
        ->selectRaw('(SELECT p.name FROM product_purchases pp
                        LEFT JOIN products p ON pp.product_id = p.id
                        WHERE pp.purchased_by = users.id
                        ORDER BY pp.created_at ASC
                        LIMIT 1) as product_name')
        ->selectRaw('(SELECT p.price FROM product_purchases pp
                        LEFT JOIN products p ON pp.product_id = p.id
                        WHERE pp.purchased_by = users.id
                        ORDER BY pp.created_at ASC
                        LIMIT 1) as product_price')
        ->selectRaw('(SELECT pp.id FROM product_purchases pp
                        WHERE pp.purchased_by = users.id
                    ) as product_purchase_id')
        ->leftJoin('transactions as tr', function ($join) {
            $join->on('tr.user_id', '=', 'users.id')
                ->where('tr.trans_type', '2');
        })
        ->where('users.is_admin', '!=', 1);

        if ($request->filter!=0 || $request->filter!=null) {
           $users->where('users.status', $request->filter);//gets all active user
        }else{
            $users->where('users.status','!=',3);
        }

        $users= $users->groupBy('users.id','users.name','users.email','users.referral_code','users.status')->get()->members;

        return response()->json(['status' => true, 'users' => $users, 'params' => $request->filter]);
    }

    // public function getRateAndLevel(Request $request, $referrerUserId) {
    //     // Get the referrer's referrer (parent)
    //     $parentReferrer = Referral::where('referred_user_id', $referrerUserId)->first();
    
    //     // If there is no referrer or reached the root (ADMIN), return the rate_id and current level as 1
    //     if (!$parentReferrer) {
    //         $rateId = Commission::where('level', 1)->value('id');
    //         return ['rate_id' => $rateId, 'level' => 1];
    //     }
    
    //     // Recursively traverse up the referral levels
    //     $parentResult = getRateAndLevel($parentReferrer->referrer_user_id);
    
    //     // Increment the level as we move down the recursion
    //     $currentLevel = $parentResult['level'] + 1;
    
    //     // Get the rate_id for the current level
    //     $rateId = Commission::where('level', $currentLevel)->value('id');
    
    //     return response()->json(['rate_id' => $rateId, 'level' => $currentLevel]);
    // }    

    

    public function create(Request $request) { 
        //email can be duplicate
        // $existingUser = User::where('email', $request->email)->get();
        // if(count($existingUser) > 0){
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Email already in use.',
        //     ]);
        // }
        
        
        $product_id = 1;
        $parent_id = 0;
        if($request->referral_code==null){
            $request->referral_code='10011';//assign to admin
        }

        $referrerUser = User::where('referral_code',$request->referral_code)->first();
        
        if($referrerUser==null){
            return response()->json([
                'status' => false,
                'message' => 'We cannot find this referral code. Pls use another code',
            ]);
        }
        //check if referral code is already assigned to 4 slots
        if($referrerUser->id!='1'){
            if($this->findChildCount($referrerUser->id)>=4){
                return response()->json([
                    'status' => false,
                    'message' => 'Slot is already full. Pls use another code',
                ]);
            }  
        }

        if(!$referrerUser){
            return response()->json([
                'status' => false,
                'message' => 'Make sure you have a valid referral code',
            ]);
        }
        // }
        

        // product Valid
        $product = Product::where('id', $product_id)->where('is_active', 1)->first();
        if(!$product){
            return response()->json([
                'status' => false,
                'message' => 'Make sure you have a valid referral code',
            ]);
        }

        $user = new User;
        $user->address = $request->address;
        $user->birthdate = $request->birthdate;
        $user->city = $request->city;
        $user->zipcode = $request->zipcode;
        $user->email            = $request->email;
        $user->idtype           = $request->idtype;
        $user->mobile_number    = $request->mobile_number;
        $user->name             = $request->name;
        $user->nationality      = $request->nationality;
        $user->sec_q1           = $request->sec_q1;
        $user->sec_q1_ans       = $request->sec_q1_ans;
        $user->sec_q2           = $request->sec_q2;
        $user->sec_q2_ans       = $request->sec_q2_ans;
        $user->sec_q3           = $request->sec_q3;
        $user->sec_q3_ans       = $request->sec_q3_ans;
        $user->parent_referral    = $referrerUser->id;//$referrerUser->referral_code;//assign parent referral code
        $user->referral_code    = $this->generateReferralCode($user->id,$product_id,$referrerUser->id);
        $user->status           = 2;//assign as pending
        $user->password = Hash::make($request->password);
        $user->reference_code=0;
        
        if($request->photoprofile == null || $request->photoprofile ==""){
            $request->photoprofile ==  "person.png";
        }
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

        //Add product purchase
        $user->save();
        $productPurchase = new ProductPurchase;
        $productPurchase->product_id    = $product_id; //for now just 1 product
        $productPurchase->purchased_by  = $user->id;
        $productPurchase->referrer_id   = $referrerUser->id ?? 0;

        $productPurchase->save();

        $user->referral_code    = $this->generateReferralCode($user->id,$product_id,$referrerUser->id);
        $user->update();
        $this->sendEmailVerification($user);
        $this->sendWelcomeEmail($user);
        
        return response()->json(['status' => true, 'user' => $user, 'product' => $productPurchase,'debugger' => $this->debugger]);
       
    }

    // public function findChildCount($parentid){
    //     return User::where('parent_referral',$parentid)->where('status',1)->count();
    // }
    public function apifindChildCount(Request $request){
        try{
            $usercount=User::where('parent_referral',$request->id)->where('status',1)->count();
            return response()->json(['status' => true, 'usercount' => $usercount]);
        }catch(Exception $e){
            return response()->json(['status' => false, 'message' => $e->message]);
        }
        
         
    }
    protected function generateReferralCode($userid,$prodid,$parentid){
        $strparentid;
        if($parentid<1){
            $parentid="000";
        }
        elseif($parentid<10){
            $parentid="00" . $parentid;
        }elseif($parentid<100){
            $parentid="0" . $parentid;
        }

        return $prodid . $parentid . $userid;
    }
    // public function ApigenerateReferralCode(Request $request){
    //     $strparentid;
    //     if($request->parentid<1){
    //         $request->parentid="000";
    //     }
    //     elseif($request->parentid<10){
    //         $request->parentid="00" . $request->parentid;
    //     }elseif($request->parentid<100){
    //         $request->parentid="0" . $request->parentid;
    //     }

    //     return $request->prodid . $request->parentid . $request->userid;
    // }
    // public function assignReferrer(User $newUser, $initialReferrerCode = null, $initialReferrerId = 0, $depth = 1) {
    //     $checker = "";
    //     $referrals = null;
    //     $newReferrerCode = '';
    //     // check if the depth exceeds the limit
    //     if ($depth >= 16) {
    //         return 'recursion exceeds limit!'; // exit recursion if depth exceeds the limit
    //     }
    
    //     $referrer = $this->getReferrerNodeCount($initialReferrerCode);

    //     if ($referrer) { // node has slot available
    //         $newUser->referral_code = $referrer->reference_code;
    //         // $newUser->level = $depth;
    //         $newUser->save();
    //         $this->calculateCommissions($referrer->reference_code, $newUser->id);
    //         $this->sendEmailVerification($newUser);
    //         // $referral = new Referral;
    //         // $referral->referrer_code = $initialReferrerCode;
    //         // $referral->referrer_user_id = $initialReferrerId;
    //         // $referral->referred_user_id = $newUser->id;

    //         // $commissions = Commission::where('level', $newUser->level)->first();
    //         // $referral->rate_id = $commissions->id;


    //         // $referral->save();
    //         array_push($this->debugger, ['referrer' => $referrer]);
    //         return $newUser;
    //     } else { // no slot available find another child nodes with available slot
    //         if ($depth >= 2 ) {
    //                 $referrals = User::where('referral_code', $initialReferrerCode)->whereNull('deleted_at')->orderBy('created_at', 'asc')->get();
    //                 array_push($this->debugger, ['referrals' => $referrals]);
    //                 foreach($referrals as $referral){
    //                     $initialReferrerCode = $referral->reference_code;
    //                     $initialReferrerId = $referral->id;
    //                     $referrer = $this->getReferrerNodeCount($initialReferrerCode);
    //                     if($referrer) { 
    //                         $newReferrerCode = $referral->reference_code;
    //                         // add user
    //                         $newUser->referral_code = $newReferrerCode;
    //                         $newUser->save();
                            
    //                         $this->calculateCommissions($newReferrerCode, $newUser->id);
    //                         $this->sendEmailVerification($newUser);
    //                         return $newUser;
    //                     }
    //                 }
    //                 if(!$referrer && $newReferrerCode == ''){
    //                     $this->assignReferrer($newUser, $initialReferrerCode, $initialReferrerId, $depth + 1);
    //                 }
    //         } else {
    //             $this->assignReferrer($newUser, $initialReferrerCode, $initialReferrerId, $depth + 1);
    //         }
    //     }

    //     array_push($this->debugger, ['referrer' => $referrer, 'newReferrerCode' => $newReferrerCode, 'depth' => $depth]);
    // }

    // protected function getReferrerNodeCount($initialReferrerCode) {
    //     return User::where('reference_code', $initialReferrerCode)->whereRaw('(SELECT COUNT(*) FROM users AS u WHERE `u`.`referral_code` = "'.$initialReferrerCode.'" AND u.deleted_at IS NULL) < 4')
    //     ->first();
    // }

    // protected function calculateCommissions($referral_code, $commission_from, $depth = 1) {
    //     if ($depth >= 16) {
    //         return 'recursion exceeds limit!'; // exit recursion if depth exceeds the limit
    //     }

    //     $user = User::where('referral_code', $referral_code)->whereNull('deleted_at')->first();
    //     $this->saveUserCommission($user, $depth, $commission_from);
    //     $referral_code = $user->referral_code;
    //     if(isset($user->referral_code)){
    //         $this->calculateCommissions($referral_code, $id, $depth + 1);
    //     }
    // }

    // protected function saveUserCommission(User $user, $commission_level, $commission_from) {
    //     $user_commission = new UserCommission;
    //     $user_commission->commission_level = $commission_level;
    //     $user_commission->commission_from = $commission_from;
    //     $user_commission->user_id = $user->id;
    //     $user_commission->save();
    // }
    
    public function sendEmailVerification($user) {
        $token = Str::random(32).$user->id;
        Mail::send('emails.verify-email', [
            'action_url' => env('FRONTEND_URL').'verify-email/'.$token,
        ], function ($message) use ($user) {
            $message->to($user->email)->subject('Action Required: Email Verification');
        });
    }

    public function sendWelcomeEmail($user) {
        // $token = Str::random(32).$user->id;
        Mail::send('emails.register-user', [
            'referral_code' => $user->referral_code,
        ], function ($message) use ($user) {
            $message->to($user->email)->subject('Welcome to PlanCare Philippines');
        });
    }

    public function updateUserStatus(Request $request){
        DB::beginTransaction();
        try{
            $user = User::where('id', $request->id)->update(["status"=>3]);
            // $user->status= 3;//account deactivated

            Transaction::where('user_id',$request->id)->delete();
            // $user->delete();
            DB::commit();
            return response()->json(['status' => true, 'user' => $user]);
        }catch(Exception $e){
            DB::rollback();
            return response()->json(['status' => false, 'user' => null]);
        }
        
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

            if($request->has('newpassword') && $request->has('confirmpassword')){
                $user->password = Hash::make($request->newpassword);
            }
            
            $user->update();

            $updatedUser = User::where('id', $user->id)->first();
            return response([
                'status' => true,
                'message' => 'Update Successful',
                'user' => $updatedUser,
            ]);
        } catch(Exception $e){
            return response([
                'status' => false,
                'message' => 'Update Failed',
                'error_message' => $e->getMessage(),
            ]);
        }
        
    }

    public function teams(Request $request){
        $user = Auth::user();
        
        $leader = User::select('id', 'name', 'email', 'profile_url','referral_code')->where('reference_code', $user->referral_code)->first();
        $members = User::select('id', 'name', 'email', 'profile_url','referral_code')->where('parent_referral', $user->id)->where('status', '1')->get();

        foreach($members as $mem){
            $member_child = User::select('id', 'name', 'email', 'profile_url', 'referral_code')->where('parent_referral', $mem->id)->where('status', '1')->get();
            $mem->myteam=$member_child;
        }
        

        return response()->json(['status' => true, 'team' => $leader, 'members' => $members]);
    }

    protected function getInnerMembers($parentid,$index){

        $members = User::select('id', 'name', 'email', 'profile_url','referral_code')->where('parent_referral', $parentid)->where('status', '1')->get();

        if($members!=null){
            foreach($members as $mem){
                $member_child = User::select('id', 'name', 'email', 'profile_url', 'referral_code')->where('parent_referral', $mem->id)->where('status', '1')->get();
                $mem->myteam=$member_child;
            }
            // getInnerMembers($)
        }

        return $members;
    }

    public function team(Request $request, $user_id){        
        $leader = User::select('id', 'name', 'email', 'profile_url', 'referral_code')->where('id', $user_id)->first();
        $members = User::select('id', 'name', 'email', 'profile_url', 'referral_code')->where('parent_referral', $leader->id)->where('status', '1')->get();
        
        $leader->members = $members;
        return response()->json(['status' => true, 'team' => $leader]);
    }

    public function member(Request $request, $user_id) {
        $member = User::where('id', $user_id)->first();
        return response()->json(['status' => true, 'member' => $member]);
    }

    public function emailVerify(Request $request, $token) {
        $id = substr($token, 32);
        $user = User::where('id', $id)->first();
        if(!$user){
            return response()->json(['status' => false, 'message' => 'Token not valid']);
        }

        if($user->is_email_verified == 1 && $user->email_verified_at != null){
            return response()->json(['status' => true, 'message' => 'Email already verified']);
        }

        $user->email_verified_at = Carbon::now();
        $user->is_email_verified = 1;
        $user->save();

        $parent = User::where('reference_code', $user->referral_code)->first();

        Mail::send('emails.sign-up', [
            'action_url' => env('FRONTEND_URL').'login',
            'user_id' => '0001-'.str_pad($parent->id, 4, '0', STR_PAD_LEFT).'-'.str_pad($user->id, 4, '0', STR_PAD_LEFT)
        ], function ($message) use ($user) {
            $message->to($user->email)->subject('Your Email Was Verified');
        });

        return response()->json(['status' => true, 'message' => 'We have verify your email address']);
    }

    public function getId(Request $request, $id) {
        $user = array("user"=>User::select(
            'name','email','birthdate','nationality','address','city','zipcode','mobile_number','referral_code','profile_url','status','parent_referral'
        )->where('id', $id)->first(),"parent"=>null);
        
        $user["parent"]= User::where('id',$user["user"]->parent_referral)->first();;
        return response()->json(['status' => true, 'user' => $user]);
    }
}
