<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gift Card Criado - BingBing</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f3f4f6;
            padding: 20px;
            line-height: 1.6;
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
            background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
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
            background: linear-gradient(90deg, #c084fc, #a855f7, #c084fc);
        }

        .icon-wrapper {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }

        .header-title {
            color: #ffffff;
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .header-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 15px;
            margin-top: 8px;
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
            margin-bottom: 24px;
        }

        .gift-box {
            background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
            border: 2px solid #a855f7;
            border-radius: 16px;
            padding: 32px;
            text-align: center;
            margin: 32px 0;
            position: relative;
        }

        .gift-label {
            font-size: 12px;
            color: #7c3aed;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .gift-code {
            font-size: 28px;
            font-weight: 900;
            color: #6d28d9;
            letter-spacing: 3px;
            margin-bottom: 10px;
        }

        .gift-value {
            font-size: 42px;
            font-weight: 900;
            color: #7c3aed;
        }

        .details-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            margin-top: 24px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-size: 13px;
            color: #6b7280;
        }

        .detail-value {
            font-size: 14px;
            color: #111827;
            font-weight: 600;
        }

        .cta {
            text-align: center;
            margin: 32px 0;
        }

        .button {
            display: inline-block;
            padding: 16px 32px;
            background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(168, 85, 247, 0.3);
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
            margin: 6px 0;
        }

        .footer-brand {
            font-weight: 600;
            color: #7c3aed;
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
        }
    </style>
</head>

<body>

    <div class="email-container">

        <div class="header">
            <div class="icon-wrapper">🎁</div>
            <h1 class="header-title">Gift Card Criado!</h1>
            <p class="header-subtitle">Seu presente digital está pronto 🎉</p>
        </div>

        <div class="content">
            <p class="greeting">Olá, {{ $user->name }}!</p>

            <p class="message">
                Seu Gift Card foi criado com sucesso. Você pode compartilhar o código abaixo com quem quiser.
            </p>

            <div class="gift-box">
                <div class="gift-label">Código do Gift Card</div>
                <div class="gift-code">{{ $code }}</div>

                <div class="gift-label" style="margin-top:20px;">Valor</div>
                <div class="gift-value">{{ number_format($creditValue, 0, ',', '.') }} C$</div>
            </div>

            <div class="details-box">
                <div class="detail-row">
                    <span class="detail-label">Valor Pago</span>
                    <span class="detail-value">R$ {{ number_format($price, 2, ',', '.') }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Data da Compra</span>
                    <span class="detail-value">{{ now()->format('d/m/Y H:i') }}</span>
                </div>

                @if ($expiresAt)
                    <div class="detail-row">
                        <span class="detail-label">Validade</span>
                        <span class="detail-value">{{ $expiresAt->format('d/m/Y H:i') }}</span>
                    </div>
                @endif
            </div>

            <div class="cta">
                <a href="{{ route('wallet.gift') }}" class="button">
                    🎫 Ver Meus Gift Cards
                </a>
            </div>

            <p class="message" style="text-align:center; font-size:13px; color:#6b7280;">
                Guarde este código com segurança. Ele poderá ser usado uma única vez.
            </p>
        </div>

        <div class="footer">
            <p class="footer-text">
                <strong class="footer-brand">BingBing Social Club</strong>
            </p>
            <p class="footer-text">
                A plataforma de bingo social mais divertida do Brasil 🎲
            </p>
            <p class="footer-text" style="font-size:12px; color:#9ca3af; margin-top:12px;">
                © {{ date('Y') }} BingBing. Todos os direitos reservados.
            </p>
            <p class="footer-text" style="font-size:11px; color:#9ca3af;">
                Este é um e-mail automático de confirmação de compra.
            </p>
        </div>

    </div>

</body>

</html>
