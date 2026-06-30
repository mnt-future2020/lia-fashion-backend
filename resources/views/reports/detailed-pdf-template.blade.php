<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            margin: 20px;
            color: #333;
            line-height: 1.4;
            font-size: 12px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #eb1c75;
            padding-bottom: 20px;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #eb1c75;
            margin-bottom: 5px;
        }
        
        .report-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .report-info {
            font-size: 11px;
            color: #666;
        }
        
        .content {
            margin: 20px 0;
        }
        
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #eb1c75;
            margin: 20px 0 10px 0;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 10px;
        }
        
        .data-table th,
        .data-table td {
            border: 1px solid #ddd;
            padding: 4px;
            text-align: left;
            word-wrap: break-word;
        }
        
        .data-table th {
            background-color: #eb1c75;
            color: white;
            font-weight: bold;
            font-size: 9px;
        }
        
        .data-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .summary-section {
            margin-top: 30px;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #eb1c75;
        }
        
        .summary-title {
            font-size: 14px;
            font-weight: bold;
            color: #eb1c75;
            margin-bottom: 10px;
        }
        
        .summary-item {
            margin: 5px 0;
            display: flex;
            justify-content: space-between;
        }
        
        .summary-key {
            font-weight: bold;
            color: #333;
        }
        
        .summary-value {
            color: #eb1c75;
            font-weight: bold;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
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

    <div class="content">
        @if(!empty($mainData))
            <div class="section-title">Report Data</div>
            <table class="data-table">
                <thead>
                    <tr>
                        @if(isset($mainData[0]) && is_array($mainData[0]))
                            @foreach(array_keys($mainData[0]) as $header)
                                <th>{{ ucwords(str_replace('_', ' ', $header)) }}</th>
                            @endforeach
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($mainData as $row)
                        @if(is_array($row))
                            <tr>
                                @foreach($row as $cell)
                                    <td>
                                        @if(is_array($cell))
                                            {{ json_encode($cell) }}
                                        @elseif(is_string($cell) || is_numeric($cell))
                                            {{ $cell ?: 'N/A' }}
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="no-data">
                No data available for the selected period.
            </div>
        @endif

        @if(!empty($summaryData))
            <div class="summary-section">
                <div class="summary-title">Summary</div>
                @foreach($summaryData as $key => $value)
                    <div class="summary-item">
                        <span class="summary-key">{{ $key }}:</span>
                        <span class="summary-value">
                            @if(is_array($value))
                                {{ json_encode($value) }}
                            @elseif(is_string($value) || is_numeric($value))
                                {{ $value }}
                            @else
                                N/A
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="footer">
        <p>This report was generated automatically by {{ $company }} reporting system.</p>
        <p>Report contains {{ count($mainData) }} records with summary statistics.</p>
    </div>
</body>
</html>