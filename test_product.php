<?php

require_once 'vendor/autoload.php';

use App\Models\Product;

try {
    echo "Testing Product model...\n";
    $product = new Product();
    echo "Product model loaded successfully!\n";
    
    // Test if we can query products
    $count = Product::count();
    echo "Total products in database: " . $count . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

