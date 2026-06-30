<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Log;

class VerifyPendingPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:verify-pending {--payment-id= : Verify specific payment ID} {--bulk : Verify all pending payments}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify pending Razorpay payments and update their status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $paymentService = new PaymentService();

        if ($paymentId = $this->option('payment-id')) {
            $this->info("Verifying specific payment: {$paymentId}");
            
            try {
                $result = $paymentService->verifyAndUpdatePayment($paymentId);
                
                if ($result['status'] === 'success') {
                    $this->info("✅ Payment verified successfully: {$paymentId}");
                    $this->line("Status: {$result['payment_status']}");
                    $this->line("Amount: {$result['amount']} {$result['currency']}");
                } elseif ($result['status'] === 'pending') {
                    $this->warn("⏳ Payment not yet captured: {$paymentId}");
                    $this->line("Status: {$result['payment_status']}");
                } else {
                    $this->error("❌ Payment verification failed: {$paymentId}");
                    $this->line("Error: {$result['message']}");
                }
            } catch (\Exception $e) {
                $this->error("❌ Error verifying payment: {$e->getMessage()}");
                Log::error("Command error verifying payment {$paymentId}: " . $e->getMessage());
            }
        } elseif ($this->option('bulk')) {
            $this->info("Verifying all pending payments...");
            
            try {
                $results = $paymentService->verifyPendingPayments();
                
                $successCount = 0;
                $errorCount = 0;
                $pendingCount = 0;
                
                foreach ($results as $result) {
                    $paymentId = $result['payment_id'];
                    $status = $result['result']['status'];
                    
                    if ($status === 'success') {
                        $this->info("✅ Payment verified: {$paymentId}");
                        $successCount++;
                    } elseif ($status === 'pending') {
                        $this->warn("⏳ Payment pending: {$paymentId}");
                        $pendingCount++;
                    } else {
                        $this->error("❌ Payment failed: {$paymentId} - {$result['result']['message']}");
                        $errorCount++;
                    }
                }
                
                $this->newLine();
                $this->info("📊 Verification Summary:");
                $this->line("Total processed: " . count($results));
                $this->line("✅ Success: {$successCount}");
                $this->line("⏳ Pending: {$pendingCount}");
                $this->line("❌ Errors: {$errorCount}");
                
            } catch (\Exception $e) {
                $this->error("❌ Error during bulk verification: {$e->getMessage()}");
                Log::error("Command error during bulk verification: " . $e->getMessage());
            }
        } else {
            $this->info("Getting pending payments...");
            
            try {
                $pendingPayments = $paymentService->getPendingPayments();
                
                if ($pendingPayments->isEmpty()) {
                    $this->info("🎉 No pending payments found!");
                } else {
                    $this->info("Found {$pendingPayments->count()} pending payment(s):");
                    
                    $headers = ['ID', 'Order ID', 'Payment ID', 'Amount', 'Status', 'Created At', 'Customer'];
                    $rows = [];
                    
                    foreach ($pendingPayments as $payment) {
                        $rows[] = [
                            $payment->id,
                            $payment->razorpay_order_id,
                            $payment->razorpay_payment_id ?? 'N/A',
                            $payment->amount,
                            $payment->status,
                            $payment->created_at->format('Y-m-d H:i:s'),
                            $payment->transaction->customer_name ?? 'N/A'
                        ];
                    }
                    
                    $this->table($headers, $rows);
                    
                    $this->newLine();
                    $this->info("To verify all pending payments, run:");
                    $this->line("php artisan payments:verify-pending --bulk");
                    
                    $this->info("To verify a specific payment, run:");
                    $this->line("php artisan payments:verify-pending --payment-id=pay_xxxxx");
                }
                
            } catch (\Exception $e) {
                $this->error("❌ Error getting pending payments: {$e->getMessage()}");
                Log::error("Command error getting pending payments: " . $e->getMessage());
            }
        }
    }
} 