<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\{File};

class TransactionController extends Controller
{
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
        $user = Auth::user();
        $payment_for = User::find($request->id);

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
        }
        return response()->json($transaction);
    }
}
