<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\{File};
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{

    public function show(Request $request) {
        $products = Product::get();
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
    
            // Handle multiple file uploads
            $fileUrls = [];
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $filePath = $file->store('public/images/products');
                    $fileUrls[] = env('APP_URL', 'https://apinew.plancareph.com').Storage::url($filePath);
                }
            }

            // Save the comma-separated string of file URLs to the product
            if (!empty($fileUrls)) {
                $product->photo_url = implode(',', $fileUrls);
            }

            $product->save();
    
            return [
                "status" => true,
                "message" => 'Product Saved!',
                "fileUrls" => $fileUrls,
                "files" => $request->files 
            ];
        } catch (\Exception $e) {
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

        $product->name = $request->name ?? '';
        $product->description = $request->description ?? '';
        $product->price = $request->price ?? 0;
        $product->photo_url = $request->photo_url;
        // Handle new file uploads
        $fileUrls = explode(',', $product->photo_url); // Existing file URLs

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                // Add timestamp to avoid overwriting
                $originalFileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('public/images/products', $originalFileName);
                $fileUrls[] = env('APP_URL', 'https://apinew.plancareph.com') . Storage::url($filePath);
            }
        }

        if (!empty($fileUrls)) {
            $product->photo_url = implode(',', $fileUrls);
        }


        $product->update();
        return response()->json([
            'status' => true,
            'message' => 'Product update successful!',
            'product' => $request->name
        ]);
    }

}
