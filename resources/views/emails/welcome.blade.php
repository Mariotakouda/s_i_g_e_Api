<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bienvenue sur {{ config('app.name') }}</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, sans-serif; background-color:#f7f7f7;">
    <div style="max-width:600px; margin:20px auto; padding:20px; background-color:#ffffff; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
        
        <h1 style="color:#333;">Bonjour {{ $userName }},</h1>
        
        <p style="color:#555; font-size:16px; line-height:1.5;">
            Nous sommes ravis de vous accueillir sur <strong>{{ config('app.name') }}</strong>.  
            Votre compte a été créé avec succès et vous pouvez dès maintenant accéder à votre espace personnel.
        </p>
        
        <p style="margin-top:30px; text-align:center;">
            <a href="{{ url('/dashboard') }}" 
               style="display:inline-block; padding:12px 24px; background-color:#007bff; color:#ffffff; text-decoration:none; border-radius:5px; font-weight:bold;">
                Accéder à mon tableau de bord
            </a>
        </p>
        
        <p style="color:#777; font-size:14px; margin-top:40px;">
            Si vous avez des questions ou besoin d’assistance, notre équipe reste à votre disposition.  
            Merci de votre confiance.
        </p>
        
        <p style="color:#333; font-size:14px; margin-top:20px;">
            Cordialement,<br>
            L’équipe {{ config('app.name') }}
        </p>
    </div>
</body>
</html>
