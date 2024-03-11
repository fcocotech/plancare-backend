<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Transaction, UserCommission, Commission, ProductPurchase, Product, WithdrawalAccount, WithdrawalAccountType};
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\{File};
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    
    public function get() {
        $user = Auth::user();

        $transactions = Transaction::with(["user","processed_by"])->where('user_id', $user->id)->orWhere('processed_by', $user->id)->get();
        return response()->json([
            'status' => true,
            'transactions' => $transactions,
        ]);
    }

    public function earnings() {
        $user = Auth::user();

        if($user->id==1){
            $earnings = Transaction::with(['commission_from'])->where('trans_type', '2')->whereNotIn('withdrawable', [2,3,4,5])->get();
            // $earnings = UserCommission::where('user_id', $user->id)->get();
            $cleared = Transaction::with(['commission_from'])->where('trans_type', '2')->where('cleared',1)->sum('amount');
            $withdrawable = Transaction::with(['commission_from'])->where('trans_type', '2')->where('withdrawable',1)->get();
            $withdrawal_request = Transaction::with(['commission_from'])->where('trans_type', '3')->whereNot('withdrawable',5)->get();

            $points_purchase = Transaction::with(['commission_from'])->where('trans_type', '4')->where('payment_method', 6)->whereNot('status', 5)->get();

            $total_earnings = $earnings->sum('amount');
        }else{
            $earnings = Transaction::with(['commission_from'])->where('user_id', $user->id)->where('trans_type', '2')->whereNotIn('withdrawable', [2,3,4,5])->get();
            // $earnings = UserCommission::where('user_id', $user->id)->get();
            $cleared = Transaction::with(['commission_from'])->where('user_id', $user->id)->where('trans_type', '2')->where('cleared',1)->sum('amount');
            $withdrawable = Transaction::with(['commission_from'])->where('user_id', $user->id)->where('trans_type', '2')->where('withdrawable',1)->get();
            $withdrawal_request = Transaction::with(['commission_from'])->where('user_id', $user->id)->where('trans_type', '3')->whereNot('withdrawable',5)->get();

            $points_purchase = Transaction::with(['commission_from'])->where('trans_type', '4')->where('payment_method', 6)->whereNot('status', 5)->get();

            $total_earnings = $earnings->sum('amount');
        }
        return response()->json([
            'status' => true,
            'earnings' => $earnings,
            'cleared' => $cleared,
            'total_earnings' => $total_earnings,
            'withdrawal_request'=>$withdrawal_request->sum('amount'),
            'total_withdrawable'=>$withdrawable->sum('amount') - ($withdrawal_request->sum('amount') + $points_purchase->sum('amount'))
        ]);
    }

    public function withdrawalRequests() {
        $user = Auth::user();

        if($user->id == 1) {
            $withdrawals = Transaction::with(['user','mode_of_payment'])->whereIn('withdrawable', ['2','3','4','5'])->get();
        } else {
            $withdrawals = Transaction::with(['user','mode_of_payment'])->where('user_id', $user->id)->whereIn('withdrawable', ['2','3','4','5'])->get();
        }

        return response()->json([
            'status' => true,
            'withdrawal_requests' => $withdrawals
        ]);
    }

    public function create($data) {
        DB::beginTransaction();
        try{
            $transaction = new Transaction;
            $transaction->transaction_id = $data['transaction_id'];
            $transaction->description = $data['description'];
            $transaction->payment_method = $data['payment_method'];
            $transaction->amount = $data['amount'];
            $transaction->proof_url = $data['proof_url'];
            $transaction->processed_by = $data['processed_by'];
            $transaction->created_by=$data['processed_by'];
            $transaction->user_id = $data['user_id'];
            $transaction->trans_type = $data['type'];
            $transaction->status = $data['status'];
            $transaction->cleared=false;
            $transaction->withdrawable= $data['withdrawable'] ?? false;
            $transaction->commission_rate = $data['commission_rate'] ?? 0;
            $transaction->remarks = $data['remarks'] ?? 0;
            $transaction->product_id = $data['product_id'] ?? null;
            if(isset($data['commission_from'])){
                $transaction->commission_from = $data['commission_from'];
            }
            // $this->findChildCount()
            $transaction->save();
            DB::commit();
            return [
                "status" => true,
                "message" => 'Transaction complete',
                "id" => $transaction->id,
            ];
        }catch(Exception $e){
            DB::rollback();
            return [
                "status" => false,
                "message" => 'Transaction failed'
            ];
        }
       
    }

    public function makePayment(Request $request) {
      
        try {
            
            // if($request->proof_of_payment==null){
            //     return response()->json(['status' => false,'message' => "Must have a valid proof of payment"]); 
            // }else{
                $user = Auth::user();
                $cleared=0;
                $payment_for = User::with('parent')->where('id',$request->id)->where('status',2)->first();//get the user info of the member
                //double check for member count
                if($payment_for['parent']->role_id!=3){
                    if($this->findChildCount($payment_for->parent_referral)>=4){
                        return response()->json(['status' => false,'message' => "Referral code is invalid. Slot is already full. Pls use another code"]); 
                    }
                }
               
                if($payment_for) {
                    //add transaction for package payment
                    $data = [
                        'user_id' => $request->id,
                        'amount' => $request->amount,
                        'type'=>1,//$request->trans_type, Package Payment
                        'processed_by' => $user->id,
                        'payment_method' => $request->payment_method,
                        'transaction_id' => substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 10),
                        'description' => $request->description,
                        'parent_referral'=>$payment_for->parent_referral,
                        'status' => 1,
                        'proof_url' => $request->proof_of_payment,
                        'remarks' => $request->remarks,
                    ];

                    if($request->has('proof_of_payment') && $request->proof_of_payment != ''){
                        $proof_image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $request->proof_of_payment));
                        $proof_path = storage_path('app/public/images/proof/');
                        if(!File::isDirectory($proof_path)){
                            File::makeDirectory($proof_path, 0777, true, true);
                        }
                        $proof_name = time().'_'.$user->id.'_proof.png';
                        file_put_contents($proof_path.$proof_name, $proof_image);
                        $data['proof_url'] = env('APP_URL', '') . '/storage/images/proof/'.$proof_name;
                    }
                    
                    //make payment
                    $transaction = self::create($data);
                    
                    if($transaction['status']) {
                        $payment_for->status = 1;
                        $payment_for->update();

                        $productPurchase = ProductPurchase::where('id', $request->product_purchase_id)->first();
                        $product = Product::where('id',$productPurchase->product_id)->first();
                        if($productPurchase){
                            $productPurchase->status = '1';
                            $productPurchase->processed_by = $user->id;
                            $productPurchase->transaction_id = $transaction['id'];
                            $productPurchase->update();
                        }

                        //check if parent has 3 members already
                        
                        // commission distribution
                        if($payment_for['parent']->role_id==3){
                            $this->assignCommission($payment_for,$payment_for->id,0.3,$request->amount);
                            $this->clearInfluencerTrans($payment_for["parent"]->id,$payment_for->id);
                        }else{
                            $clearedparents=$this->clearParents($payment_for->parent_referral);
                            $this->assignCommission($payment_for,$payment_for->id,0.3,$request->amount);
                        }
                        
                        //clear transactions
                        //get other members of parent id
                        $members = User::where('parent_referral',$payment_for->parent_referral)->where("status",1)->get(['id']);
                        $this->clearTransactions($payment_for->parent_referral,$members);
                       
                        $trans=[];
                        // $trans=$memids->get(['id']);
                        if($payment_for->cleared==1){
                            $trans=$this->checkDownWithdrawableAmount($request->id,$trans);
                        }else{
                            $members=[];
                            $trans=$this->checkUpWithdrawableAmount($payment_for->parent_referral,$trans,$members);
                        }
                                                
                        //send email confirmation
                        $this->sendPaymentConfirmationEmail($data["transaction_id"],$payment_for,$product);
                        
                        return response()->json(['status' => true,'object'=>$payment_for, 'message' => "Payment Successful"]);
                    } else {
                        return response()->json(['status' => false, 'message' => 'Payment for user with ID: '.$request->id.' cannot be processed.']); 
                    }
                    
                } else {
                    return response()->json(['status' => false, 'message' => 'Payment for user with ID: '.$request->id.' cannot be processed.']);
                    
                }

            // }
            
        } catch(Exception $e) {
            // DB::rollback();
            return response()->json(['status' => false, 'message' => $e->getMessage(),'data'=>$members]);
        }
        
    }

    public function clearParents($parentid){
        $parent=User::find($parentid);
        if($this->findChildCount($parent->id)>=3){
                $parent->cleared =1;
                $parent->update();
        }

        return true;
    }

    public function sendPaymentConfirmationEmail($trans_no,$user,$product) {

        // $token = Str::random(32).$user->id;
        try{
        Mail::send('emails.payment-confirmation', [
            'name' => $user->name,'trans_no'=>$trans_no,'amount'=>$product->price,'prod_name'=>$product->name
        ], function ($message) use ($user,$trans_no) {
            $message->to($user->email)->subject('Payment Confirmation: ' . $trans_no);
        });
        }catch(Exception $e){
            throw $e;
        }
    }

    protected function assignCommission($member,$newmemberid,$comm_rate,$amt){
        // DB::beginTransaction();
        try{
            // $newmemberid=$member->id;
            $user = Auth::user();
            $parent = User::find($member->parent_referral);
            if($parent->id==1){
                return false;
            }else{
                if($parent!=null){
                    
                    $commission = new UserCommission();
                    // $transaction = new transaction();

                    $transaction = new Transaction;
                    $transaction->transaction_id = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 10);
                    $transaction->description = "Commission distribution";
                    $transaction->payment_method = 0;
                    $transaction->amount = $amt * $comm_rate;
                    $transaction->proof_url = null;
                    $transaction->processed_by = $user->id;
                    $transaction->created_by=$user->id;
                    $transaction->user_id = $parent->id;
                    $transaction->trans_type = 2;//commission
                    $transaction->status = 1;
                    $transaction->commission_rate = $comm_rate;
                    $transaction->commission_from = $newmemberid;
                    $transaction->cleared=false;
                    $transaction->withdrawable=false;
                    $transaction->save();

                    $commission->commission_level = 0;
                    $commission->user_id = $parent->id;
                    $commission->commission_from = $newmemberid;
                    $commission->status=1;
                    $commission->comm_rate = $comm_rate;
                    $commission->comm_amt = $comm_rate * $amt;
                    $commission->cleared=false;
                    $commission->save();
                                
                    // DB::commit();
                    //recursive function to crawl to members.
                    if($member['parent']->role_id!=3){
                        if($comm_rate==0.3){
                            return $this->assignCommission($parent,$newmemberid,0.1,$amt);
                        }else{
                            return $this->assignCommission($parent,$newmemberid,$comm_rate/2,$amt);
                        }
                    }
                    return true;
                }else{
                    return false;
                }
            }
        }catch(Exception $e){
            // DB::rollback();
            return false;
        }
    }

    protected function clearInfluencerTrans($userid,$memberid){
        DB::beginTransaction();
        try{
            Transaction::where('user_id',$userid)->where('commission_from',$memberid)->where('cleared',0)->update(['cleared'=>1]);
            DB::commit();

            return true;
        }catch(Exception $e){
            DB::rollback();
            return false;
        }
    }
    protected function setWithdrawableInfluencer($userid){
        try{
            $trans = Transaction::where('user_id',$userid)->where('trans_type',2)->where('cleared',1)->where('withdrawable',0)->get(['id']);
            if($trans->sum('amount')>=5000){
                $this->clearWithdrawableAmt($trans);
            }
            
            return true;
        }catch(Exception $e){
            return false;
        }
    }
    public function clearTransactions($parentid,$members){
        // DB::beginTransaction();
        try{
            $parent=User::where('id',$parentid)->first();
            
            if($parentid!=1){
                if($parent->cleared==1){
                   
                    Transaction::where('user_id',$parentid)->whereIn('commission_from',$members)->where('cleared',0)->update(['cleared'=>1]);
                    // DB::commit();
                    return $this->clearTransactions($parent->parent_referral,$members);
                }else{
                    //proceed to the next parent
                    return $this->clearTransactions($parent->parent_referral,$members);
                }
            }
            else{
                return false;
            }
            return true;
        }catch(Exception $e){
            // DB::rollback();
            return false;
        }
        
    }

    protected function setAsWithdrawable($member){

    }
    protected function clearWithdrawableAmt($trans){
        try{
            foreach($trans as $ids){
                Transaction::whereIn('id',$ids)->update(["withdrawable"=>1]);
            }
            
            return true;
        }catch(Exception $e){
            return false;
        }
        
    }
    protected function checkUpWithdrawableAmount($memberid,$trans,$members){
        // DB::beginTransaction();
        try{
            // $loggeduser = Auth::user();
            $user = User::with('parent')->where('id',$memberid)->first();
            // $clear_member=[];
            //Navigate Up (to parents)
            if($user->id!=1){
                if($user["parent"]->cleared==1 || $user->cleared==1){
                    // print($user);
                    $member=User::select('id')->where('parent_referral',$user->id)->where('status',1)->get();
                    if($member!=null){
                        // print("cleared: ".$clear_member);
                        array_push($members,$member);
                    }
                    // print_r($members);
                    // Transaction::where('user_id',$user["parent"]->id)->whereIn('commission_from',$mem->id)->where('cleared',1)->where('withdrawable',0)->first();
                    // if($members!=null){
                        foreach($members as $mem){
                            // print("members:".$mem);
                            foreach($mem as $ind){
                                // $transid=Transaction::where('user_id',$user->id)->where('commission_from',$ind->id)->where('cleared',1)->where('withdrawable',0)->get(['id','user_id','commission_from','amount']);
                                Transaction::where('user_id',$user->id)->where('commission_from',$ind->id)->where('cleared',1)->where('withdrawable',0)->update(["withdrawable"=>1]);
                                // print("withdraw transactions:".$transid);
                                // if($transid!=null){
                                    // array_push($trans,$transid);
                                // }
                            }
                            
                        }
                    }
                  
                    $this->checkUpWithdrawableAmount($user->parent_referral,$trans, $members);
                // }
                // else{
                //     // DB::rollback();
                //     // return response()->json(['status' => false, 'message' => 'No cleared','parent'=>$user]); ;
                //     // $trans=null;
                //     return $trans;
                // }
            }
            // else{
            //     return $trans;
            //     // return response()->json(['status' => true, 'message' => 'No cleared','parent'=>$user]);
            //     // DB::commit();
            // }
            // print_r($members);
            return true;
 
        }catch(Exception $e){ 
            // DB::rollback();
            return false;
        }
    }

    protected function checkDownWithdrawableAmount($parentid,$trans){
        
        try{
            //get fellow members
            $clearmembers = User::with("clearedmembers")->where('parent_referral',$parentid)->where('status',1)->where('cleared',1)->get();
            $lastclear=null;
            $status=true;
            if(count($clearmembers)!=0){
             //set transactions to withdrawable
            //  print("Has cleared members: ". $clearmembers);
                foreach($clearmembers as $mem){

                    print(count($mem->clearedmembers));
                    // array_push($trans,$mem);
                    // $transnew=$this->checkDownWithdrawableAmount($mem->id,$trans);
                    // array_push($trans,$transnew);
                    // $lastclear=$mem->id;
                    
                    if(count($mem->clearedmembers)==0){
                        // print("no cleared members: ". $mem->id);
                        $members=[];
                        $trans=$this->checkUpWithdrawableAmount($mem->id,$trans,$members);
                        // array_push($trans,$transnew);
                    }else{
                        // print("Has cleared members: ".$mem->id);
                        $this->checkDownWithdrawableAmount($mem->id,$trans);
                    }
                }
            }else{
                
                $members=[];
                // print("no cleared members: ". $parentid);
                $trans=$this->checkUpWithdrawableAmount($parentid,$trans,$members);
            }
            // print("last clear:" . $lastclear);
            // $members=[];
            // $trans=$this->checkUpWithdrawableAmount($parentid,$trans,$members);
            
            
            return $trans;
        }catch(Exception $e){
            // var_dump($e->message());
            return false;
        }
        
    }

    public function APIcleartransactions(Request $request){
        $members = User::where('parent_referral',$request->id)->where('status',1)->get(['id']);
   
        $status = $this->clearTransactions($request->id,$members);
        
        return response()->json(['status' => $status,"parent"=>$request->id, "data"=>$members]);
    }
    public function APIcheckWithdrawableAmount(Request $request){
        // $dbtrans= DB::beginTransaction();   
        try{
            $trans=[];
            $members=[];
            $trans= $this->checkUpWithdrawableAmount($request->id,$trans,$members);

            if($trans!=null){
                // $this->clearUpWithdrawableAmt($trans);
                return response()->json(['status' => true, 'message' => 'Cleared',"data"=>$trans]);
            }else{
                return response()->json(['status' => false, 'message' => 'Not cleared',"data"=>$trans]);
            }
            
        }catch(Exception $e){
         
            return false;
        }
    }

    public function APIDowncheckWithdrawableAmount(Request $request){
        // $dbtrans= DB::beginTransaction();
        try{

            $member = User::with('parent','clearedmembers')->where('id',$request->id)->where("status",1)->first();
            // $memids =[];
            // $memids = User::with('clearedmembers')->where('parent_referral',$member->parent_referral)->where('status',1)->where('cleared',1);
            $trans=[];
            $members=[];
            // $trans=$memids->get(['id']);
            if($member->cleared==1){
                // rint("member cleared");
                $trans=$this->checkDownWithdrawableAmount($request->id,$trans);
            }else{
                // $members=[];
                // print("member not cleared");
                $trans=$this->checkUpWithdrawableAmount($member->parent_referral,$trans,$members);
            }
           
            // if($this->clearWithdrawableAmt($trans)){
                return response()->json(['status' => true, 'message' => 'Cleared',"data"=>$trans]);
            // }else{
            //     return response()->json(['status' => false, 'message' => 'Not cleared',"data"=>$trans,"member"=>$member]);
            // }
        }catch(Exception $e){
            return response()->json(['status' => false, 'message' => 'Error',"data"=>$member,"members"=>$memids->get(['id'])->toArray()]);
        }
        
    }

    public function withdrawalRequest(Request $request) {
        try{
            $user = Auth::user();
            // check total earnins or validate
            $earnings = Transaction::with(['commission_from'])->where('user_id', $user->id)->where('trans_type', '2')->get();
            // $withdrawable = Transaction::with(['commission_from'])->where('user_id', $user->id)->where('trans_type', '2')->where('withdrawable',1)->get();
            $total_earnings = $earnings->sum('amount');

            if($request->points_to_withdraw < 5000){
                return response()->json(['status' => false, 'message' => 'Withdrawable amount limit is 5,000.00']);
            }

            if($request->points_to_withdraw > $total_earnings){
                return response()->json(['status' => false, 'message' => 'Points to withdraw should not exceed the total withdrawable points.']);
            }

            $data = [
                'user_id' => $user->id,
                'amount' => $request->points_to_withdraw,
                'type'=> 3, // Withdrawal Request
                'processed_by' => $user->id,
                'payment_method' => $request->withdrawal_method,
                'transaction_id' => substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 10),
                'description' => 'Withdrawal Request',
                'status' => 2,
                'proof_url' => null,
                'withdrawable' => 2, // 2 - pending, 3 - in progress, 4 - released, 5 - cancelled
            ];

            //make transaction
            $transaction = self::create($data);

            $withdraw_account_details = WithdrawalAccount::where('user_id', $user->id)->where('type', $request->withdrawal_method)->first();
            $mode_of_payment = WithdrawalAccountType::where('id', $request->withdrawal_method)->first();

            Mail::send('emails.withdrawal-status.in-progress', [
                'name' => $user->name,
                'trans_no' => $data['transaction_id'],
                'amount_to_withdraw' => $data['amount'],
                'admin_fee' => 50.00,
                'amount_to_receive' => ($data['amount'] - 50),
                'withdrawal_method' => $mode_of_payment->name ?? '--',
                'withdraw_account_details' => $withdraw_account_details
            ], function ($message) use ($user, $data) {
                $message->to($user->email)->subject('Your Withdrawal Request is In Progress with Transaction ID: '.$data['transaction_id']);
            });

            // get admin user
            $adminUser = User::where('id', 1)->first();
            if($adminUser->email){
                Mail::send('emails.admin-notifications.user-withdrawal-notification', [
                    'name' => $adminUser->name,
                    'trans_no' => $data['transaction_id'],
                    'amount_to_withdraw' => $data['amount'],
                    'admin_fee' => 50.00,
                    'amount_to_receive' => ($data['amount'] - 50),
                    'withdrawal_method' => $mode_of_payment->name ?? '--',
                    'withdraw_account_details' => $withdraw_account_details,
                    'requesting_user' => $user,
                ], function ($message) use ($adminUser, $data, $user) {
                    $message->to($adminUser->email)->subject('Withdrawal Request - User ID: '.$user->referral_code.' with Transaction ID: '.$data['transaction_id']);
                });
            }

            return response()->json(['status' => $transaction['status'], 'message' => $transaction['status'] ? 'Withdraw requests successful and marked as pending.' : $transaction['message']]);
        } catch (\Exception $e){
            return response()->json(['status' => false, 'message' => 'There was an error on your request.', 'detail' => $e->getMessage()]);
        }
    }

    public function withdrawalRequestFullDetails(Request $request) {
        // get user connected bank
        $transaction = Transaction::with([
            'mode_of_payment',
            'user',
            'user.withdrawal_accounts',
        ])->where('transaction_id', $request->transaction_id)->first();
        
        $requested_account = WithdrawalAccount::where('user_id', $transaction->user->id)->where('type', $transaction->payment_method)->first();
        $transaction->requested_account = $requested_account;
        if(!$transaction){
            return response()->json(['status' => false, 'message' => 'Transaction not valid!']);
        }
        return response()->json(['status' => true, 'transaction' => $transaction]);
    }

    public function withdrawalRequestCancelled(Request $request) {
        $user = Auth::user();

        $transaction = Transaction::with(['mode_of_payment'])->where('transaction_id', $request->transaction_id)->first();
        if(!$transaction){
            return response()->json(['status' => false, 'message' => 'Transaction not valid!']);
        }

        $transaction->withdrawable = 5; // cancelled 
        $transaction->status = 5;
        $transaction->update();

        Mail::send('emails.withdrawal-status.cancelled', [
            'name' => $user->name,
            'trans_no' => $transaction->transaction_id,
            'amount_to_withdraw' => $transaction->amount,
            'admin_fee' => 50.00,
            'amount_to_receive' => ($transaction->amount - 50),
            'withdrawal_method' => $transaction->mode_of_payment->name ?? '--',
        ], function ($message) use ($user, $transaction) {
            $message->to($user->email)->subject('You\'ve Cancelled Your Withdrawal Request with Transaction ID: '.$transaction->transaction_id);
        });

        return response()->json(['status' => true,'amount_to_withdraw'=> $transaction->amount,'message' => 'Transaction: '.$request->transaction_id. ' was cancelled!']);
    }

    public function withdrawalRequestStatusUpdate(Request $request) {

        $transaction = Transaction::with(['user', 'user.withdrawal_accounts', 'mode_of_payment'])->where('transaction_id', $request->transaction_id)->first();
        if(!$transaction){
            return response()->json(['status' => false, 'message' => 'Transaction not valid!']);
        }
        
        $transaction_user = $transaction->user;

        $withdraw_account_details = WithdrawalAccount::where('user_id', $transaction_user->id)->where('type', $transaction->payment_method)->first();

        if(!in_array($request->new_status, [2, 3, 4, 5])) {
            return response()->json(['status' => false, 'message' => 'Status not valid!']);
        }
    
        $transaction->withdrawable = $request->new_status;
        $transaction->status = $request->new_status;
        $transaction->update();

        $transaction_status="";
        $subject = "";
        $template = "";
        switch($request->new_status) {
            case '2': case 2:
                $transaction_status = 'In Progress';
                $subject = 'Your Withdrawal Request is In Progress with Transaction ID: '.$transaction->transaction_id; 
                $template = "emails.withdrawal-status.in-progress";
            break;
            case '3': case 3:
                $transaction_status = 'Processing';
                $subject = 'Your Withdrawal Request is Processing with Transaction ID: '.$transaction->transaction_id;
                $template = "emails.withdrawal-status.processing";
            break;
            case '4': case 4:
                $transaction_status = 'Released'; 
                $subject = 'Your Withdrawal Request has been Successfully Processed and Funds Released with Transaction ID: '.$transaction->transaction_id;
                $template = "emails.withdrawal-status.released";
            break;
            case '5': case 5:
                $transaction_status = 'Cancelled';
                $subject = 'Your Withdrawal Request has been Cancelled with Transaction ID: '.$transaction->transaction_id;
                $template = "emails.withdrawal-status.cancelled";
            break;
            default:
            break;
        }

        Mail::send($template, [
            'name' => $transaction_user->name,
            'trans_no' => $transaction->transaction_id,
            'amount_to_withdraw' => $transaction->amount,
            'admin_fee' => 50.00,
            'amount_to_receive' => ($transaction->amount - 50),
            'withdrawal_method' => $transaction->mode_of_payment->name ?? '--',
            'withdraw_account_details' => $withdraw_account_details,
        ], function ($message) use ($transaction_user, $subject) {
            $message->to($transaction_user->email)->subject($subject);
        });

        return response()->json(['status' => true, 'message' => 'Transaction: '.$request->transaction_id. ' status was updated to '.$transaction_status]);
    }

    public function buyProduct(Request $request, $product_id) {
        // check product availability
        $product = Product::where('id', $product_id)->first();
        if(!$product){
            return response()->json(['status' => false, 'message' => 'Transaction failed!']);
        }
        // check user
        $user = Auth::user();
        $prod_purcahse = new ProductPurchase();
        // check available points
        $withdrawable = Transaction::with(['commission_from'])->where('user_id', $user->id)->where('trans_type', '2')->where('withdrawable',1)->get();
        $withdrawal_request = Transaction::with(['commission_from'])->where('user_id', $user->id)->where('trans_type', '3')->whereNot('withdrawable',5)->get();
        $points_purchase = Transaction::with(['commission_from'])->where('trans_type', '4')->where('payment_method', 6)->get();
        
        $availablePoints = $withdrawable->sum('amount')- ($withdrawal_request->sum('amount') + $points_purchase->sum('amount'));

        if($availablePoints < $product->price){
            return response()->json(['status' => false, 'message' => 'Not enough points.']);
        }

        $data = [
            'user_id' => $user->id,
            'amount' => $product->price,
            'type'=> 4, // Points Purchase
            'processed_by' => $user->id,
            'payment_method' => 6, // Points
            'transaction_id' => substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 10),
            'description' => 'Product Purchase - Points',
            'status' => 0, // Pending, 2 - In Progress, 1 - Completed
            'proof_url' => null,
            'withdrawable' => 0,
            'product_id' => $product->id
        ];

        $transaction = self::create($data);

        $prod_purcahse->product_id =$product->id;
        $prod_purcahse->purchase_by=$user->id;
        $prod_purcahse->referrer_id=$user->id;
        $prod_purcahse->transaction_id=$transaction->transaction_id;
        $prod_purcahse->status=0;//pending
        // $prod_purcahse->quantity=$request->qtyToBuy;

        $prod_purcahse->create();

        if($transaction['status']) {
            // email to admin
            // email to user

            Mail::send('emails.product-status.pending', [
                'name' => $user->name,
                'trans_no' => $data['transaction_id'],
                'amount_to_withdraw' => $data['amount'],
                'total_points_available' => ($availablePoints - $product->price) ,
                'product' => $product,
            ], function ($message) use ($user, $data) {
                $message->to($user->email)->subject('Purchase Product with Points is Pending with Transaction ID: '.$data['transaction_id']);
            });

            // get admin user
            $adminUser = User::where('id', 1)->first();
            if($adminUser->email){
                Mail::send('emails.admin-notifications.product-purchase-notification', [
                    'name' => $adminUser->name,
                    'trans_no' => $data['transaction_id'],
                    'amount_to_withdraw' => $data['amount'],
                    'total_points_available' => $availablePoints,
                    'product' => $product,
                    'buyer' => $user,
                ], function ($message) use ($adminUser, $data, $user) {
                    $message->to($adminUser->email)->subject('Product Purchase with Points - User ID: '.$user->referral_code.' with Transaction ID: '.$data['transaction_id']);
                });
            }

            return response()->json(['status' => true, 'message' => "Your request to purchase the product with Points was successful and is pending approval."]);
        } else {
            return response()->json(['status' => false, 'message' => 'Transaction cannot be processed.']); 
        }
    }
}
