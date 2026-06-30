<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RazorpayWebhookController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function handle(Request $request)
    {
        try {
            Log::info('Razorpay webhook received', [
                'event' => $request->input('event'),
                'headers' => $request->headers->all()
            ]);

            // Get webhook signature from headers
            $webhookSignature = $request->header('x-razorpay-signature');

            if (!$webhookSignature) {
                Log::error('Webhook signature missing');
                return response()->json([
                    'status' => 'error',
                    'message' => 'Webhook signature missing'
                ], 400);
            }

            // Process webhook
            $result = $this->paymentService->processWebhook(
                $request->all(),
                $webhookSignature
            );

            if ($result['status'] === 'error') {
                return response()->json($result, 400);
            }

            return response()->json($result, 200);

        } catch (\Exception $e) {
            Log::error('Webhook handling failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Webhook processing failed'
            ], 500);
        }
    }
}
