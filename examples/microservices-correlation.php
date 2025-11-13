<?php

/**
 * Microservices Correlation Example
 *
 * Shows how to pass request_id between services for complete request tracing
 * across your microservices architecture.
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Http, Log};

// ═══════════════════════════════════════════════════════
// SERVICE A: API Gateway (receives external request)
// ═══════════════════════════════════════════════════════

class ApiGatewayController extends Controller
{
    public function handleRequest(Request $request)
    {
        // GlobalLogger automatically generates request_id: 550e8400...
        $requestId = Log::getContextManager()->getRequestId();

        Log::info('Request received at API Gateway', [
            'endpoint' => $request->path(),
            'method' => $request->method(),
        ]);

        // Pass request_id to Service B
        $userServiceResponse = Http::withHeaders([
            'X-Request-ID' => $requestId, // <-- Pass request_id!
        ])->post('http://user-service/api/validate', [
            'user_token' => $request->bearerToken(),
        ]);

        if (!$userServiceResponse->successful()) {
            Log::error('User validation failed', [
                'status' => $userServiceResponse->status(),
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Pass request_id to Service C
        $orderServiceResponse = Http::withHeaders([
            'X-Request-ID' => $requestId, // <-- Pass request_id!
        ])->post('http://order-service/api/orders', [
            'user_id' => $userServiceResponse->json('user_id'),
            'items' => $request->items,
        ]);

        Log::info('Request completed at API Gateway', [
            'order_id' => $orderServiceResponse->json('order_id'),
            'status' => $orderServiceResponse->status(),
        ]);

        return $orderServiceResponse->json();
    }
}

// ═══════════════════════════════════════════════════════
// SERVICE B: User Service (validates users)
// ═══════════════════════════════════════════════════════

class UserValidationController extends Controller
{
    public function validate(Request $request)
    {
        // GlobalLogger automatically picks up request_id from X-Request-ID header!
        // Now all logs use the same request_id: 550e8400...

        Log::info('User validation started', [
            'has_token' => $request->has('user_token'),
        ]);

        $user = $this->validateToken($request->user_token);

        if (!$user) {
            Log::warning('Invalid user token', [
                'token_prefix' => substr($request->user_token, 0, 10),
            ]);

            return response()->json(['error' => 'Invalid token'], 401);
        }

        Log::info('User validated successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return response()->json([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }
}

// ═══════════════════════════════════════════════════════
// SERVICE C: Order Service (creates orders)
// ═══════════════════════════════════════════════════════

class OrderCreationController extends Controller
{
    public function create(Request $request)
    {
        // GlobalLogger automatically uses same request_id: 550e8400...
        // All services now share the same request_id!

        Log::info('Order creation started', [
            'user_id' => $request->user_id,
            'item_count' => count($request->items),
        ]);

        // Call inventory service with same request_id
        $requestId = Log::getContextManager()->getRequestId();
        $inventoryResponse = Http::withHeaders([
            'X-Request-ID' => $requestId, // <-- Keep passing it!
        ])->post('http://inventory-service/api/check', [
            'items' => $request->items,
        ]);

        $order = Order::create([
            'user_id' => $request->user_id,
            'items' => $request->items,
            'total' => $this->calculateTotal($request->items),
        ]);

        Log::info('Order created successfully', [
            'order_id' => $order->id,
            'total' => $order->total,
        ]);

        return response()->json([
            'order_id' => $order->id,
            'status' => 'created',
        ]);
    }
}

// ═══════════════════════════════════════════════════════
// SERVICE D: Inventory Service (checks stock)
// ═══════════════════════════════════════════════════════

class InventoryController extends Controller
{
    public function check(Request $request)
    {
        // Still using the same request_id: 550e8400...

        Log::info('Inventory check started', [
            'item_count' => count($request->items),
        ]);

        foreach ($request->items as $item) {
            $available = $this->checkStock($item['sku']);

            if (!$available) {
                Log::warning('Item out of stock', [
                    'sku' => $item['sku'],
                    'requested_qty' => $item['quantity'],
                ]);

                return response()->json(['error' => 'Out of stock'], 400);
            }
        }

        Log::info('Inventory check passed', [
            'items_checked' => count($request->items),
        ]);

        return response()->json(['available' => true]);
    }
}

/*
═══════════════════════════════════════════════════════
SEARCHING ACROSS ALL SERVICES
═══════════════════════════════════════════════════════

In CloudWatch (with log groups for each service):

Search query:
{ $.request_id = "550e8400-e29b-41d4-a716-446655440000" }

Results across ALL services:

[API Gateway]
10:30:00.100 | Request received at API Gateway

[User Service]
10:30:00.150 | User validation started

[User Service]
10:30:00.200 | User validated successfully

[Order Service]
10:30:00.250 | Order creation started

[Inventory Service]
10:30:00.300 | Inventory check started

[Inventory Service]
10:30:00.350 | Inventory check passed

[Order Service]
10:30:00.400 | Order created successfully

[API Gateway]
10:30:00.450 | Request completed at API Gateway

You can now see the COMPLETE journey across all services!
*/

/*
═══════════════════════════════════════════════════════
BEST PRACTICES
═══════════════════════════════════════════════════════

1. ALWAYS pass X-Request-ID header between services
2. Use the same header name across all services
3. Configure middleware to pick up external request_id:

   // config/globallogger.php
   'request_id' => [
       'header' => 'X-Request-ID',
   ]

4. Include request_id in error responses:

   return response()->json([
       'error' => 'Something failed',
       'request_id' => Log::getContextManager()->getRequestId(),
   ], 500);

5. Add request_id to async jobs:

   dispatch(new ProcessOrder($order))->withHeader(
       'X-Request-ID',
       Log::getContextManager()->getRequestId()
   );
*/
