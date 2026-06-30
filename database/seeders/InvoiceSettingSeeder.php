<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InvoiceSetting;
use Carbon\Carbon;

class InvoiceSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default invoice settings
        InvoiceSetting::create([
            'prefix' => 'CXI',
            'financial_year_start' => Carbon::parse('2024-04-01'),
            'financial_year_end' => Carbon::parse('2025-03-31'),
            'last_invoice_number' => 'CXI25260001',
            'last_sequence_number' => 1,
            'is_active' => true,
        ]);
    }
}
