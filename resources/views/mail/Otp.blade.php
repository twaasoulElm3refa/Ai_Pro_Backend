<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>OTP Verification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f6f8fb;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 520px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }

        .header {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            padding: 30px;
            text-align: center;
            color: white;
        }

        .header h1 {
            margin: 0;
            font-size: 22px;
        }

        .content {
            padding: 30px;
            text-align: center;
        }

        .otp-box {
            margin: 25px auto;
            display: inline-block;
            padding: 15px 30px;
            font-size: 28px;
            letter-spacing: 6px;
            font-weight: bold;
            background: #f3f4f6;
            border-radius: 10px;
            color: #111827;
        }

        .text {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.6;
        }

        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #9ca3af;
        }

        .warning {
            color: #ef4444;
            font-size: 13px;
            margin-top: 10px;
        }
    </style>
</head>

<body>

<div class="container">

    <!-- Header -->
    <div class="header">
        <h1>OTP Verification</h1>
        <p>Secure your account</p>
    </div>

    <!-- Content -->
    <div class="content">

        <p class="text">
            Hello 👋 <br>
            Use the OTP below to verify your email address:
        </p>

        <div class="otp-box">
            {{ $otp }}
        </div>

        <p class="text">
            This code will expire in <b>10 minutes</b>.<br>
            Do not share it with anyone.
        </p>

        <p class="warning">
            If you did not request this, you can ignore this email.
        </p>

        <div class="footer">
            © {{ date('Y') }} AI PRO. All rights reserved.
        </div>

    </div>
</div>

</body>
</html>
