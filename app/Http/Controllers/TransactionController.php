<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Transaction, UserCommission, Commission, ProductPurchase,Product};
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
            $earnings = Transaction::with(['commission_from'])->where('trans_type', '2')->get();
            // $earnings = UserCommission::where('user_id', $user->id)->get();
            $withdrawable = Transaction::with(['commission_from'])->where('trans_type', '2')->where('withdrawable',1)->get();
            $total_earnings = $earnings->sum('amount');
        }else{
            $earnings = Transaction::with(['commission_from'])->where('user_id', $user->id)->where('trans_type', '2')->get();
            // $earnings = UserCommission::where('user_id', $user->id)->get();
            $withdrawable = Transaction::with(['commission_from'])->where('user_id', $user->id)->where('trans_type', '2')->where('withdrawable',1)->get();
            $total_earnings = $earnings->sum('amount');
        }
        return response()->json([
            'status' => true,
            'earnings' => $earnings,
            'total_earnings' => $total_earnings,
            'total_withdrawable'=>$withdrawable->sum('amount')
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
            $transaction->withdrawable=false;
            $transaction->commission_rate = $data['commission_rate'] ?? 0;
            if(isset($data['commission_from'])){
                $transaction->commission_from = $data['commission_from'];
            }
            // $this->findChildCount()
            $transaction->save();
            DB::commit();
            return [
                "status" => true,
                "message" => 'Transaction complete'
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
                $payment_for = User::where('id',$request->id)->where('status',2)->first();//get the user info of the member
                //double check for member count
                if($this->findChildCount($payment_for->parent_referral)>=4){
                    return response()->json(['status' => false,'message' => "Referral code is invalid. Slot is already full. Pls use another code"]); 
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
                        'proof_url' => $request->proof_of_payment
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
                        $product = Product::where('id',$productPurchase->product_id)->first();;
                        if($productPurchase){
                            $productPurchase->status = '1';
                            $productPurchase->update();
                        }

                        //check if parent has 3 members already
                        $clearedparents=$this->clearParents($payment_for->parent_referral);
                        // commission distribution
                        $this->assignCommission($payment_for,$payment_for->id,0.3,$request->amount);
                        //clear transactions
                        //get other members of parent id
                        $members = User::where('parent_referral',$payment_for->parent_referral)->where("status",1)->get(['id']);
                        $this->clearTransactions($payment_for->parent_referral,$members);
                        
                        $trans=[];
                        $clearedmembers=null;
                        $members=[];

                        //check withdrawable
                        // $trans=$this->checkDownWithdrawableAmount($payment_for,$trans);
                        
                        //Navigate Up to parents
                        // if($clearedparents){
                        // $trans=$this->checkUpWithdrawableAmount($payment_for->id,$trans,$members);

                        //     if($trans!=null || !$trans){
                        //         //clear Parents withdrawable amount
                        //         if($this->clearUpWithdrawableAmt($trans)){
                        //             $transdown=[];
                        //             $transdown=$this->checkDownWithdrawableAmount($payment_for->parent_referral,$transdown);
                        //             if($transdown!=null |!$transdown){
                        //                 $this->clearUpWithdrawableAmt($transdown);
                        //             }
                                    
                        //             // // check if self is cleared
                        //             // if($this->findChildCount($payment_for->id)>2){
                        //             //     //Navigate to members. Check other members that are cleared
                                        
                        //             //     $clearedmembers = User::where('parent_referral',$payment_for->id)->get();
                        //             //     if($clearedmembers!=null){
                        //             //         //recursive navigation on cleared members nodes
                        //             //         $this->checkDownWithdrawableAmount($clearedmembers,$payment_for->id,$transdown);
                        //             //     }
                        //             // }
                                    
                        //         }
                               
                        //     }
                        // }
                        //send email confirmation
                        $this->sendPaymentConfirmationEmail($data["transaction_id"],$payment_for,$product);
                        
                        return response()->json(['status' => true,'object'=>$product, 'message' => "Payment Successful",'members'=>$clearedmembers]);
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
                    if($comm_rate==0.3){
                        return $this->assignCommission($parent,$newmemberid,0.1,$amt);
                    }else{
                        return $this->assignCommission($parent,$newmemberid,$comm_rate/2,$amt);
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

    public function clearTransactions($parentid,$members){
        // DB::beginTransaction();
        try{
            $parent=User::where('id',$parentid)->first();
            if($parentid!=1){
                if($parent->cleared==1){
                    
                    // foreach($members as $mem){
                    //     // UserCommission::where('user_id',$parentid)->where('commission_from',$mem->id)->where('cleared',0)->update(['cleared'=>1]);
                    //     Transaction::where('user_id',$parentid)->where('commission_from',$mem->id)->where('cleared',0)->update(['cleared'=>1]);
                    // }
                    Transaction::where('user_id',$parentid)->whereIn('commission_from',$members)->where('cleared',0)->update(['cleared'=>1]);
                    // DB::commit();

                    // $add_members = User::select('id')->where('parent_referral',$parent->parent_referral)->get();
                    // array_push('$members',$add_members);
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
    protected function clearUpWithdrawableAmt($trans){
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
            $clear_member=[];
            //Navigate Up (to parents)
            if($user["parent"]->id!=1){
                if($user["parent"]->cleared==1){

                    $clear_member=User::select('id')->where('parent_referral',$user["parent"]->id)->where('status',1)->get(['id']);
                    if($clear_member!=null){
                        array_push($members,$clear_member);
                    }
                    
                    // Transaction::where('user_id',$user["parent"]->id)->whereIn('commission_from',$mem->id)->where('cleared',1)->where('withdrawable',0)->first();
                    if($members!=null){
                        foreach($members as $mem){
                            $transid=Transaction::where('user_id',$user["parent"]->id)->whereIn('commission_from',$mem)->where('cleared',1)->where('withdrawable',0)->get(['id']);
                            // Transaction::where('user_id',$user["parent"]->id)->whereIn('commission_from',$mem)->where('cleared',1)->where('withdrawable',0)->update(["withdrawable"=>0]);
                           
                            if($transid!=null){
                                array_push($trans,$transid);
                            }
                        }
                    }
                    
                    return $this->checkUpWithdrawableAmount($user->id,$trans, $members);
                }else{
                    // DB::rollback();
                    // return response()->json(['status' => false, 'message' => 'No cleared','parent'=>$user]); ;
                    // $trans=null;
                    return $trans;
                }
            }else{
                return $trans;
                // return response()->json(['status' => true, 'message' => 'No cleared','parent'=>$user]);
                // DB::commit();
            }


        }catch(Exception $e){ 
            // DB::rollback();
            return false;
        }
    }

    protected function checkDownWithdrawableAmount($parentid,$trans){
        
        try{
            //get cleared fellow members
            $clearmembers = User::with("members")->where('parent_referral',$parentid)->where('status',1)->where('cleared',1)->get();
            
            if($clearmembers!=null){
             //set transactions to withdrawable
                foreach($clearmembers as $mem){
                    // $transid = Transaction::where('user_id',$parentid)->where('commission_from',$mem->id)->where('cleared',1)->where('withdrawable',0)->where('trans_type',2)->get(['id','commission_from','amount']);
                    // if($transid!=null){
                    //     array_push($trans,$transid);
                    // }

                    // if($mem->cleared==1){
                        $trans=$this->checkDownWithdrawableAmount($mem->id,$trans);
                    // }
                    
                }
            }else{
                $members=[];
                $trans=$this->checkUpWithdrawableAmount($parentid,$trans,$members);
            }
            return $trans;
        }catch(Exception $e){
            // var_dump($e->message());
            return $trans=null;
        }
        
    }

    public function APIcleartransactions(Request $request){
        $members = User::where('parent_referral',$request->id)->where('status',1)->get();
   
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

            $members = User::with('parent','clearedmembers')->where('id',$request->id)->where("status",1)->first();
            $memids =[];
            $memids = User::with('clearedmembers')->where('parent_referral',$members->parent_referral)->where('status',1)->where('cleared',1);
            $trans=[];
            // $trans=$memids->get(['id']);
            $trans=$this->checkDownWithdrawableAmount($members->parent_referral,$trans);
            // array_merge(...$memids->get(['id'])->toArray());
            if($trans!=null){
                return response()->json(['status' => true, 'message' => 'Cleared',"data"=>$trans,"members"=>$memids->get(['id'])->toArray()]);
            }else{
                return response()->json(['status' => false, 'message' => 'Not cleared',"data"=>$trans,"members"=>$memids->get(['id'])->toArray()]);
            }
        }catch(Exception $e){
            return response()->json(['status' => false, 'message' => 'Error',"data"=>$members,"members"=>$memids->get(['id'])->toArray()]);
        }
        
    }
}
