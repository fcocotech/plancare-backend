<?php

namespace App\Http\Controllers;

use App\Models\WithdrawalAccount;
use Illuminate\Http\Request;

class WithdrawalAccountController extends Controller
{
    public function get(Request $request, $user_id){
        $withdrawalAccounts = WithdrawalAccount::where('user_id', $user_id)->get();
        return response()->json(['status' => true, 'accounts' => $withdrawalAccounts]);
    }


    public function store(Request $request, $user_id, $account_type)
    {
        // check if exists
        try {
            $withdrawalAccount = WithdrawalAccount::where('user_id', $user_id)->where('type', $account_type)->first();
            if(!$withdrawalAccount){
                // create
                $withdrawalAccount = new WithdrawalAccount;
                $withdrawalAccount->bank_name = $request->bank_name;
                $withdrawalAccount->account_name = $request->account_name ?? '';
                $withdrawalAccount->account_number = $request->account_number;
                $withdrawalAccount->status = $request->status ? 1 : 0;
                $withdrawalAccount->user_id = $user_id;
                $withdrawalAccount->type = $account_type;
                $withdrawalAccount->save();
            } else {
                // update
                $withdrawalAccount->bank_name = $request->bank_name;
                $withdrawalAccount->account_name = $request->account_name ?? '';
                $withdrawalAccount->account_number = $request->account_number;
                $withdrawalAccount->status = $request->status ? 1 : 0;
                $withdrawalAccount->update();
            }
            return response()->json(['status' => true, 'message' => 'Update successful.']);

        }catch(\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()]);
        }
        
    }

   
}
