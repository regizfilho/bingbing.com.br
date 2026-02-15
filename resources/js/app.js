import "./bootstrap";
import "./echo";
import './push-notifications';
import "./audio-manager.js";
import { requestNotificationPermission } from './notification-permission';

import Alpine from "alpinejs";
import { shareProfile } from "./share-profile.js";

window.requestNotificationPermission = requestNotificationPermission;
window.Alpine = Alpine;

Alpine.data("shareProfile", shareProfile);
Alpine.start();

const processedClicks = new Set();

async function processarClicksPendentes() {
    try {
        const cache = await caches.open('notification-clicks-v1');
        const requests = await cache.keys();
        
        if (requests.length === 0) return;

        console.log('[Notifications] Processing pending clicks:', requests.length);
        
        for (const request of requests) {
            try {
                const response = await cache.match(request);
                const data = await response.json();
                const clickKey = `${data.notificationId}-${data.timestamp}`;
                
                if (processedClicks.has(clickKey)) {
                    await cache.delete(request);
                    continue;
                }
                
                processedClicks.add(clickKey);
                
                const result = await fetch(`/notifications/click/${data.notificationId}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });
                
                if (result.ok) {
                    await cache.delete(request);
                    console.log('[Notifications] Click processed:', data.notificationId);
                } else {
                    console.error('[Notifications] Click processing failed:', {
                        notificationId: data.notificationId,
                        status: result.status,
                        statusText: result.statusText
                    });
                    
                    if (result.status === 401 || result.status === 419) {
                        await cache.delete(request);
                    }
                    
                    if (result.status >= 500) {
                        processedClicks.delete(clickKey);
                    }
                }
            } catch (error) {
                console.error('[Notifications] Individual click processing error:', error);
            }
        }
    } catch (error) {
        console.error('[Notifications] Pending clicks processing failed:', error);
    }
}

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', function(event) {
        if (event.data.type === 'NAVIGATE') {
            console.log('[ServiceWorker] Navigation triggered:', event.data.url);
            window.location.href = event.data.url;
        }
    });
}

if ('caches' in window) {
    setTimeout(processarClicksPendentes, 500);
}

document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        processarClicksPendentes();
    }
});