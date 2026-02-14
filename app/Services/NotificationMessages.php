<?php

namespace App\Services;

class NotificationMessages
{
    /**
     * Mensagens de compra de créditos
     */
    public static function creditPurchase(int $credits, string $packageName): array
    {
        $messages = [
            [
                'title' => '🎉 Recarga Confirmada!',
                'body' => 'Uau! Você acaba de adicionar {credits} créditos à sua carteira! Está pronto para dominar?',
            ],
            [
                'title' => '💰 Créditos Adicionados!',
                'body' => 'Booom! {credits} créditos fresquinhos na sua conta! Hora de arrasar nos jogos!',
            ],
            [
                'title' => '🚀 Recarga Bem-Sucedida!',
                'body' => 'Incrível! Você ganhou {credits} créditos com o pacote {package}! Que comece a diversão!',
            ],
            [
                'title' => '⚡ Energia Recarregada!',
                'body' => 'Sensacional! {credits} créditos acabaram de entrar na sua carteira! Você está imparável!',
            ],
            [
                'title' => '🎮 Pronto para Jogar!',
                'body' => 'Show! {credits} créditos adicionados com sucesso! Seus adversários não têm chance!',
            ],
            [
                'title' => '💎 Tesouro Desbloqueado!',
                'body' => 'Parabéns! {credits} créditos foram depositados na sua conta! Você é um verdadeiro campeão!',
            ],
            [
                'title' => '🔥 Está Pegando Fogo!',
                'body' => 'Arrasou! {credits} créditos na área! Prepare-se para uma sequência de vitórias épicas!',
            ],
            [
                'title' => '🏆 Recarga de Campeão!',
                'body' => 'Mandou bem! {credits} créditos já estão disponíveis! Agora é só partir para cima!',
            ],
        ];

        $message = $messages[array_rand($messages)];

        return [
            'title' => $message['title'],
            'body' => str_replace(
                ['{credits}', '{package}'],
                [number_format($credits, 0, ',', '.'), $packageName],
                $message['body']
            ),
        ];
    }

    /**
     * Mensagens de vitória em jogos
     */
    public static function gameVictory(string $gameName, float $prize): array
    {
        $messages = [
            [
                'title' => '🏆 Você Venceu!',
                'body' => 'Sensacional! Você ganhou R$ {prize} no jogo {game}! A vitória é sua!',
            ],
            [
                'title' => '🎯 Vitória Épica!',
                'body' => 'Incrível! Você dominou {game} e ganhou R$ {prize}! Imparável!',
            ],
            [
                'title' => '⚡ Arrasou!',
                'body' => 'Show! Você detonou no {game} e faturou R$ {prize}! Continue assim!',
            ],
        ];

        $message = $messages[array_rand($messages)];

        return [
            'title' => $message['title'],
            'body' => str_replace(
                ['{game}', '{prize}'],
                [$gameName, number_format($prize, 2, ',', '.')],
                $message['body']
            ),
        ];
    }

    /**
     * Mensagens de boas-vindas
     */
    public static function welcome(string $userName): array
    {
        $messages = [
            [
                'title' => '👋 Bem-vindo de volta!',
                'body' => 'E aí, {name}! Pronto para mais uma sessão épica de jogos?',
            ],
            [
                'title' => '🎮 Hora de Jogar!',
                'body' => 'Olá {name}! Seus adversários estão te esperando. Bora dominar?',
            ],
            [
                'title' => '🚀 Vamos Nessa!',
                'body' => 'Fala {name}! Que tal começar o dia com uma vitória?',
            ],
        ];

        $message = $messages[array_rand($messages)];

        return [
            'title' => $message['title'],
            'body' => str_replace('{name}', $userName, $message['body']),
        ];
    }

    /**
     * Mensagens de saldo baixo
     */
    public static function lowBalance(int $currentBalance): array
    {
        return [
            'title' => '⚠️ Saldo Baixo!',
            'body' => "Você tem apenas {$currentBalance} créditos restantes. Hora de recarregar para continuar jogando!",
        ];
    }

    /**
     * Mensagens de cupom aplicado
     */
    public static function couponApplied(string $couponCode, float $discount): array
    {
        $messages = [
            [
                'title' => '🎁 Cupom Aplicado!',
                'body' => 'Show! Cupom {code} ativado! Você economizou R$ {discount}!',
            ],
            [
                'title' => '💰 Desconto Garantido!',
                'body' => 'Parabéns! {code} aplicado com sucesso! R$ {discount} de economia!',
            ],
            [
                'title' => '🎉 Que Sorte!',
                'body' => 'Cupom {code} funcionou! Você ganhou R$ {discount} de desconto!',
            ],
        ];

        $message = $messages[array_rand($messages)];

        return [
            'title' => $message['title'],
            'body' => str_replace(
                ['{code}', '{discount}'],
                [$couponCode, number_format($discount, 2, ',', '.')],
                $message['body']
            ),
        ];
    }

