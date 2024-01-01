<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Transaction, UserCommission, Commission, ProductPurchase};
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\{File};

class TransactionController extends Controller
{
    public function get() {
        $user = Auth::user();

        $transactions = Transaction::where('user_id', $user->id)->orWhere('processed_by', $user->id)->get();
        return response()->json([
            'status' => true,
            'transactions' => $transactions,
        ]);
    }

    public function earnings() {
        $user = Auth::user();

        $earnings = Transaction::with(['commission_from'])->where('user_id', $user->id)->where('payment_method', 'Commissions')->get();
        $total_earnings = $earnings->sum('amount');
        return response()->json([
            'status' => true,
            'earnings' => $earnings,
            'total_earnings' => $total_earnings
        ]);
    }

    public function create($data) {
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

        $transaction->save();

        return [
            "status" => true,
            "message" => 'Transaction complete'
        ];
    }

    public function makePayment(Request $request) {
        try {
            $user = Auth::user();
            $payment_for = User::find($request->id);//get the user info of the member

            if($payment_for) {
                $data = [
                    'user_id' => $request->id,
                    'amount' => $request->amount,
                    'type'=>$request->trans_type,
                    'processed_by' => $user->id,
                    'payment_method' => $request->payment_method,
                    'transaction_id' => substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 10),
                    'description' => $request->description,
                    'parent_referral'=>$payment_for->parent_referral,
                    'status' => 1,
                    'proof_url' => null
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

                $transaction = self::create($data);
                if($transaction['status']) {
                    $payment_for->status = 1;
                    $payment_for->update();

                    $productPurchase = ProductPurchase::where('id', $request->product_purchase_id)->first();
                    if($productPurchase){
                        $productPurchase->status = '1';
                        $productPurchase->update();
                    }
                
                    // commission distribution
                    // $datas = $this->commissionDistribution($payment_for, $request->amount);
                    $this->assignCommission($payment_for,$request->amount,0.3);

                    return response()->json(['status' => true, 'message' => "Payment Successful"]);
                } else {
                    return response()->json(['status' => false, 'message' => 'Payment for user with ID: '.$request->id.' cannot be processed.']); 
                }
            } else {
                return response()->json(['status' => false, 'message' => 'Payment for user with ID: '.$request->id.' cannot be processed.']);
            }
        } catch(Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    protected function commissionDistribution($from, $amount_paid) {
        $datas = [];
        $user = Auth::user();
        $connectedNodes = UserCommission::where('commission_from', $from->id)->where('status', 'unreleased')->get();
        foreach($connectedNodes as $key => $node) {
            $commission = Commission::where('level', $node->commission_level)->first();

            $rate_percentage = $commission->rate / 100;

            $commission_amount = $amount_paid * $rate_percentage;

            $data = [
                'user_id' => $node->user_id,
                'amount' => $commission_amount,
                'processed_by' => $user->id,
                'payment_method' => 'Commissions',
                'transaction_id' => substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 10),
                'description' => 'Received '.($commission->rate).'% commission from '.$from->name,
                'status' => 1,
                'proof_url' => '',
                'commission_rate' => $commission->rate,
                'commission_from' => $from->id
            ];

            $datas[] = $data;
            $transaction = self::create($data);
            if($transaction['status']) {
                $user_commission = UserCommission::find($node->id);
                $user_commission->status = 'released';
                $user_commission->update();
            }
        }
        return ['data' => $datas, 'nodes' => $connectedNodes];
    }

    // protected function APIcommissionDistribution(Requst $request) {
    //     $datas = [];
    //     $user = Auth::user();
    //     $connectedNodes = UserCommission::where('commission_from', $request->id)->where('status', 'unreleased')->get();
    //     foreach($connectedNodes as $key => $node) {
    //         $commission = Commission::where('level', $node->commission_level)->first();

    //         $rate_percentage = $commission->rate / 100;

    //         $commission_amount = $request->amount * $rate_percentage;

    //         $data = [
    //             'user_id' => $node->user_id,
    //             'amount' => $commission_amount,
    //             'processed_by' => $user->id,
    //             'payment_method' => 6,
    //             'trans_type'=>2,//commissions
    //             'transaction_id' => substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 10),
    //             'description' => 'Received '.($commission->rate).'% commission from '.$request->name,
    //             'status' => 1,
    //             'proof_url' => '',
    //             'commission_rate' => $commission->rate,
    //             'commission_from' => $request->id
    //         ];

    //         $datas[] = $data;
    //         $transaction = self::create($data);
    //         if($transaction['status']) {
    //             $user_commission = UserCommission::find($node->id);
    //             $user_commission->status = 1;
    //             $user_commission->update();
    //         }
    //     }
    //     return ['data' => $datas, 'nodes' => $connectedNodes];
    // }

    public function APIcommissionDistribution2(Request $request) {
        // $datas = [];
        try{
            $user = Auth::user();
            $member =  User::find($request->id);
                       
            // $parent = User::where('parent_referral',$member->parent_referral);
            $this->assignCommission($member,$request->rate,$request->amt);

            return response()->json(['status' => true, 'message' => 'Success']);
        }catch(Exception $e){
            return response()->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    protected function assignCommission($member,$comm_rate,$amt){
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

            if($comm_rate==0.3){
                return $this->assignCommission($parent,0.2,$amt);
            }else{
                return $this->assignCommission($parent,$comm_rate/2,$amt);
            }
            
            return true;
        }else{
            return false;
        }

        // return true;
        
    }
}
