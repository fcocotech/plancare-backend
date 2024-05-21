<?php

namespace App\Http\Controllers;

use App\Models\ProductPurchase;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

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
    public function get(Request $request) {
        $user = Auth::user();

        if($user->role_id==1){
            $purchasesQuery = ProductPurchase::with(["purchasedby","product.category"])->where('purchase_type',2);
        }else{
            $purchasesQuery = ProductPurchase::with(["purchasedby","product.category"])->where('purchased_by', $user->id)->where('purchase_type',2);
        }

        $categoryId = $request->category_id;
        if ($categoryId != 0) {
            $purchasesQuery->whereHas('product.category', function ($query) use ($categoryId) {
                $query->where('id', $categoryId);
            });
        }

        $purchases = $purchasesQuery->get();

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
        $user = Auth::user();
        $purchasedby = $request->purchasedby;
        try{
            $transaction = Transaction::where('transaction_id',$request->transaction_id)->first();
            $product = ProductPurchase::Find($request->id);

            if($transaction!=null){
                if($request->status==1){
                    $product->status=1;
                    $transaction->amount= $transaction->amount;
                    $transaction->status=$request->status;
                }else{
                    $product->status=$request->status;
                    $transaction->status=3;//cancelled
                    $transaction->update();
                    $transaction->delete();
                }
                $product->update();
                
                if($request->status==1){
                    Mail::send('emails.product-status.approved', [
                        'name' => $purchasedby["name"],
                        'trans_no' => $product->transaction_id,
                        'amount_to_withdraw' => $product['amount'],
                        'product' => $request,
                    ], function ($message) use ($purchasedby, $product) {
    
                        $message->to($purchasedby["email"])->subject('Purchase Product is approved with Transaction ID: '.$product->transaction_id);
                    });
                }else{
                    Mail::send('emails.product-status.cancelled', [
                        'name' => $purchasedby["name"],
                        'trans_no' => $product->transaction_id,
                        'amount_to_withdraw' => $product['amount'],
                        'product' => $request,
                    ], function ($message) use ($purchasedby, $product) {
    
                        $message->to($purchasedby["email"])->subject('Purchase Product is cancelled with Transaction ID: '.$product->transaction_id);
                    });
                }

                

            }
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
