<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FrontendController extends Controller
{

    public function index(Request $request)
    {
        $products = Product::with(['galleries'])->latest()->get();

        return view('pages.frontend.index', compact('products'));
    }

    public function detail(Request $request, $slug)
    {

        $product = Product::with(['galleries'])->where('slug', $slug)->firstOrFail();

        $recommendations = Product::with(['galleries'])->inRandomOrder()->limit(4)->get();

        return view('pages.frontend.detail', compact('product', 'recommendations'));
    }

    public function cart(Request $request)
    {
        $carts = Cart::with(['product.galleries'])->where('user_id', Auth::user()->id)->get();

        return view('pages.frontend.cart', compact('carts'));
    }

    public function cardAdd(Request $request, $id)
    {

        Cart::create([
            'user_id' => Auth::user()->id,
            'product_id' => $id
        ]);

        return redirect('cart');
    }

    public function cardDelete(Request $request, $id)
    {
        $item = Cart::findOrFail($id);
        $item->delete();

        return redirect('cart');
    }

    public function success(Request $request)
    {
        return view('pages.frontend.success');
    }
}
