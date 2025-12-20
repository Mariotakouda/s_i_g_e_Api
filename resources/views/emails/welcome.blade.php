<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bienvenue sur {{ config('app.name') }}</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, sans-serif; background-color:#f7f7f7;">
    <div style="max-width:600px; margin:20px auto; padding:20px; background-color:#ffffff; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
        
        <h1 style="color:#333;">Bonjour {{ $name }},</h1> <p style="color:#555; font-size:16px; line-height:1.5;">
            Nous sommes ravis de vous accueillir sur <strong>{{ config('app.name') }}</strong>.  
            Votre compte employé a été créé avec succès.
        </p>

        <div style="background-color: #f1f8ff; border-left: 4px solid #007bff; padding: 15px; margin: 25px 0;">
            <p style="margin: 0; color: #333; font-weight: bold;">Vos identifiants de connexion :</p>
            <p style="margin: 10px 0 5px 0; color: #555;"><strong>Email :</strong> {{ $email }}</p>
            <p style="margin: 0; color: #555;"><strong>Mot de passe temporaire :</strong> <span style="color: #d9534f; font-family: monospace; font-size: 18px;">{{ $password }}</span></p>
        </div>

        <p style="color: #d9534f; font-size: 14px; font-style: italic;">
            * Par mesure de sécurité, veuillez changer ce mot de passe dès votre première connexion.
        </p>
        
        <p style="margin-top:30px; text-align:center;">
            <a href="{{ url('/login') }}" 
               style="display:inline-block; padding:12px 24px; background-color:#007bff; color:#ffffff; text-decoration:none; border-radius:5px; font-weight:bold;">
                Se connecter au tableau de bord
            </a>
        </p>
        
        <p style="color:#777; font-size:14px; margin-top:40px; border-top: 1px solid #eee; padding-top: 20px;">
            Si vous avez des questions ou besoin d’assistance, notre équipe RH reste à votre disposition.
        </p>
        
        <p style="color:#333; font-size:14px; margin-top:20px;">
            Cordialement,<br>
            L’équipe <strong>{{ config('app.name') }}</strong>
        </p>
    </div>
</body>
</html>