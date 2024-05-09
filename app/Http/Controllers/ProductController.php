<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\{File};

class ProductController extends Controller
{

    public function show(Request $request) {
        $products = Product::with(['category'])->get();
        return response()->json([
            'status' => true,
            'products' => $products,
        ]);
    }

    public function activeProducts(Request $request) {
        $products = Product::where('is_active', 1)->get();
        return response()->json([
            'status' => true,
            'products' => $products,
        ]);
    }

    public function create(Request $request) {
        try {
            $user = Auth::user();

            $product = new Product;
            $product->name = $request->name;
            $product->description = $request->description;
            $product->price = $request->price;
            $product->is_active = 1;
            $product->is_shop_active = 1;
            $product->category_id = $request->category_id;

            if($request->has('product_image') && $request->product_image != ''){
                $proof_image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $request->product_image));
                $proof_path = storage_path('app/public/images/products/');
                if(!File::isDirectory($proof_path)){
                    File::makeDirectory($proof_path, 0777, true, true);
                }
                $proof_name = str_replace(' ', '_', $product->name).'.png';
                file_put_contents($proof_path.$proof_name, $proof_image);
                $product->photo_url = env('APP_URL', '') . '/storage/images/products/'.$proof_name;
            }

            $product->save();
            return [
                "status" => true,
                "message" => 'Product Saved!'
            ];
        } catch(\Exception $e){
            return [
                "status" => false,
                "message" => $e->getMessage()
            ];
        }
    }

    public function delete(Request $request, $product_id) {
        $product = Product::find($product_id);
        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found!',
            ], 404);
        }

        // Delete the product
        $product->delete();

        return response()->json([
            'status' => true,
            'message' => 'Product deleted successfully!',
        ]);
    }

    public function updateStatus(Request $request, $product_id) {
        $product = Product::find($product_id);
        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found!',
            ], 404);
        }
        if($request->has('is_active')){
            $product->is_active = $request->is_active;
        }
        if($request->has('is_shop_active')){
            $product->is_shop_active = $request->is_shop_active;
        }
        $product->update();
        return response()->json([
            'status' => true,
            'message' => 'Product update successful!',
        ]);
    }

    public function update(Request $request, $product_id) {
        $product = Product::find($product_id);
        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found!',
            ], 404);
        }

        if($product->name != $request->name){
            $product->name = $request->name;
        }
        if($product->description != $request->description){
            $product->description = $request->description;
        }
        if($product->price != $request->price){
            $product->price = $request->price;
        }
        if($product->category_id != $request->category_id){
            $product->category_id = $request->category_id;
        }
        
        if($request->has('product_image') && $request->product_image != ''){
            $proof_image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $request->product_image));
            $proof_path = storage_path('app/public/images/products/');
            if(!File::isDirectory($proof_path)){
                File::makeDirectory($proof_path, 0777, true, true);
            }
            $proof_name = str_replace(' ', '_', $product->name).'.png';
            file_put_contents($proof_path.$proof_name, $proof_image);
            $product->photo_url = env('APP_URL', '') . '/storage/images/products/'.$proof_name;
        }


        $product->update();
        return response()->json([
            'status' => true,
            'message' => 'Product update successful!',
        ]);
    }

}
