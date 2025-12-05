<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request; // <--- ВАЖНО: Добавете този ред най-горе!

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    // === НАСТРОЙКА ЗА KOYEB / TRUSTED PROXIES ===
    // Тъй като сте на Runtime, слагаме кода тук:
    Request::setTrustedProxies(
        ['0.0.0.0/0', '::/0'], // Вярваме на всички проксита (Koyeb load balancers)
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_PREFIX
    );
    // ============================================

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};