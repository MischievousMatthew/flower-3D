<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isExistingUser ? 'Vendor Application Approved' : 'Vendor Account Created' }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }
        .credentials {
            background: white;
            border: 2px solid #48bb78;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            background: #48bb78;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            margin-top: 20px;
        }
        .note {
            background: #fff3cd;
            border: 1px solid #ffecb5;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🌺 BloomCraft</h1>
        <h2>{{ $isExistingUser ? 'Vendor Application Approved' : 'Welcome to BloomCraft!' }}</h2>
    </div>
    
    <div class="content">
        <p>Dear {{ $application->owner_name }},</p>
        
        @if($isExistingUser)
            <p>We're excited to inform you that your vendor application for <strong>{{ $application->store_name }}</strong> has been approved!</p>
            <p>Your existing account has been upgraded to a vendor account. You can now access the vendor dashboard.</p>
        @else
            <p>Congratulations! Your vendor application for <strong>{{ $application->store_name }}</strong> has been approved and your vendor account has been created.</p>
            
            <div class="credentials">
                <h3>Your Login Credentials:</h3>
                <p><strong>Email:</strong> {{ $application->email }}</p>
                <p><strong>Password:</strong> {{ $password }}</p>
                <p><em>Please change your password after your first login.</em></p>
            </div>
        @endif
        
        <a href="{{ $loginUrl }}" class="btn">Login to Vendor Dashboard</a>
        
        <div class="note">
            <p><strong>Important:</strong></p>
            <ul>
                <li>Login to set up your store profile</li>
                <li>Add your products and pricing</li>
                <li>Configure your delivery settings</li>
                @if(!$isExistingUser)
                <li>Change your password immediately after login</li>
                @endif
            </ul>
        </div>
        
        <p>If you have any questions, please contact our support team.</p>
        
        <p>Best regards,<br>
        <strong>The BloomCraft Team</strong></p>
    </div>
</body>
</html>