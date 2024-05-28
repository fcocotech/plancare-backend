<?php

namespace App\Http\Controllers;

use App\Models\{ProductCategory, ProductPurchase, Transaction};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductCategoryController extends Controller
{
    public function get(Request $request) {
        $user = Auth::user();

        $categories = ProductCategory::query();
        if($request->category_id != 0){
            $categories->where('id', $request->category_id);
        }
        $categories = $categories->get();
        
        $results = [];
        foreach ($categories as $category) {
            $user = Auth::user();
            $userIds = [$user->id];

            if($user->id == 1){
                $userIds = ProductPurchase::whereHas('product', function ($query) use ($category) {
                    $query->where('category_id', $category->id);
                })->pluck('purchased_by');
            } else {
                $userIds = ProductPurchase::whereHas('product', function ($query) use ($category) {
                    $query->where('category_id', $category->id);
                })->where('purchased_by', $user->id)->pluck('purchased_by'); 
            }
            
        
            $totalAccumulatedPoints = Transaction::whereIn('user_id', $userIds)
                ->where('trans_type', '2')
                ->whereNotIn('withdrawable', [2, 3, 4, 5])
                ->sum('amount');
        
            $totalWithdrawable = Transaction::whereIn('user_id', $userIds)
                ->where('trans_type', '2')
                ->where('withdrawable', 1)
                ->sum('amount');

            $totalWithdrawalRequests = Transaction::whereIn('user_id', $userIds)
                ->where('trans_type', '3')
                ->whereNot('withdrawable',5)
                ->sum('amount');
        
            $totalPointsPurchase = Transaction::where('trans_type', '4')
                ->whereIn('user_id', $userIds)
                ->where('payment_method', 6)
                ->whereIn('status', [0,1])
                ->sum('amount');
            
            
            $results[] = [
                'name' => $category->name,
                'total_accumulated_points' => $totalAccumulatedPoints,
                'total_withdrawals' => $totalWithdrawable - ($totalWithdrawalRequests + $totalPointsPurchase),
                'total_balance' => ($totalWithdrawable - $totalWithdrawalRequests)
            ];
        }

        return response()->json([
            'status' => true,
            'categories' => $results,
        ]);
    }

    public function show(Request $request) {
        $categories = ProductCategory::get();
        return response()->json([
            'status' => true,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {   
        try {
            $user = Auth::user();
            $productCategory = new ProductCategory;
            $productCategory->name = $request->name;
            $productCategory->save();

            return [
                "status" => true,
                "message" => 'Product Category Saved!'
            ];
        } catch(\Exception $e){
            return [
                "status" => false,
                "message" => $e->getMessage()
            ];
        }
    }

    public function update(Request $request, $id) {
        $productCategory = ProductCategory::find($id);
        if (!$productCategory) {
            return response()->json([
                'status' => false,
                'message' => 'Product category not found!',
            ], 404);
        }

        if($productCategory->name != $request->name){
            $productCategory->name = $request->name;
        }

        $productCategory->update();
        return response()->json([
            'status' => true,
            'message' => 'Product category update successful!',
        ]);

    }

    public function delete(Request $request, $id) {
        $productCategory = ProductCategory::find($id);
        if (!$productCategory) {
            return response()->json([
                'status' => false,
                'message' => 'Product Category not found!',
            ], 404);
        }
        $productCategory->delete();
        return response()->json([
            'status' => true,
            'message' => 'Product Category deleted successfully!',
        ]);
    }


}
