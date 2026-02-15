export function requestNotificationPermission() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        console.error('[Notifications] Push notifications not supported');
        alert('Notificações push não são suportadas neste navegador');
        return Promise.reject('Not supported');
    }

    const publicKey = document.querySelector('meta[name="vapid-public-key"]')?.content;
    if (!publicKey) {
        console.error('[Notifications] VAPID public key not found');
        return Promise.reject('No VAPID key');
    }

    return Notification.requestPermission()
        .then(permission => {
            if (permission !== 'granted') {
                console.warn('[Notifications] Permission denied by user');
                throw new Error('Permissão negada');
            }

            console.log('[Notifications] Permission granted');
            return navigator.serviceWorker.ready;
        })
        .then(registration => {
            return registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(publicKey)
            });
        })
        .then(subscription => {
            const subscriptionJson = subscription.toJSON();
            
            return fetch('/api/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    endpoint: subscriptionJson.endpoint,
                    keys: {
                        p256dh: subscriptionJson.keys.p256dh,
                        auth: subscriptionJson.keys.auth
                    },
                    device_info: navigator.userAgent
                })
            });
        })
        .then(response => {
            if (!response.ok) {
                console.error('[Notifications] Subscription failed:', response.status);
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                console.error('[Notifications] Server rejected subscription:', data.message);
                throw new Error(data.message || 'Erro desconhecido');
            }
            
            console.log('[Notifications] Subscription successful');
            return data;
        })
        .catch(error => {
            console.error('[Notifications] Subscription process failed:', error.message);
            throw error;
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