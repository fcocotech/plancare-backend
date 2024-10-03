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

    protected function getUsersQuery($category_id, $filter = null, $approval = false) {
        $users = User::with(['members', 'productPurchases.product.category'])
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.referral_code',
                'users.status',
                'users.role_id',
                'rf.name as referredbyname',
                'rf.referral_code as referredby',
                'users.cleared'
            )
            ->selectRaw('COALESCE(SUM(tr.amount), 0) as total_commissions')
            ->selectRaw('(SELECT p.name FROM product_purchases pp
                            LEFT JOIN products p ON pp.product_id = p.id
                            WHERE pp.purchased_by = users.id AND pp.purchase_type=1
                            ORDER BY pp.created_at ASC
                            LIMIT 1) as product_name')
            ->selectRaw('(SELECT p.price FROM product_purchases pp
                            LEFT JOIN products p ON pp.product_id = p.id
                            WHERE pp.purchased_by = users.id AND pp.purchase_type=1
                            ORDER BY pp.created_at ASC
                            LIMIT 1) as product_price')
            ->selectRaw('(SELECT pp.id FROM product_purchases pp
                            WHERE pp.purchased_by = users.id AND pp.purchase_type=1
                        ) as product_purchase_id')
            ->leftJoin('users as rf', 'rf.id', '=', 'users.parent_referral')
            ->leftJoin('transactions as tr', function ($join) {
                $join->on('tr.user_id', '=', 'users.id')
                    ->where('tr.trans_type', '2')
                    ->whereNull('tr.deleted_at');
            })
            ->where('users.is_admin', '!=', 1)
            ->where('users.role_id', '!=', 3);
    
        // Apply status filtering if specified
        if ($filter != null) {
            $users->where('users.status', $filter);
        }
    
        // Apply proof_url filtering if specified
        if ($approval != null) {
            $users->whereNotNull('users.proof_url');
        } else {
            $users->whereNull('users.proof_url');
        }
    
        // Apply category filtering if specified
        $categoryId = $category_id;
        if ($categoryId != 0) {
            $users->whereHas('productPurchases.product.category', function ($query) use ($categoryId) {
                $query->where('id', $categoryId);
            });
        }
    
        return $users->groupBy(
            'users.id',
            'users.name',
            'users.email',
            'users.referral_code',
            'users.status',
            'users.role_id',
            'rf.referral_code',
            'rf.name',
            'users.cleared'
        );
    }

    protected function getUsersQuery2($category_id, $filter = null, $approval = false) {
        $users = User::with(['members', 'productPurchases.product.category'])
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.referral_code',
                'users.status',
                'users.role_id',
                'rf.name as referredbyname',
                'rf.referral_code as referredby',
                'users.cleared'
            )
            ->selectRaw('(SELECT p.name FROM product_purchases pp
                            LEFT JOIN products p ON pp.product_id = p.id
                            WHERE pp.purchased_by = users.id AND pp.purchase_type=1
                            ORDER BY pp.created_at ASC
                            LIMIT 1) as product_name')
            ->selectRaw('(SELECT p.price FROM product_purchases pp
                            LEFT JOIN products p ON pp.product_id = p.id
                            WHERE pp.purchased_by = users.id AND pp.purchase_type=1
                            ORDER BY pp.created_at ASC
                            LIMIT 1) as product_price')
            ->selectRaw('(SELECT pp.id FROM product_purchases pp
                            WHERE pp.purchased_by = users.id AND pp.purchase_type=1
                        ) as product_purchase_id')
            ->leftJoin('users as rf', 'rf.id', '=', 'users.parent_referral')
            ->leftJoin('transactions as tr', function ($join) {
                $join->on('tr.user_id', '=', 'users.id')
                    ->where('tr.trans_type', '2')
                    ->whereNull('tr.deleted_at');
            })
            ->where('users.is_admin', '!=', 1)
            ->where('users.role_id', '!=', 3);
    
        // Apply status filtering if specified
        // if ($filter != null) {
        //     $users->where('users.status', $filter);
        // }
    
        // Apply proof_url filtering if specified
        // if ($approval != null) {
        //     $users->whereNotNull('users.proof_url');
        // } else {
        //     $users->whereNull('users.proof_url');
        // }
    
        // Apply category filtering if specified
        $categoryId = $category_id;
        if ($categoryId != 0) {
            $users->whereHas('productPurchases.product.category', function ($query) use ($categoryId) {
                $query->where('id', $categoryId);
            });
        }
        $users = DB::select('SELECT u.name,u.email,u.reference_code,u.parent_referral,u.cleared,p.name,p.price FROM `users` as u 
join product_purchases as pp on u.id=pp.purchased_by 
join products as p on p.id=pp.product_id 
where u.status in (1,2) and isnull(u.deleted_at) and isnull(pp.deleted_at)and isnull(p.deleted_at) and pp.purchase_type=1 and u.is_admin<>1 and u.role_id<>3",)
        return $users;
        
    }

    public function getCardData(Request $request) {
        $totalUsersQuery = $this->getUsersQuery($request->category_id);
    
        $totalPendingUsersQuery = $this->getUsersQuery($request->category_id, 2);
        // $totalPendingUsersQuery->where('users.status', 2)
        //                        ->whereNull('users.proof_url');
    
        $totalPendingApprovalUsersQuery = $this->getUsersQuery($request->category_id, 2, true);
        // $totalPendingApprovalUsersQuery->where('users.status', 2)
        //                                ->whereNotNull('users.proof_url');
    
        $totalActiveUsersQuery = $this->getUsersQuery($request->category_id, 1);
        // $totalActiveUsersQuery->where('users.status', 1);
    
        // Get the counts for each query
        $totals = (object) [
            'total_users' => count($totalUsersQuery->get()),
            'pending_users' => count($totalPendingUsersQuery->get()),
            'pending_approval' => count($totalPendingApprovalUsersQuery->get()),
            'total_active' => count($totalActiveUsersQuery->get()),
        ];
    
        return response()->json([
            'status' => true,
            'totals' => $totals,
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
        $users = User::with(['members', 'productPurchases.product.category'])
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.referral_code',
                'users.status',
                'users.role_id',
                'rf.name as referredbyname',
                'rf.referral_code as referredby',
                'users.cleared'
            )
            ->selectRaw('COALESCE(SUM(tr.amount), 0) as total_commissions')
            ->selectRaw('(SELECT p.name FROM product_purchases pp
                            LEFT JOIN products p ON pp.product_id = p.id
                            WHERE pp.purchased_by = users.id AND pp.purchase_type=1
                            ORDER BY pp.created_at ASC
                            LIMIT 1) as product_name')
            ->selectRaw('(SELECT p.price FROM product_purchases pp
                            LEFT JOIN products p ON pp.product_id = p.id
                            WHERE pp.purchased_by = users.id AND pp.purchase_type=1
                            ORDER BY pp.created_at ASC
                            LIMIT 1) as product_price')
            ->selectRaw('(SELECT pp.id FROM product_purchases pp
                            WHERE pp.purchased_by = users.id AND pp.purchase_type=1
                        ) as product_purchase_id')
            ->leftJoin('users as rf', 'rf.id', '=', 'users.parent_referral')
            ->leftJoin('transactions as tr', function ($join) {
                $join->on('tr.user_id', '=', 'users.id')
                    ->where('tr.trans_type', '2')->whereNull('tr.deleted_at');
            })
            ->where('users.is_admin', '!=', 1)
            ->where('users.role_id', '!=', 3);
    
        if ($request->filter != null) {
            $users->where('users.status', $request->filter);
        }
    
        if ($request->approval != null) {
            $users->whereNotNull('users.proof_url');
        } else {
            $users->whereNull('users.proof_url');
        }
    
        $categoryId = $request->category_id;
    
        if ($categoryId != 0) {
            $users->whereHas('productPurchases.product.category', function ($query) use ($categoryId) {
                $query->where('id', $categoryId);
            });
        }
    
        $users = $users->groupBy(
            'users.id',
            'users.name',
            'users.email',
            'users.referral_code',
            'users.status',
            'users.role_id',
            'rf.referral_code',
            'rf.name',
            'users.cleared'
        )->get();
    
        return response()->json(['status' => true, 'users' => $users, 'params' => $request->filter]);
    }    

    public function getInfluencers(Request $request) {
        $users = array("profile"=>
            User::with(['members','productPurchases.product.category'])->select('users.id','users.name','users.email','users.referral_code','users.status','users.role_id','rf.name as referredbyname','rf.referral_code as referredby','users.cleared')
            ->selectRaw('COALESCE(SUM(tr.amount), 0) as total_commissions')

            ->leftJoin('users as rf', 'rf.id', '=', 'users.parent_referral')
            ->leftJoin('transactions as tr', function ($join) {
                $join->on('tr.user_id', '=', 'users.id')
                    ->where('tr.trans_type', '2')->whereNull('tr.deleted_at');
            })
            ->where('users.is_admin', '!=', 1)
            ->where('users.role_id', '=', 3)
        );

        $categoryId = $request->category_id;

        if ($categoryId != 0) {
            $users["profile"]->whereHas('productPurchases.product.category', function ($query) use ($categoryId) {
                $query->where('id', $categoryId);
            });
        }

        $users["profile"] = $users["profile"]->groupBy('users.id','users.name','users.email','users.referral_code','users.status','users.role_id','rf.referral_code','rf.name','users.cleared')->get();

        return response()->json(['status' => true, 'users' => $users["profile"]]);
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

    

    public function createInfluencer(Request $request) {
        $product_id = $request->product;
        $parent_id = '10011'; // assigned to admin

        $referrerUser = User::where('referral_code', $parent_id)->orWhere('id', 1)->where('status', 1)->first();

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
        $user->parent_referral  = $referrerUser->id; //$referrerUser->referral_code;//assign parent referral code
        $user->referral_code    = $this->generateReferralCode($user->id, $product_id, $referrerUser->id);
        $user->status           = 2; //assign as pending
        $user->password         = Hash::make($request->password);
        $user->reference_code   = 0;
        $user->cleared          = false;
        $user->role_id          = 3; // Influencer role
        $user->product_id       = $request->product;
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

        $proof_image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $request->proofImage));
        $proof_path = storage_path('app/public/images/proof/');
        if(!File::isDirectory($proof_path)){
            File::makeDirectory($proof_path, 0777, true, true);
        }
        $proof_name = time().'_'.$user->id.'_proof.png';
        file_put_contents($proof_path.$proof_name, $proof_image);
        $user->proof_url = env('APP_URL', '') . '/storage/images/proof/'.$proof_name;

        
        //Add product purchase
        $user->save();
        $productPurchase                = new ProductPurchase;
        $productPurchase->product_id    = $product_id; //for now just 1 product
        $productPurchase->purchased_by  = $user->id;
        $productPurchase->referrer_id   = $referrerUser->id ?? 0;

        $productPurchase->save();

        $user->referral_code = $this->generateReferralCode($user->id, $product_id, $referrerUser->id);
        $user->update();
        $this->sendEmailVerification($user);
        $this->sendWelcomeEmail($user);
        
        return response()->json(['status' => true, 'user' => $user, 'product' => $productPurchase]);
    }

    public function create(Request $request) { 
        //email can be duplicate
        // $existingUser = User::where('email', $request->email)->get();
        // if(count($existingUser) > 0){
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Email already in use.',
        //     ]);
        // }
        
        
        // $product_id = 1;
        $product_id = $request->product;
        $parent_id = 0;
        if($request->referral_code==null){
            $request->referral_code='10011';//assign to admin
        }

        $referrerUser = User::where('referral_code',$request->referral_code)->where('status',1)->first();
        
        if($referrerUser==null){
            return response()->json([
                'status' => false,
                'message' => 'We cannot find this referral code. Pls use another code',
            ]);
        }
        //check if referral code is already assigned to 4 slots
        // if($referrerUser->id!='1'){
        //     if($this->findChildCount($referrerUser->id)>=4){
        //         return response()->json([
        //             'status' => false,
        //             'message' => 'Slot is already full. Pls use another code',
        //         ]);
        //     }  
        // }

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
        $user->parent_referral  = $referrerUser->id;//$referrerUser->referral_code;//assign parent referral code
        $user->referral_code    = $this->generateReferralCode($user->id,$product_id,$referrerUser->id);
        $user->status           = 2;//assign as pending
        $user->product_id       = $request->product;
        $user->password = Hash::make($request->password);
        $user->reference_code=0;
        $user->cleared = false;
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

        $proof_image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $request->proofImage));
        $proof_path = storage_path('app/public/images/proof/');
        if(!File::isDirectory($proof_path)){
            File::makeDirectory($proof_path, 0777, true, true);
        }
        $proof_name = time().'_'.$user->id.'_proof.png';
        file_put_contents($proof_path.$proof_name, $proof_image);
        $user->proof_url = env('APP_URL', '') . '/storage/images/proof/'.$proof_name;

        //Add product purchase
        $user->save();
        $productPurchase = new ProductPurchase;
        $productPurchase->product_id    = $product_id; //for now just 1 product
        $productPurchase->purchased_by  = $user->id;
        $productPurchase->referrer_id   = $referrerUser->id ?? 0;

        $productPurchase->save();

        $user->referral_code    = $this->generateReferralCode($user->id,$product_id,$referrerUser->id);
        $user->update();
        
        $product = Product::with(['category'])->where('id', $product_id)->first();

        $initial_commission_rate = 0.0;
        if ($product->price == 599) {
            $initial_commission_rate = 0.25;
        } elseif ($product->price == 1199) {
            $initial_commission_rate = 0.28;
        } elseif ($product->price == 3000) {
            $initial_commission_rate = 0.30;
        }

        TransactionController::assignCommissionV2($user, $user->id, 1, $initial_commission_rate, $product->price, $product->id);
        
        $this->sendEmailVerification($user);
        $this->sendWelcomeEmail($user);

        return response()->json(['status' => true, 'user' => $user, 'product' => $productPurchase]);
       
    }

    // public function findChildCount($parentid){
    //     return User::where('parent_referral',$parentid)->where('status',1)->count();
    // }
    public function apifindChildCount(Request $request){
        return ['usercount' => User::where('parent_referral',$request->id)->where('status',1)->count()];
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

    public function approveInfluencer(Request $request){
        DB::beginTransaction();
        try{
            $user = User::where('id', $request->id)->first();
            $user->update(["status"=>1]);
            DB::commit();
            return response()->json(['status' => true]);
        }catch(Exception $e){
            DB::rollback();
            return response()->json(['status' => false]);
        }
    }
    public function updateUserStatus(Request $request){
        DB::beginTransaction();
        try{
            $user = User::where('id', $request->id)->first();
            
            if($user->status==1){
                $user->update(["status"=>2]);
            }elseif($user->status==2){
                $user->delete();
            }else{
                $user->delete();
            }
            
            Transaction::where('commission_from',$user->id)->where("trans_type",2)->update(['cleared'=>0,'withdrawable'=>0]);
            Transaction::where('commission_from',$user->id)->where("trans_type",2)->delete();
        
            if($user->role_id!=3){//if not an influencer
                $parent=User::find($user->parent_referral);
            
                if($parent!=null){
                    // if($this->findChildCount($parent->id)<3){
                            $parent->cleared =0;
                            $parent->update();
                            // print("clear".$parent->id);
                            $members = User::where('parent_referral',$parent->id)->where('status',1)->get(['id']);
                            foreach($members as $mem){
                                // print("revert".$mem->id);
                                $this->revertParentTransaction($parent->id,$mem->id);
                             }
                        
                    // }
                }
            }
           
            DB::commit();
            return response()->json(['status' => true, 'user' => null]);
        }catch(Exception $e){
            DB::rollback();
            return response()->json(['status' => false, 'user' => null]);
        }
        
    }

    protected function revertParentTransaction($userid,$memberid){
        DB::beginTransaction();
        try{
        
            $user = User::find($userid);
            Transaction::where('user_id',$user->id)->where('commission_from',$memberid)->where("trans_type",2)->update(['withdrawable'=>0]);
            
            if($user->parent_referral !=1){
                $this->revertParentTransaction($user->parent_referral,$memberid);
            }
            DB::commit();
            return true;
        }catch(Exception $e){
            DB::rollback();
            return false;
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
            $user->proof_method     = $request->proof_method;
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

            if($user->proof_url != $request->proofImage) {
                $proof_image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $request->proofImage));
                $proof_path = storage_path('app/public/images/proof/');
                if(!File::isDirectory($proof_path)){
                    File::makeDirectory($proof_path, 0777, true, true);
                }
                $proof_name = time().'_'.$user->id.'_proof.png';
                file_put_contents($proof_path.$proof_name, $proof_image);
                $user->proof_url = env('APP_URL', '') . '/storage/images/proof/'.$proof_name;
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
        
        $categoryId = $request->category_id;
    
        $leader = User::select('id', 'name', 'email', 'profile_url','referral_code','status')->where('referral_code', $user->referral_code)->first();
        $members=array("members"=>[],"count"=>0);
        $members=$this->getInnerMembers($leader->id,$members,$categoryId);
        
        $pendingMembers = User::where('status', 2)->count();

        return response()->json(['status' => true, 'team' => $leader, 'members' => $members["members"],'count'=>$members["count"],'pending_members'=>$pendingMembers]);
    }

    public function teamsCount(Request $request){
        
    }
    
    protected function getInnerMembers($parentid,$innermembers,$categoryId=0){

        // $members = User::select('id', 'name', 'email', 'profile_url','referral_code','status')->where('parent_referral', $parentid)->where('status', '1')->get();
        $membersQuery = User::select('id', 'name', 'email', 'profile_url','referral_code','status')->with(['productPurchases.product.category'])->where('parent_referral', $parentid);

        if ($categoryId != 0) {
            $membersQuery->whereHas('productPurchases.product.category', function ($query) use ($categoryId) {
                $query->where('id', $categoryId);
            });
        }
        $members = $membersQuery->get();

        if($members!=null){
            
            if($members!=null){
                foreach($members as $mem){
                    $membersQuery = User::select('id', 'name', 'email', 'profile_url', 'referral_code')->with(['productPurchases.product.category'])->where('parent_referral', $mem->id)->where('status', '1');

                    if ($categoryId != 0) {
                        $membersQuery->whereHas('productPurchases.product.category', function ($query) use ($categoryId) {
                            $query->where('id', $categoryId);
                        });
                    }
                    $member_child = $membersQuery->get();

                    // $member_child = User::select('id', 'name', 'email', 'profile_url', 'referral_code')->with(['productPurchases.product.category'])->where('parent_referral', $mem->id)->where('status', '1')->get();
                    if($member_child!=null){
                        array_push($innermembers["members"],$mem);
                        $innermembers["count"] += 1;
                        $innermembers=$this->getInnerMembers($mem->id,$innermembers,$categoryId);
                    }
                }
            }
        }
        return $innermembers;
    }

    public function team(Request $request, $user_id){        
        // $leader = User::select('id', 'name', 'email', 'profile_url', 'referral_code', 'cleared', 'role_id')->where('id', $user_id)->first();
        $categoryId = $request->category_id;

        $leaderQuery = User::select('id', 'name', 'email', 'profile_url', 'referral_code', 'cleared', 'role_id','status')->with(['productPurchases.product.category'])->where('id', $user_id);
        // if ($categoryId != 0) {
        //     $leaderQuery->whereHas('productPurchases.product.category', function ($query) use ($categoryId) {
        //         $query->where('id', $categoryId);
        //     });
        // }
        $leader = $leaderQuery->first();


        // $members = User::select('id', 'name', 'email', 'profile_url', 'referral_code', 'cleared', 'role_id')->where('parent_referral', $leader->id)->where('status', '1')->where('role_id','!=','3')->get();
        $membersQuery = User::select('id', 'name', 'email', 'profile_url', 'referral_code', 'cleared', 'role_id','status')->with(['productPurchases.product.category'])->where('parent_referral', $leader->id)->where('role_id','!=','3');
        if ($categoryId != 0) {
            $membersQuery->whereHas('productPurchases.product.category', function ($query) use ($categoryId) {
                $query->where('id', $categoryId);
            });
        }
        $members = $membersQuery->get();
        
        $leader->members_count = count($members);

        if($members!=null){
            foreach($members as $mem){
                $members_count = User::where('parent_referral', $mem->id)->where('status', '1')->count();
                $mem->members_count = $members_count;
            }
        }

        $leader->members = [];
        if($leader->role_id != 3){ // not influencer role
            $leader->members = $members;
        }

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
            'id','name','email','birthdate','nationality','address','city','zipcode','mobile_number','referral_code','profile_url','status','parent_referral','role_id'
        )->where('id', $id)->first(),"parent"=>null);
        
        $user["parent"]= User::where('id',$user["user"]->parent_referral)->first();

        // first product purhcased
        $productPurchase = ProductPurchase::with(['product', 'processed_by_user', 'transaction', 'transaction.mode_of_payment'])->where('product_id', 1)->where('purchased_by', $user['user']->id)->first();
        $user["payment_details"] = $productPurchase;

        // check if null product_purchase
        if($productPurchase){
            $productPurchase = ProductPurchase::with(['product'])->where('product_id', 1)->where('purchased_by', $user['user']->id)->orderBy('created_at', 'ASC')->first();
            
            // check again if null then proceed transactions
            if($productPurchase){
                $transaction = Transaction::with(['mode_of_payment'])->where('user_id', $productPurchase->purchased_by)
                ->where('trans_type', 1)
                ->where('processed_by', 1)
                ->orderBy('created_at', 'asc')
                ->first();

                if($transaction){
                    $productPurchase->processed_by = $transaction->processed_by;
                    $productPurchase->processed_by_user = User::select('id', 'name')->where('id', $transaction->processed_by)->first();
                }

                $productPurchase->transaction = $transaction;

                $user["payment_details"] = $productPurchase;
            }
        }

        return response()->json(['status' => true, 'user' => $user]);
    }

    public function changeReferrerId(Request $request) {
        try {
            $parentReferrer = User::where('referral_code', $request->referred_by)->first();
            $userToChange = User::where('id', $request->user_id)->first();
            $userToChange->parent_referral = $parentReferrer->id;
            $userToChange->update();
            return response()->json(['status' => true, 'message' => 'Update Successful']);
        } catch (\Exception $e){
            return response()->json(['status' => false]);
        }
    }

    public function changeUserRole(Request $request) {
        try {
            $user = User::where('id', $request->user_id)->first();
            $user->role_id = $request->role_id;
            $user->update();
            return response()->json(['status' => true, 'message' => 'Update Successful']);
        } catch (\Exception $e){
            return response()->json(['status' => false]);
        }
    }   
}
