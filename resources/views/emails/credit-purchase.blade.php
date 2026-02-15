<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Recarga Confirmada - BingBing</title>
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
            background: linear-gradient(90deg, #34d399, #10b981, #34d399);
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            animation: scaleIn 0.5s ease-out;
        }
        
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        
        .checkmark {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #10b981;
            font-size: 24px;
            font-weight: bold;
        }
        
        .header-title {
            color: #ffffff;
            font-size: 32px;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.5px;
        }
        
        .header-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
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
        
        .credits-box {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 2px solid #10b981;
            border-radius: 16px;
            padding: 32px;
            text-align: center;
            margin: 32px 0;
            position: relative;
            overflow: hidden;
        }
        
        .credits-box::before {
            content: '✨';
            position: absolute;
            top: 16px;
            left: 16px;
            font-size: 24px;
            opacity: 0.5;
        }
        
        .credits-box::after {
            content: '✨';
            position: absolute;
            bottom: 16px;
            right: 16px;
            font-size: 24px;
            opacity: 0.5;
        }
        
        .credits-label {
            font-size: 12px;
            color: #059669;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        
        .credits-amount {
            font-size: 56px;
            font-weight: 900;
            color: #10b981;
            line-height: 1;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .credits-currency {
            font-size: 24px;
            color: #059669;
        }
        
        .package-name {
            font-size: 14px;
            color: #065f46;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .details-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            margin: 24px 0;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
        }
        
        .detail-value {
            font-size: 14px;
            color: #111827;
            font-weight: 600;
        }
        
        .discount-highlight {
            color: #10b981;
            font-weight: 700;
        }
        
        .total-box {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border: 2px solid #3b82f6;
            border-radius: 12px;
            padding: 20px;
            margin-top: 16px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .total-label {
            font-size: 14px;
            color: #1e40af;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .total-value {
            font-size: 28px;
            color: #1e3a8a;
            font-weight: 900;
        }
        
        .balance-box {
            background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%);
            border-left: 4px solid #8b5cf6;
            border-radius: 8px;
            padding: 20px;
            margin: 24px 0;
        }
        
        .balance-text {
            font-size: 13px;
            color: #5b21b6;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .balance-amount {
            font-size: 32px;
            color: #6d28d9;
            font-weight: 900;
        }
        
        .button-container {
            text-align: center;
            margin: 32px 0;
        }
        
        .button {
            display: inline-block;
            padding: 16px 32px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 15px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            transition: all 0.3s ease;
        }
        
        .button:hover {
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
            transform: translateY(-2px);
        }
        
        .info-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 16px 20px;
            border-radius: 8px;
            margin: 24px 0;
        }
        
        .info-text {
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
            color: #10b981;
        }
        
        .social-links {
            margin: 20px 0;
            display: flex;
            justify-content: center;
            gap: 16px;
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
            
            .credits-amount {
                font-size: 48px;
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
            <div class="success-icon">
                <div class="checkmark">✓</div>
            </div>
            <h1 class="header-title">Recarga Confirmada!</h1>
            <p class="header-subtitle">Sua compra foi processada com sucesso</p>
        </div>

        <!-- Content -->
        <div class="content">
            <p class="greeting">Olá, {{ $user->name }}! 🎉</p>
            
            <p class="message">
                Boa notícia! Sua recarga de créditos foi processada com sucesso e já está disponível na sua carteira BingBing.
            </p>

            <!-- Credits Box -->
            <div class="credits-box">
                <div class="credits-label">Você recebeu</div>
                <div class="credits-amount">
                    <span class="credits-currency">C$</span>
                    {{ number_format($credits, 0, ',', '.') }}
                </div>
                <div class="package-name">{{ $packageName }}</div>
            </div>

            <!-- Transaction Details -->
            <div class="details-box">
                <h3 style="font-size: 14px; color: #111827; font-weight: 700; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px;">
                    Detalhes da Compra
                </h3>
                
                <div class="detail-row">
                    <span class="detail-label">Data e Hora</span>
                    <span class="detail-value">{{ $transaction->created_at->format('d/m/Y \à\s H:i') }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">ID da Transação</span>
                    <span class="detail-value">#{{ $transaction->uuid }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Pacote</span>
                    <span class="detail-value">{{ $packageName }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Créditos</span>
                    <span class="detail-value">+{{ number_format($credits, 0, ',', '.') }} C$</span>
                </div>
                
                @if($hasCoupon)
                    <div class="detail-row">
                        <span class="detail-label">Valor Original</span>
                        <span class="detail-value">R$ {{ number_format($originalAmount, 2, ',', '.') }}</span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Desconto Aplicado</span>
                        <span class="detail-value discount-highlight">- R$ {{ number_format($discountAmount, 2, ',', '.') }}</span>
                    </div>
                @endif
            </div>

            <!-- Total -->
            <div class="total-box">
                <div class="total-row">
                    <span class="total-label">Total Pago</span>
                    <span class="total-value">R$ {{ number_format($finalAmount, 2, ',', '.') }}</span>
                </div>
            </div>

            <!-- New Balance -->
            <div class="balance-box">
                <div class="balance-text">Seu novo saldo:</div>
                <div class="balance-amount">{{ number_format($newBalance, 0, ',', '.') }} C$</div>
            </div>

            @if($hasCoupon)
                <div class="info-box">
                    <p class="info-text">
                        <strong>🎫 Desconto especial!</strong> Você economizou R$ {{ number_format($discountAmount, 2, ',', '.') }} nesta compra com seu cupom!
                    </p>
                </div>
            @endif

            <!-- CTA Button -->
            <div class="button-container">
                <a href="{{ route('dashboard') }}" class="button">
                    🎮 Começar a Jogar
                </a>
            </div>

            <p class="message" style="text-align: center; margin-top: 32px;">
                Agora você pode usar seus créditos para participar de salas, criar jogos e muito mais!
            </p>

            <p class="message" style="margin-top: 24px; font-size: 13px; color: #6b7280; text-align: center;">
                Dúvidas? Entre em contato com nosso suporte através do chat ou pelo email 
                <a href="mailto:suporte@bingbing.com.br" style="color: #10b981; text-decoration: none; font-weight: 600;">suporte@bingbing.com.br</a>
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
                Este é um e-mail automático de confirmação de compra.
            </p>
        </div>
    </div>
</body>
</html>