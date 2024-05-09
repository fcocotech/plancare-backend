<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductCategoryController extends Controller
{
    
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
