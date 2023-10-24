<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    public function create($data) {
        $transaction = new Transaction;
        $transaction->transaction_id = $data->transaction_id;
        $transaction->description = $data->description;
        $transaction->payment_method = $data->payment_method;
        $transaction->amount = $data->amount;
        $transaction->proof_url = $data->proof_url;
        $transaction->processed_by = $data->processed_by;
        $transaction->user_id = $data->user_id;
        $transaction->status = $data->status;

        $transaction->save();

        return response()->json([
            "status" => true,
            "message" => 'Transaction complete'
        ]);
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

        // self::create($data);
        return response()->json($data);
    }
}
