<?php

namespace App\Http\Controllers\API;

use Midtrans\Config;
use Midtrans\Notification;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Transaction;

class MidtransController extends Controller
{
    public function callback()
    {
        // Set Konfigurasi midtrans
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized = config('services.midtrans.isSanitized');
        Config::$is3ds = config('services.midtrans.is3ds');

        // Buat instance midtrans notification
        $notification = new Notification();

        // Assign ke variable untuk memudahkan coding
        $status = $notification->transaction_status;
        $type = $notification->payment_type;
        $fraud = $notification->fraud_status;
        $order_id = $notification->order_id;

        // Get transaction id
        $order = explode('-', $order_id);

        // Mencari transaksi berdasarkan id
        $transaction = Transaction::findOrFail($order[1]);

        // Handle notification status midtrans
        if ($status == 'capture') {
            if ($type == 'credit_card') {
                if ($fraud == 'challenge') {
                    $transaction->status = 'PENDING';
                } else {
                    $transaction->status = 'SUCCESS';
                }
            }
        } elseif ($status == 'settlement') {
            $transaction->status = 'SUCCESS';
        } elseif ($status == 'pending') {
            $transaction->status = 'PENDING';
        } elseif ($status == 'deny') {
            $transaction->status = 'PENDING';
        } elseif ($status == 'expire') {
            $transaction->status = 'CANCELLED';
        } elseif ($status == 'cancell') {
            $transaction->status = 'CANCELLED';
        }

        // Simpan transaksi
        $transaction->save();

        // Return respone untuk midtrans
        return response()->json([
            'meta' => [
                'code' => 200,
                'message' => 'Midtrans Notification Success',
            ],
        ]);
    }
}
