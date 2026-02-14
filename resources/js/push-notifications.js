if ('serviceWorker' in navigator && 'PushManager' in window) {
    navigator.serviceWorker.register('/sw.js')
        .then(function(registration) {
            console.log('Service Worker registrado');
            
            return registration.pushManager.getSubscription()
                .then(function(subscription) {
                    if (!subscription) {
                        return Notification.requestPermission()
                            .then(function(permission) {
                                if (permission === 'granted') {
                                    return subscribeUser(registration);
                                }
                            });
                    }
                });
        })
        .catch(function(err) {
            console.error('Erro no Service Worker:', err);
        });
}

function subscribeUser(registration) {
    const publicKey = document.querySelector('meta[name="vapid-public-key"]')?.content;
    
    if (!publicKey) {
        console.error('VAPID public key não encontrada');
        return;
    }
    
    return registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(publicKey)
    })
    .then(function(subscription) {
        return fetch('/api/push/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                subscription: subscription.toJSON(),
                device_info: navigator.userAgent
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Inscrito com sucesso:', data);
        });
    })
    .catch(function(err) {
        console.error('Erro ao subscrever:', err);
    });
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}