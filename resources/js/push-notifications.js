let registration = null;

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

async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        console.error('[PushNotifications] Service Worker not supported');
        return null;
    }

    try {
        registration = await navigator.serviceWorker.register('/sw.js');
        await navigator.serviceWorker.ready;
        
        console.log('[PushNotifications] Service Worker registered:', registration.scope);
        return registration;
    } catch (error) {
        console.error('[PushNotifications] Service Worker registration failed:', error.message);
        return null;
    }
}

async function subscribeToPush() {
    if (!registration) {
        registration = await registerServiceWorker();
        
        if (!registration) {
            throw new Error('Service Worker registration failed');
        }
    }

    try {
        const vapidPublicKey = document.querySelector('meta[name="vapid-public-key"]')?.content;
        
        if (!vapidPublicKey) {
            console.error('[PushNotifications] VAPID public key not found');
            throw new Error('VAPID public key not found');
        }

        const existingSubscription = await registration.pushManager.getSubscription();
        
        if (existingSubscription) {
            await sendSubscriptionToBackend(existingSubscription);
            return existingSubscription;
        }

        const applicationServerKey = urlBase64ToUint8Array(vapidPublicKey);
        
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: applicationServerKey
        });

        console.log('[PushNotifications] New subscription created');
        await sendSubscriptionToBackend(subscription);
        
        return subscription;
    } catch (error) {
        console.error('[PushNotifications] Subscription creation failed:', error.message);
        throw error;
    }
}

async function sendSubscriptionToBackend(subscription) {
    try {
        const subscriptionJson = subscription.toJSON();
        const deviceInfo = `${navigator.platform} - ${navigator.userAgent}`;

        const response = await fetch('/api/push/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                endpoint: subscriptionJson.endpoint,
                keys: {
                    p256dh: subscriptionJson.keys.p256dh,
                    auth: subscriptionJson.keys.auth
                },
                device_info: deviceInfo
            })
        });

        const data = await response.json();

        if (!response.ok) {
            console.error('[PushNotifications] Backend registration failed:', {
                status: response.status,
                message: data.message
            });
            throw new Error(data.message || 'Erro ao registrar subscription');
        }

        console.log('[PushNotifications] Subscription registered successfully');
        return data;
    } catch (error) {
        console.error('[PushNotifications] Backend communication failed:', error.message);
        throw error;
    }
}

async function unsubscribeFromPush() {
    if (!registration) {
        console.warn('[PushNotifications] No Service Worker registered');
        return;
    }

    try {
        const subscription = await registration.pushManager.getSubscription();
        
        if (!subscription) {
            console.warn('[PushNotifications] No active subscription');
            return;
        }

        const endpoint = subscription.endpoint;

        await fetch('/api/push/unsubscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ endpoint })
        });

        await subscription.unsubscribe();
        console.log('[PushNotifications] Subscription removed successfully');
    } catch (error) {
        console.error('[PushNotifications] Unsubscribe failed:', error.message);
        throw error;
    }
}

window.registerPushSubscription = async function() {
    try {
        if (Notification.permission !== 'granted') {
            console.warn('[PushNotifications] Permission not granted:', Notification.permission);
            throw new Error('Notification permission not granted');
        }

        const subscription = await subscribeToPush();
        return subscription;
    } catch (error) {
        console.error('[PushNotifications] Registration failed:', error.message);
        throw error;
    }
};

window.unregisterPushSubscription = unsubscribeFromPush;

if ('serviceWorker' in navigator && 'PushManager' in window) {
    registerServiceWorker().then(() => {
        console.log('[PushNotifications] System ready');
    }).catch((error) => {
        console.error('[PushNotifications] Initialization failed:', error.message);
    });
} else {
    console.warn('[PushNotifications] Not supported in this browser');
}