    /**
     * Mensagens de sala criada
     */
    public static function gameRoomCreated(string $roomName, string $inviteCode): array
    {
        $messages = [
            [
                'title' => '🎮 Sala Criada com Sucesso!',
                'body' => 'Arrasou! Sua sala "{room}" está pronta! Compartilhe o código {code} e chame a galera!',
            ],
            [
                'title' => '🏆 Arena Preparada!',
                'body' => 'Show! "{room}" está aberta para batalha! Convide os jogadores com o código {code}!',
            ],
            [
                'title' => '⚡ Partida Iniciada!',
                'body' => 'Perfeito! Sala "{room}" criada! Código de convite: {code} - Bora jogar!',
            ],
            [
                'title' => '🎯 Tudo Pronto!',
                'body' => 'Mandou bem! "{room}" está esperando os jogadores! Código: {code}',
            ],
            [
                'title' => '🚀 Sala no Ar!',
                'body' => 'Sensacional! "{room}" está ativa! Compartilhe {code} e comece a diversão!',
            ],
        ];

        $message = $messages[array_rand($messages)];

        return [
            'title' => $message['title'],
            'body' => str_replace(
                ['{room}', '{code}'],
                [$roomName, $inviteCode],
                $message['body']
            ),
        ];
    }

    /**
     * Mensagens de sala aberta/publicada
     */
    public static function gameRoomOpened(string $roomName, string $inviteCode): array
    {
        $messages = [
            [
                'title' => '✅ Sala Aberta para Jogadores!',
                'body' => 'Pronto! "{room}" está recebendo participantes agora! Código: {code}',
            ],
            [
                'title' => '🎯 Tá Valendo!',
                'body' => 'Show! Sala "{room}" liberada! Convide a galera com o código {code}!',
            ],
            [
                'title' => '🚀 Partida Ativa!',
                'body' => 'Beleza! "{room}" está no ar! Compartilhe {code} e bora jogar!',
            ],
            [
                'title' => '🎮 Sala Aguardando Jogadores!',
                'body' => 'Eba! "{room}" está pronta para começar! Código de entrada: {code}',
            ],
            [
                'title' => '⚡ Tudo Configurado!',
                'body' => 'Perfeito! Sala "{room}" aberta! Envie {code} para seus amigos entrarem!',
            ],
        ];

        $message = $messages[array_rand($messages)];

        return [
            'title' => $message['title'],
            'body' => str_replace(
                ['{room}', '{code}'],
                [$roomName, $inviteCode],
                $message['body']
            ),
        ];
    }

    /**
     * Mensagens de jogador entrando na sala
     */
    public static function playerJoinedRoom(string $roomName, int $cardsCount): array
    {
        $messages = [
            [
                'title' => '🎮 Você Entrou na Partida!',
                'body' => 'Booom! Suas {cards} cartelas para "{room}" foram geradas! Boa sorte!',
            ],
            [
                'title' => '🎯 Está no Jogo!',
                'body' => 'Arrasou! {cards} cartelas prontas em "{room}"! Que venha a vitória!',
            ],
            [
                'title' => '⚡ Cartelas Geradas!',
                'body' => 'Show! Você tem {cards} chances em "{room}"! Bora dominar!',
            ],
            [
                'title' => '🚀 Pronto para Jogar!',
                'body' => 'Mandou bem! {cards} cartelas ativas em "{room}"! Boa sorte campeão!',
            ],
            [
                'title' => '🏆 Partida Confirmada!',
                'body' => 'Eba! {cards} cartelas geradas para "{room}"! Que comece o jogo!',
            ],
        ];

        $message = $messages[array_rand($messages)];

        return [
            'title' => $message['title'],
            'body' => str_replace(
                ['{room}', '{cards}'],
                [$roomName, $cardsCount],
                $message['body']
            ),
        ];
    }

    /**
     * Mensagens de vitória no bingo
     */
    public static function bingoWinner(string $roomName, string $prizeName): array
    {
        $messages = [
            [
                'title' => '🏆 BINGO! Você Venceu!',
                'body' => 'SENSACIONAL! Você ganhou "{prize}" em "{room}"! Parabéns campeão!',
            ],
            [
                'title' => '🎉 VITÓRIA ÉPICA!',
                'body' => 'ARRASOU! "{prize}" é seu em "{room}"! Você é imbatível!',
            ],
            [
                'title' => '⚡ VOCÊ GANHOU!',
                'body' => 'INCRÍVEL! "{prize}" conquistado em "{room}"! Mandou muito bem!',
            ],
            [
                'title' => '🚀 CAMPEÃO!',
                'body' => 'SHOW! Você levou "{prize}" em "{room}"! Vitória merecida!',
            ],
            [
                'title' => '💎 BINGO CONFIRMADO!',
                'body' => 'PERFEITO! "{prize}" é todo seu em "{room}"! Que jogada incrível!',
            ],
        ];

        $message = $messages[array_rand($messages)];

        return [
            'title' => $message['title'],
            'body' => str_replace(
                ['{room}', '{prize}'],
                [$roomName, $prizeName],
                $message['body']
            ),
        ];
    }

    /**
     * Mensagens de promoção
     */
    public static function promotion(string $title, string $description): array
    {
        return [
            'title' => "🔥 {$title}",
            'body' => $description,
        ];
    }
}