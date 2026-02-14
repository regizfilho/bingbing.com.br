self.addEventListener('install', function(event) {
    console.log('Service Worker instalado');
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    console.log('Service Worker ativado');
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function(event) {
    console.log('Push recebido:', event);
    
    if (!event.data) {
        console.log('Push sem dados');
        return;
    }

    try {
        const data = event.data.json();
        console.log('Dados da notificação:', data);

        const options = {
            body: data.body,
            icon: data.icon || '/imgs/ico.png',
            badge: data.badge || '/imgs/ico.png',
            data: {
                url: data.url,
                notification_id: data.data?.notification_id
            },
            requireInteraction: false,
            tag: 'notification-' + (data.data?.notification_id || Date.now())
        };

        event.waitUntil(
            self.registration.showNotification(data.title, options)
        );
    } catch (error) {
        console.error('Erro ao processar push:', error);
    }
});

self.addEventListener('notificationclick', function(event) {
    console.log('=== NOTIFICAÇÃO CLICADA ===');
    console.log('Event:', event);
    console.log('Notification data:', event.notification.data);
    
    event.notification.close();

    const notificationData = event.notification.data;
    let url = notificationData?.url || '/';
    const notificationId = notificationData?.notification_id;

    // Garantir que a URL é absoluta
    if (!url.startsWith('http')) {
        url = self.location.origin + (url.startsWith('/') ? url : '/' + url);
    }

    console.log('URL de destino:', url);
    console.log('Notification ID:', notificationId);

    // Salvar click pendente para ser processado pela página (apenas UMA vez)
    if (notificationId) {
        console.log('Salvando click pendente no cache');
        
        event.waitUntil(
            caches.open('notification-clicks-v1').then(function(cache) {
                const timestamp = Date.now();
                const clickData = {
                    notificationId: notificationId,
                    timestamp: timestamp
                };
                
                const clickUrl = `/click-pending/${notificationId}-${timestamp}`;
                
                return cache.put(
                    clickUrl,
                    new Response(JSON.stringify(clickData), {
                        headers: { 'Content-Type': 'application/json' }
                    })
                );
            }).then(function() {
                console.log('Click salvo no cache');
            }).catch(function(error) {
                console.error('Erro ao salvar click:', error);
            })
        );
    }

    // Sempre abrir em NOVA ABA
    event.waitUntil(
        clients.openWindow(url).then(function(client) {
            console.log('Nova aba aberta:', client ? 'Sucesso' : 'Falhou');
            return client;
        }).catch(function(error) {
            console.error('Erro ao abrir nova aba:', error);
        })
    );
});