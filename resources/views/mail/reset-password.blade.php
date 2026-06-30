<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(45deg, #EC008C, #FC6767);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 300;
            letter-spacing: 1px;
        }
        .content {
            padding: 30px;
            color: #555;
        }
        .message {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .button {
            display: inline-block;
            padding: 15px 35px;
            background: #EC008C;
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 14px;
            letter-spacing: 1px;
            margin: 20px 0;
            transition: background 0.3s;
        }
        .button:hover {
            background: #D1007D;
        }
        .footer {
            background: #f8f8f8;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #888;
            border-top: 1px solid #eee;
        }
        .url-info {
            margin-top: 20px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 5px;
            font-size: 13px;
            color: #666;
        }
        .divider {
            height: 1px;
            background: #eee;
            margin: 20px 0;
        }
        .logo-text {
            font-size: 24px;
            font-weight: bold;
            color: white;
            letter-spacing: 2px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo-text">{{ config('app.name') }}</div>
            <h1>Reset Your Password</h1>
        </div>

        <div class="content">
            <div class="message">
                Hello,<br><br>
                We received a request to reset your password. Don't worry, we're here to help you get back into your account.
            </div>

            <center>
                <a href="{{ $url }}" class="button">Reset Password</a>
            </center>

            <div class="url-info">
                If you're having trouble with the button above, copy and paste this URL into your browser:<br><br>
                {{ $url }}
            </div>

            <div class="divider"></div>

            <div style="color: #888; font-size: 13px;">
                If you didn't request this password reset, you can safely ignore this email. Your password will remain unchanged.
            </div>
        </div>

        <div class="footer">
            <div style="margin-bottom: 10px;">© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</div>
            <div>This is an automated email, please do not reply.</div>
        </div>
    </div>
</body>
</html>
