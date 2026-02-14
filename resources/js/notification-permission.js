export function requestNotificationPermission() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        alert('Notificações push não são suportadas neste navegador');
        return Promise.reject('Not supported');
    }

    const publicKey = document.querySelector('meta[name="vapid-public-key"]')?.content;
    if (!publicKey) {
        console.error('VAPID public key não encontrada');
        return Promise.reject('No VAPID key');
    }

    return Notification.requestPermission()
        .then(permission => {
            if (permission !== 'granted') {
                throw new Error('Permissão negada');
            }

            return navigator.serviceWorker.ready;
        })
        .then(registration => {
            return registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(publicKey)
            });
        })
        .then(subscription => {
            return fetch('/api/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    subscription: subscription.toJSON(),
                    device_info: navigator.userAgent
                })
            });
        })
        .then(response => response.json());
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