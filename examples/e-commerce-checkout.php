<?php

/**
 * E-Commerce Checkout Example
 *
 * Real-world example showing request correlation and performance tracing
 * in an e-commerce checkout flow.
 */

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Http, Log, Mail};
use App\Mail\OrderConfirmation;

class CheckoutController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    public function process(Request $request)
    {
        // request_id automatically generated: 550e8400-e29b-41d4-a716-446655440000

        Log::info('Checkout started', [
            'cart_total' => $request->total,
            'item_count' => count($request->cart_items),
            'user_id' => $request->user()->id,
        ]);

        // ═══════════════════════════════════════════════════════
        // WITH AUTOMATIC TRACING (Recommended)
        // ═══════════════════════════════════════════════════════
        // Just enable auto-tracing in .env:
        // GLOBALLOG_AUTO_TRACING_ENABLED=true

        // This HTTP request is automatically traced!
        $inventoryCheck = Http::post('https://inventory.example.com/check', [
            'items' => $request->cart_items,
        ]);

        if (!$inventoryCheck->successful()) {
            Log::error('Inventory check failed', [
                'status' => $inventoryCheck->status(),
                'items' => $request->cart_items,
            ]);

            return response()->json(['error' => 'Items out of stock'], 400);
        }

        // Database query - automatically traced!
        $order = Order::create([
            'user_id' => $request->user()->id,
            'items' => $request->cart_items,
            'total' => $request->total,
            'status' => 'pending',
        ]);

        Log::info('Order created', [
            'order_id' => $order->id,
            'total' => $order->total,
        ]);

        // ═══════════════════════════════════════════════════════
        // MANUAL TRACING (For custom operations)
        // ═══════════════════════════════════════════════════════

        // Start tracing payment processing
        $paymentTrace = Log::startTrace('payment.stripe.charge', [
            'amount' => $request->total,
            'order_id' => $order->id,
        ]);

        try {
            $payment = $this->paymentService->charge(
                $request->user(),
                $request->total,
                $request->payment_method
            );

            // End trace with success metadata
            Log::endTrace($paymentTrace, [
                'charge_id' => $payment->id,
                'status' => $payment->status,
                'success' => true,
            ]);

            Log::info('Payment successful', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
            ]);
        } catch (\Exception $e) {
            // End trace with error metadata
            Log::endTrace($paymentTrace, [
                'error' => $e->getMessage(),
                'success' => false,
            ]);

            Log::error('Payment failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            // Update order status
            $order->update(['status' => 'failed']);

            return response()->json(['error' => 'Payment failed'], 400);
        }

        // Update order status
        $order->update(['status' => 'completed']);

        // Send confirmation email - automatically traced!
        Mail::to($request->user())->send(new OrderConfirmation($order));

        Log::info('Checkout completed', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'total' => $order->total,
            'duration_ms' => $this->getCheckoutDuration(),
        ]);

        return response()->json([
            'order_id' => $order->id,
            'status' => 'completed',
        ]);
    }

    private function getCheckoutDuration(): int
    {
        // Calculate duration from first log
        return 850; // Example
    }
}

/*
What you see in CloudWatch Logs:

[
  {
    "message": "Checkout started",
    "request_id": "550e8400...",
    "timestamp": "2025-01-17T10:30:00Z",
    "cart_total": 99.99
  },
  {
    "message": "Trace completed: http.request",
    "request_id": "550e8400...",
    "duration_ms": 120,
    "type": "trace",
    "url": "inventory.example.com"
  },
  {
    "message": "Order created",
    "request_id": "550e8400...",
    "order_id": "ORD-123"
  },
  {
    "message": "Trace completed: payment.stripe.charge",
    "request_id": "550e8400...",
    "duration_ms": 340,
    "type": "trace",
    "charge_id": "ch_123"
  },
  {
    "message": "Payment successful",
    "request_id": "550e8400...",
    "payment_id": "pay_123"
  },
  {
    "message": "Checkout completed",
    "request_id": "550e8400...",
    "order_id": "ORD-123"
  }
]

Search all logs from this checkout:
{ $.request_id = "550e8400..." }

What you see in AWS X-Ray:

POST /api/checkout (850ms)
├─ http.request (120ms) ← Automatic
│  └─ POST inventory.example.com
├─ database.insert (45ms) ← Automatic
│  └─ INSERT orders
├─ payment.stripe.charge (340ms) ← Manual
│  └─ Stripe API
└─ mail.send (95ms) ← Automatic
   └─ Send OrderConfirmation
*/
