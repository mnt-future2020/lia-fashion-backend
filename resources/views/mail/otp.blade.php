<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OTP Verification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .otp-container {
            background-color: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
        }
        .otp-code {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
            letter-spacing: 5px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Verify Your Account</h1>
    </div>

    <p>Hello,</p>

    <p>Your verification code is:</p>

    <div class="otp-container">
        <div class="otp-code">{{ $otp }}</div>
    </div>

    <p><strong>This code will expire in 10 minutes.</strong></p>

    <p>If you didn't request this verification code, please ignore this email.</p>

    <div class="footer">
        <p>Thanks,<br>
        <strong>{{ config('app.name', 'Lia_Fashions') }}</strong></p>
    </div>
</body>
</html>
