import "./bootstrap";
import "./echo";
import './push-notifications';
import { requestNotificationPermission } from './notification-permission';

import Alpine from "alpinejs";
import { shareProfile } from "./share-profile.js";


window.requestNotificationPermission = requestNotificationPermission;

window.Alpine = Alpine;

Alpine.data("shareProfile", shareProfile);

Alpine.start();

// Set para evitar processar o mesmo click múltiplas vezes
const processedClicks = new Set();

// Função para processar clicks pendentes
async function processarClicksPendentes() {
    console.log('Verificando clicks pendentes...');
    
    try {
        const cache = await caches.open('notification-clicks-v1');
        const requests = await cache.keys();
        
        console.log('Clicks pendentes encontrados:', requests.length);
        
        for (const request of requests) {
            try {
                const response = await cache.match(request);
                const data = await response.json();
                
                // Criar chave única para este click
                const clickKey = `${data.notificationId}-${data.timestamp}`;
                
                // Verificar se já foi processado
                if (processedClicks.has(clickKey)) {
                    console.log('Click já processado, pulando:', clickKey);
                    await cache.delete(request);
                    continue;
                }
                
                console.log('Processando click:', data);
                
                // Marcar como processado ANTES de fazer o request
                processedClicks.add(clickKey);
                
                // Usar GET em vez de POST para evitar problemas de CSRF
                const result = await fetch(`/notifications/click/${data.notificationId}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });
                
                console.log('Resultado do registro:', result.status);
                
                if (result.ok) {
                    const jsonResult = await result.json();
                    console.log('Click registrado com sucesso:', jsonResult);
                    // Remover do cache após processar
                    await cache.delete(request);
                    console.log('Click processado e removido do cache');
                } else {
                    const errorText = await result.text();
                    console.error('Erro ao registrar click:', result.status, errorText);
                    
                    // Se for erro de autenticação, remover do cache
                    if (result.status === 401 || result.status === 419) {
                        console.log('Erro de autenticação, removendo do cache');
                        await cache.delete(request);
                    }
                    
                    // Se deu erro, remover da lista de processados para tentar novamente depois
                    if (result.status >= 500) {
                        processedClicks.delete(clickKey);
                    }
                }
            } catch (error) {
                console.error('Erro ao processar click individual:', error);
            }
        }
    } catch (error) {
        console.error('Erro ao processar clicks pendentes:', error);
    }
}

// Listener para mensagens do Service Worker
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', function(event) {
        console.log('=== MENSAGEM DO SW ===');
        console.log('Dados:', event.data);
        
        // Listener para navegação
        if (event.data.type === 'NAVIGATE') {
            const url = event.data.url;
            console.log('Navegando para:', url);
            window.location.href = url;
        }
    });
}

// Processar clicks pendentes ao carregar a página
if ('caches' in window) {
    console.log('Iniciando verificação de clicks pendentes...');
    
    // Processar após 500ms (dar tempo para a página carregar)
    setTimeout(processarClicksPendentes, 500);
}

// Adicionar também no evento de visibilidade
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        console.log('Página ficou visível, verificando clicks...');
        processarClicksPendentes();
    }
});