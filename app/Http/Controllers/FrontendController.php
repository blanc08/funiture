<?php

namespace App\Http\Controllers;

use Midtrans\Snap;
use App\Models\Cart;
use Midtrans\Config;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\CheckoutRequest;

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

    public function cartDelete(Request $request, $id)
    {
        $item = Cart::findOrFail($id);
        $item->delete();

        return redirect('cart');
    }

    public function checkout(CheckoutRequest $request)
    {
        $data = $request->all();

        // Get Cart Data
        $carts = Cart::with(['product'])->where('user_id', Auth::user()->id)->get();

        // add to transaction database
        $data['user_id'] = Auth::user()->id;
        $data['total_price'] = $carts->sum('product.price');

        // create transaction
        $transaction = Transaction::create($data);

        // Create transaction item
        foreach ($carts as $cart) {
            $items[] = TransactionItem::create([
                'transaction_id' => $transaction->id,
                'user_id' => $cart->user_id,
                'product_id' => $cart->product_id,
            ]);
        }

        // Delete Cart after transaction
        Cart::where('user_id', Auth::user()->id)->delete();

        // Konfigurasi midtrans
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized = config('services.midtrans.isSanitized');
        Config::$is3ds = config('services.midtrans.is3ds');

        // Setup variable midtrans
        $midtrans = [
            'transaction_details' => [
                'order_id' => 'LUX-' . $transaction->id,
                'gross_amount' => $transaction->total_price,
            ],
            'costumer_details' => [
                'first_name' => $transaction->name,
                'email' => $transaction->email,
            ],
            'enabled_payments' => ['gopay', 'bank_transfer'],
            'vtweb' => [],
        ];

        // Payment Proccess
        try {

            // Get Snap Payment Page Url
            $paymentUrl = Snap::createTransaction($midtrans)->redirect_url;

            // Save to database
            $transaction->payment_url = $paymentUrl;
            $transaction->save();

            // Redirect
            return redirect($paymentUrl);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function success(Request $request)
    {
        return view('pages.frontend.success');
    }
}
