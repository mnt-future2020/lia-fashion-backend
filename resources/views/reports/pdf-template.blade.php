<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #eb1c75;
            padding-bottom: 20px;
        }
        
        .company-name {
            font-size: 28px;
            font-weight: bold;
            color: #eb1c75;
            margin-bottom: 5px;
        }
        
        .report-title {
            font-size: 22px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .report-info {
            font-size: 14px;
            color: #666;
        }
        
        .summary-section {
            margin: 30px 0;
        }
        
        .summary-title {
            font-size: 18px;
            font-weight: bold;
            color: #eb1c75;
            margin-bottom: 15px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .summary-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #eb1c75;
        }
        
        .summary-card h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #666;
        }
        
        .summary-card .value {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 12px;
        }
        
        .data-table th,
        .data-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .data-table th {
            background-color: #eb1c75;
            color: white;
            font-weight: bold;
        }
        
        .data-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        
        .page-break {
            page-break-after: always;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .status-in-stock {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-low-stock {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-out-of-stock {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">{{ $company }}</div>
        <div class="report-title">{{ $title }}</div>
        <div class="report-info">
            Generated on: {{ $date }} | Period: Last {{ $dateRange }}
        </div>
    </div>

    @if(isset($data['dashboard_stats']) && is_array($data['dashboard_stats']))
        <div class="summary-section">
            <div class="summary-title">Key Performance Metrics</div>
            <div class="summary-grid">
                <div class="summary-card">
                    <h4>Total Orders</h4>
                    <div class="value">{{ number_format($data['dashboard_stats']['total_orders'] ?? 0) }}</div>
                </div>
                <div class="summary-card">
                    <h4>Total Revenue</h4>
                    <div class="value">₹{{ number_format($data['dashboard_stats']['total_revenue'] ?? 0) }}</div>
                </div>
                <div class="summary-card">
                    <h4>Average Order Value</h4>
                    <div class="value">₹{{ number_format($data['dashboard_stats']['average_order_value'] ?? 0) }}</div>
                </div>
                <div class="summary-card">
                    <h4>New Customers</h4>
                    <div class="value">{{ number_format($data['dashboard_stats']['new_customers'] ?? 0) }}</div>
                </div>
            </div>
        </div>
    @endif

    @if(isset($data['summary']) && is_array($data['summary']))
        <div class="summary-section">
            <div class="summary-title">Report Summary</div>
            <div class="summary-grid">
                @foreach($data['summary'] as $key => $value)
                    <div class="summary-card">
                        <h4>{{ ucwords(str_replace('_', ' ', $key)) }}</h4>
                        <div class="value">
                            @if(is_numeric($value))
                                {{ number_format($value, 2) }}
                            @else
                                {{ $value ?? 'N/A' }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if(isset($data['products']) && is_array($data['products']) && count($data['products']) > 0)
        <div class="summary-section">
            <div class="summary-title">Product Details</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>SKU</th>
                        @if(isset($data['products'][0]['size']))
                            <th>Size</th>
                        @endif
                        @if(isset($data['products'][0]['stock_quantity']))
                            <th>Stock</th>
                            <th>Status</th>
                        @endif
                        @if(isset($data['products'][0]['selling_value']))
                            <th>Value</th>
                        @endif
                        @if(isset($data['products'][0]['total_sold']))
                            <th>Sold</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach(array_slice($data['products'], 0, 50) as $product)
                        @if(is_array($product))
                            <tr>
                                <td>{{ $product['product_name'] ?? $product['name'] ?? 'N/A' }}</td>
                                <td>{{ $product['sku_code'] ?? 'N/A' }}</td>
                                @if(isset($product['size']))
                                    <td>{{ $product['size'] }}</td>
                                @endif
                                @if(isset($product['stock_quantity']))
                                    <td>{{ $product['stock_quantity'] }}</td>
                                    <td>
                                        <span class="status-badge 
                                            @if(($product['status'] ?? '') == 'In Stock') status-in-stock
                                            @elseif(($product['status'] ?? '') == 'Low Stock') status-low-stock
                                            @else status-out-of-stock
                                            @endif">
                                            {{ $product['status'] ?? 'Unknown' }}
                                        </span>
                                    </td>
                                @endif
                                @if(isset($product['selling_value']))
                                    <td>₹{{ number_format($product['selling_value']) }}</td>
                                @endif
                                @if(isset($product['total_sold']))
                                    <td>{{ $product['total_sold'] }}</td>
                                @endif
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if(isset($data['suppliers']) && is_array($data['suppliers']) && count($data['suppliers']) > 0)
        <div class="summary-section">
            <div class="summary-title">Supplier Information</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Supplier Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Total Purchases</th>
                        <th>Purchase Value</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(array_slice($data['suppliers'], 0, 30) as $supplier)
                        @if(is_array($supplier))
                            <tr>
                                <td>{{ $supplier['supplier_name'] ?? 'N/A' }}</td>
                                <td>{{ $supplier['email'] ?? 'N/A' }}</td>
                                <td>{{ $supplier['phone'] ?? 'N/A' }}</td>
                                <td>{{ $supplier['total_purchases'] ?? 0 }}</td>
                                <td>₹{{ number_format($supplier['total_purchase_value'] ?? 0) }}</td>
                                <td>{{ $supplier['status'] ?? 'Unknown' }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="footer">
        <p>This report was generated automatically by {{ $company }} reporting system.</p>
        <p>For questions or support, please contact your system administrator.</p>
    </div>
</body>
</html>