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

        $earnings = Transaction::with(['commission_from'])->where('user_id', $user->id)->where('trans_type', '2')->get();
        // $earnings = UserCommission::where('user_id', $user->id)->get();
        $withdrawable = Transaction::with(['commission_from'])->where('user_id', $user->id)->where('trans_type', '2')->where('cleared',1)->get();
        $total_earnings = $earnings->sum('amount');
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
        DB::beginTransaction();
        try {
            
            if($request->proof_of_payment==null){
                return response()->json(['status' => false,'message' => "Must have a valid proof of payment"]); 
            }else{
                $user = Auth::user();
                $cleared=0;
                $payment_for = User::find($request->id);//get the user info of the member
                //double check for member count
                if($this->findChildCount($payment_for->parent_referral)>=4){
                    return response()->json(['status' => false,'message' => "Referral code is invalid. Slot is already full. Pls use another code"]); 
                }
                // elseif($this->findChildCount($payment_for->parent_referral)>=3){

                // }

                if($payment_for) {
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
                        $parent=User::find($payment_for->parent_referral);
                        if($this->findChildCount($parent->id)>=3){
                                $parent->cleared =1;
                                $parent->update();
                        }
                        // commission distribution
                        DB::commit();
                        $this->assignCommission($payment_for,0.3,$request->amount);
                        
                        //send email confirmation
                        $this->sendPaymentConfirmationEmail($data["transaction_id"],$payment_for,$product);
                        
                        return response()->json(['status' => true,'object'=>$product, 'message' => "Payment Successful"]);
                    } else {
                        return response()->json(['status' => false, 'message' => 'Payment for user with ID: '.$request->id.' cannot be processed.']); 
                    }
                    
                } else {
                    return response()->json(['status' => false, 'message' => 'Payment for user with ID: '.$request->id.' cannot be processed.']);
                    
                }

            }
            
        } catch(Exception $e) {
            DB::rollback();
            return response()->json(['status' => false, 'message' => $e->getMessage()]);
        }
        
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

    protected function assignCommission($member,$comm_rate,$amt){
        DB::beginTransaction();
        try{
            $user = Auth::user();
            $parent = User::find($member->parent_referral);
            if($parent->id==1){
                return false;
            }
            if($parent!=null){
                
                $commission = new UserCommission();
                $transaction = new transaction();

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
                $transaction->commission_from = $member->id;
                $transaction->save();

                $commission->commission_level = 0;
                $commission->user_id = $parent->id;
                $commission->commission_from = $member->id;
                $commission->status=1;
                $commission->comm_rate = $comm_rate;
                $commission->comm_amt = $comm_rate * $amt;
                $commission->save();
                 //check if parent has 3 members
                if($this->findChildCount($parent->id)>=3){
                   
                    $members = User::where('parent_referral',$parent->id)->get();

                    foreach($members as $mem){
                        UserCommission::where('user_id',$parent->id)->where('commission_from',$mem->id)->where('cleared',0)->update(['cleared'=>1]);
                        Transactions::where('user_id',$parent->id)->where('commission_from',$member->id)->where('cleared',0)->update(['cleared'=>1]);
                    }
                }
                
                DB::commit();
                //recursive function to crawl to members.
                if($comm_rate==0.3){
                    return $this->assignCommission($parent,0.1,$amt);
                }else{
                    return $this->assignCommission($parent,$comm_rate/2,$amt);
                }
                return true;
            }else{
                return false;
            }
        }catch(Exception $e){
            DB::rollback();
            return false;
        }
    }
}
