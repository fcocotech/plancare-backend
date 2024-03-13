<?php

namespace App\Http\Controllers;

use App\Models\ProductPurchase;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductPurchaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }
    public function get() {
        $user = Auth::user();

        if($user->role_id==1){
            $purchases = ProductPurchase::with(["purchasedby","product"])->where('purchase_type',2)->get();
        }else{
            $purchases = ProductPurchase::with(["purchasedby","product"])->where('purchased_by', $user->id)->where('purchase_type',2)->get();
        }
        
        return response()->json([
            'status' => true,
            'purchase' => $purchases,
        ]);
    }
    /**
     * Display the specified resource.
     */
    public function show(ProductPurchase $productPurchase)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProductPurchase $productPurchase)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        //
        try{
            $transaction = Transaction::where('transaction_id',$request->transaction_id)->first();
            $product = ProductPurchase::Find($request->id);

            if($request->status==1){
                $product->status=1;
                $transaction->amount= $transaction->amount;
                $transaction->status=$request->status;
            }else{
                $product->status=$request->status;
                $transaction->status=3;//cancelled
            }
            $product->update();
            $transaction->update();

            return response()->json([
                'status' => true,
                'message' => "Product purchase updated. Success!",
            ]); 
        }catch(Exception $e){
            return response()->json([
                'status' => false,
                'message' =>"Update Failed",
            ]); 
        }
       
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductPurchase $productPurchase)
    {
        //
    }
}
