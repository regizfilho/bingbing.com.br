<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Redefinir Senha - BingBing</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            background-color: #f3f4f6;
            padding: 20px;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            padding: 40px 32px;
            text-align: center;
            position: relative;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3b82f6, #06b6d4, #3b82f6);
        }
        
        .logo {
            width: 64px;
            height: 64px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .logo-text {
            color: #ffffff;
            font-size: 28px;
            font-weight: 900;
            letter-spacing: -0.5px;
        }
        
        .header-title {
            color: #ffffff;
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.5px;
        }
        
        .header-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            margin-top: 8px;
            font-weight: 500;
        }
        
        .content {
            padding: 40px 32px;
        }
        
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 16px;
        }
        
        .message {
            font-size: 15px;
            color: #4b5563;
            line-height: 1.7;
            margin-bottom: 24px;
        }
        
        .info-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 16px 20px;
            border-radius: 8px;
            margin: 24px 0;
        }
        
        .info-box-text {
            font-size: 14px;
            color: #1e40af;
            margin: 0;
            display: flex;
            align-items: start;
            gap: 12px;
        }
        
        .info-icon {
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .button-container {
            text-align: center;
            margin: 32px 0;
        }
        
        .button {
            display: inline-block;
            padding: 16px 32px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            transition: all 0.3s ease;
        }
        
        .button:hover {
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
            transform: translateY(-2px);
        }
        
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e5e7eb, transparent);
            margin: 32px 0;
        }
        
        .alternative-text {
            font-size: 13px;
            color: #6b7280;
            text-align: center;
            margin: 24px 0 16px;
        }
        
        .url-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 16px;
            word-break: break-all;
            font-size: 12px;
            color: #4b5563;
            font-family: 'Courier New', monospace;
        }
        
        .warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 16px 20px;
            border-radius: 8px;
            margin: 24px 0;
        }
        
        .warning-text {
            font-size: 14px;
            color: #92400e;
            margin: 0;
        }
        
        .footer {
            background: #f9fafb;
            padding: 32px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        
        .footer-text {
            font-size: 13px;
            color: #6b7280;
            margin: 8px 0;
        }
        
        .footer-brand {
            font-weight: 600;
            color: #2563eb;
        }
        
        @media only screen and (max-width: 600px) {
            body {
                padding: 10px;
            }
            
            .content {
                padding: 24px 20px;
            }
            
            .header {
                padding: 32px 20px;
            }
            
            .footer {
                padding: 24px 20px;
            }
            
            .button {
                display: block;
                padding: 14px 24px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <span class="logo-text">B²</span>
            </div>
            <h1 class="header-title">Redefinir sua senha</h1>
            <p class="header-subtitle">Solicitação de redefinição de senha</p>
        </div>

        <!-- Content -->
        <div class="content">
            <p class="greeting">Olá, {{ $user->name }}! 👋</p>
            
            <p class="message">
                Recebemos uma solicitação para redefinir a senha da sua conta BingBing. 
                Se foi você quem solicitou, clique no botão abaixo para criar uma nova senha.
            </p>

            <!-- Info Box -->
            <div class="info-box">
                <p class="info-box-text">
                    <span class="info-icon">⏱️</span>
                    <span>Este link é válido por <strong>{{ $count }} minutos</strong>. Após esse período, você precisará solicitar um novo link.</span>
                </p>
            </div>

            <!-- Button -->
            <div class="button-container">
                <a href="{{ $url }}" class="button">
                    🔐 Redefinir minha senha
                </a>
            </div>

            <div class="divider"></div>

            <!-- Alternative Link -->
            <p class="alternative-text">
                Se o botão não funcionar, copie e cole o link abaixo no seu navegador:
            </p>
            <div class="url-box">{{ $url }}</div>

            <!-- Warning -->
            <div class="warning">
                <p class="warning-text">
                    <strong>⚠️ Não solicitou esta redefinição?</strong><br>
                    Se você não pediu para redefinir sua senha, ignore este e-mail. 
                    Sua conta permanece segura e nenhuma alteração será feita.
                </p>
            </div>

            <p class="message" style="margin-top: 32px;">
                Caso tenha alguma dúvida ou precise de ajuda, nossa equipe de suporte está sempre disponível.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p class="footer-text">
                <strong class="footer-brand">BingBing Social Club</strong>
            </p>
            <p class="footer-text">
                A plataforma de bingo social mais divertida do Brasil 🎲
            </p>
            <p class="footer-text" style="margin-top: 16px; color: #9ca3af; font-size: 12px;">
                © {{ date('Y') }} BingBing. Todos os direitos reservados.
            </p>
            <p class="footer-text" style="color: #9ca3af; font-size: 11px; margin-top: 8px;">
                Este é um e-mail automático. Por favor, não responda.
            </p>
        </div>
    </div>
</body>
</html>