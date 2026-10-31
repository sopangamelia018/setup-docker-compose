<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function check(callable $fn): array
{
    try {
        return ['status' => 'ok'] + $fn();
    } catch (Throwable $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

function checkMysql(): array
{
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('MYSQL_HOST'), getenv('MYSQL_DATABASE'));
    $pdo = new PDO($dsn, getenv('MYSQL_USER'), getenv('MYSQL_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    return ['version' => $pdo->query('SELECT VERSION()')->fetchColumn()];
}

function checkPgsql(): array
{
    $dsn = sprintf('pgsql:host=%s;dbname=%s', getenv('PG_HOST'), getenv('PG_DATABASE'));
    $pdo = new PDO($dsn, getenv('PG_USER'), getenv('PG_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    return ['version' => $pdo->query('SELECT version()')->fetchColumn()];
}

function checkMongo(): array
{
    $manager = new MongoDB\Driver\Manager(getenv('MONGO_URI'));
    $cursor = $manager->executeCommand('admin', new MongoDB\Driver\Command(['buildInfo' => 1]));
    return ['version' => $cursor->toArray()[0]->version];
}

function rabbitConnection(): AMQPStreamConnection
{
    return new AMQPStreamConnection(
        getenv('RABBITMQ_HOST'),
        (int) getenv('RABBITMQ_PORT'),
        getenv('RABBITMQ_USER'),
        getenv('RABBITMQ_PASS')
    );
}

function checkRabbit(): array
{
    $conn = rabbitConnection();
    $conn->close();
    return ['connected' => true];
}

$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/') ?: '/';

switch ($path) {
    case '/':
        respond([
            'service' => 'php-api',
            'php'     => PHP_VERSION,
            'routes'  => ['GET /health', 'POST /rabbitmq/publish {"message":"..."}'],
        ]);

    case '/health':
        respond([
            'mysql'    => check('checkMysql'),
            'postgres' => check('checkPgsql'),
            'mongodb'  => check('checkMongo'),
            'rabbitmq' => check('checkRabbit'),
        ]);

    case '/rabbitmq/publish':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(['error' => 'Gunakan method POST'], 405);
        }
        $body = json_decode(file_get_contents('php://input') ?: '{}', true);
        $msg  = (string) ($body['message'] ?? '');
        if ($msg === '') {
            respond(['error' => 'Field "message" wajib diisi'], 422);
        }
        try {
            $conn = rabbitConnection();
            $ch = $conn->channel();
            $ch->queue_declare('demo', false, true, false, false);
            $ch->basic_publish(
                new AMQPMessage($msg, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]),
                '',
                'demo'
            );
            $ch->close();
            $conn->close();
            respond(['published' => true, 'queue' => 'demo', 'message' => $msg, 'by' => 'php-api']);
        } catch (Throwable $e) {
            respond(['error' => $e->getMessage()], 500);
        }

    default:
        respond(['error' => 'Not found'], 404);
}
