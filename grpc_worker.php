<?php

use App\Grpc\AuthServiceInterface;
use App\Grpc\AuthService;
use App\Kernel;
use Spiral\RoadRunner\GRPC\Invoker;
use Spiral\RoadRunner\GRPC\Server;
use Spiral\RoadRunner\Worker;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

// 1. Load Env
if (class_exists(Dotenv::class) && file_exists(__DIR__.'/.env')) {
    $dotenv = new Dotenv();
    $dotenv->usePutenv(true);
    $dotenv->overload(__DIR__.'/.env');
}

// 2. Boot Kernel
$env = $_SERVER['APP_ENV'] ?? 'dev';
$debug = (bool) ($_SERVER['APP_DEBUG'] ?? ('prod' !== $env));

$kernel = new Kernel($env, $debug);
$kernel->boot();

$container = $kernel->getContainer();

// 3. Get Services
// Ensure AuthService is public in services.yaml
$authService = $container->get(AuthService::class);

// 4. Create Server
$server = new Server(new Invoker(), [
    'debug' => $debug,
]);

// 5. Register the Interface
// This allows RoadRunner to handle GoogleLogin, Register, and Login
$server->registerService(
    AuthServiceInterface::class,
    $authService
);

// 6. Start Loop
$worker = Worker::create();
$server->serve($worker);