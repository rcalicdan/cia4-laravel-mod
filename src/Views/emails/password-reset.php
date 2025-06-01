<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
            padding: 20px;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .email-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }

        .email-header h1 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .email-header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .email-body {
            padding: 40px 30px;
        }

        .greeting {
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 20px;
            color: #2d3748;
        }

        .message {
            font-size: 16px;
            line-height: 1.7;
            margin-bottom: 30px;
            color: #4a5568;
        }

        .reset-button {
            display: inline-block;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            text-decoration: none;
            padding: 16px 32px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(245, 87, 108, 0.4);
            margin-bottom: 30px;
        }

        .reset-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(245, 87, 108, 0.5);
            color: white;
            text-decoration: none;
        }

        .button-container {
            text-align: center;
            margin: 30px 0;
        }

        .alternative-text {
            font-size: 14px;
            color: #718096;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e2e8f0;
        }

        .alternative-link {
            color: #f5576c;
            word-break: break-all;
            text-decoration: none;
        }

        .alternative-link:hover {
            text-decoration: underline;
        }

        .email-footer {
            background-color: #f7fafc;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }

        .footer-text {
            font-size: 14px;
            color: #718096;
            margin-bottom: 8px;
        }

        .expiry-notice {
            background-color: #fef5e7;
            border: 1px solid #f6e05e;
            border-radius: 8px;
            padding: 16px;
            margin: 20px 0;
            color: #744210;
            font-size: 14px;
            text-align: center;
        }

        .security-notice {
            background-color: #fed7d7;
            border: 1px solid #fc8181;
            border-radius: 8px;
            padding: 16px;
            margin: 20px 0;
            color: #742a2a;
            font-size: 14px;
        }

        .icon {
            width: 64px;
            height: 64px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 28px;
        }

        @media (max-width: 600px) {
            body {
                padding: 10px;
            }
            
            .email-header {
                padding: 30px 20px;
            }
            
            .email-header h1 {
                font-size: 24px;
            }
            
            .email-body {
                padding: 30px 20px;
            }
            
            .reset-button {
                display: block;
                width: 100%;
                padding: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <div class="icon">
                🔐
            </div>
            <h1>Reset Password</h1>
            <p>Secure your account</p>
        </div>
        
        <div class="email-body">
            <div class="greeting">
                Hello {{ $user->name ?? 'there' }}!
            </div>
            
            <div class="message">
                We received a request to reset your password. Click the button below to create a new password for your account.
            </div>
            
            <div class="button-container">
                <a href="{{ $resetUrl }}" class="reset-button">
                    Reset My Password
                </a>
            </div>
            
            <div class="expiry-notice">
                <strong>⏰ This password reset link will expire in {{ $expiry }} hours.</strong>
            </div>
            
            <div class="security-notice">
                <strong>🛡️ Security Notice:</strong> If you didn't request this password reset, please ignore this email. Your password will remain unchanged.
            </div>
            
            <div class="alternative-text">
                If the button above doesn't work, you can copy and paste the following link into your browser:
                <br><br>
                <a href="{{ $resetUrl }}" class="alternative-link">{{ $resetUrl }}</a>
            </div>
        </div>
        
        <div class="email-footer">
            <div class="footer-text">
                This password reset was requested from IP address: {{ $_SERVER['REMOTE_ADDR'] ?? 'Unknown' }}
            </div>
            <div class="footer-text">
                This is an automated message, please do not reply to this email.
            </div>
        </div>
    </div>
</body>
</html>