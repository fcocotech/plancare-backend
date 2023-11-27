<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Transaction, UserCommission, Commission};
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\{File};

class TransactionController extends Controller
{
    public function get() {
        $transactions = Transaction::get();
        return response()->json([
            'status' => true,
            'transactions' => $transactions,
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
        $transaction->user_id = $data['user_id'];
        $transaction->status = $data['status'];

        $transaction->save();

        return [
            "status" => true,
            "message" => 'Transaction complete'
        ];
    }

    public function makePayment(Request $request) {
        try {
            $user = Auth::user();
            $payment_for = User::find($request->id);

            if($payment_for) {
                $data = [
                    'user_id' => $request->id,
                    'amount' => $request->amount,
                    'processed_by' => $user->id,
                    'payment_method' => $request->payment_method,
                    'transaction_id' => substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 10),
                    'description' => 'Make payment for '.$payment_for->name,
                    'status' => 'Complete',
                    'proof_url' => ''
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
                    $payment_for->status = 'active';
                    $payment_for->update();

                    // commission distribution
                    $datas = $this->commissionDistribution($payment_for, $request->amount);

                    return response()->json(['status' => true, 'message' => $transaction['message'], 'datas' => $datas]);
                } else {
                    return response()->json(['status' => false, 'message' => 'Payment for user with ID: '.$request->id.' cannot be processed.']); 
                }
            } else {
                return response()->json(['status' => false, 'message' => 'Payment for user with ID: '.$request->id.' cannot be processed.']);
            }
        } catch(\Exception $e) {
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
                'status' => 'Complete',
                'proof_url' => ''
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
}
