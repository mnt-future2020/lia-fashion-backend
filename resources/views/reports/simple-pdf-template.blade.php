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
            line-height: 1.6;
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
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .report-info {
            font-size: 12px;
            color: #666;
        }
        
        .content {
            margin: 20px 0;
        }
        
        .section-title {
            font-size: 16px;
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
            font-size: 11px;
        }
        
        .data-table th,
        .data-table td {
            border: 1px solid #ddd;
            padding: 6px;
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
        
        .summary-item {
            margin: 10px 0;
            padding: 10px;
            background: #f8f9fa;
            border-left: 4px solid #eb1c75;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 15px;
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
        @if(is_array($data))
            @foreach($data as $key => $value)
                @if($key === 'summary' && is_array($value))
                    <div class="section-title">Summary</div>
                    @foreach($value as $summaryKey => $summaryValue)
                        <div class="summary-item">
                            <strong>{{ ucwords(str_replace('_', ' ', $summaryKey)) }}:</strong> 
                            @if(is_array($summaryValue))
                                {{ json_encode($summaryValue) }}
                            @elseif(is_numeric($summaryValue))
                                {{ number_format($summaryValue, 2) }}
                            @elseif(is_string($summaryValue) || is_numeric($summaryValue))
                                {{ $summaryValue }}
                            @else
                                N/A
                            @endif
                        </div>
                    @endforeach
                @elseif(is_array($value) && !empty($value))
                    <div class="section-title">{{ ucwords(str_replace('_', ' ', $key)) }}</div>
                    @if(isset($value[0]) && is_array($value[0]))
                        <table class="data-table">
                            <thead>
                                <tr>
                                    @foreach(array_keys($value[0]) as $header)
                                        <th>{{ ucwords(str_replace('_', ' ', $header)) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(array_slice($value, 0, 30) as $row)
                                    <tr>
                                        @foreach($row as $cell)
                                            <td>
                                                @if(is_array($cell))
                                                    {{ json_encode($cell) }}
                                                @elseif(is_numeric($cell) && $cell > 1000)
                                                    {{ number_format($cell) }}
                                                @elseif(is_string($cell) || is_numeric($cell))
                                                    {{ $cell }}
                                                @else
                                                    N/A
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                @endif
            @endforeach
        @else
            <div class="summary-item">
                <strong>Report Data:</strong> 
                @if(is_string($data) || is_numeric($data))
                    {{ $data }}
                @else
                    Complex data structure
                @endif
            </div>
        @endif
    </div>

    <div class="footer">
        <p>This report was generated automatically by {{ $company }} reporting system.</p>
    </div>
</body>
</html>