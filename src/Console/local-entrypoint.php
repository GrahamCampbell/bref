<?php declare(strict_types=1);

use Bref\Bref;
use Bref\Context\Context;
use Bref\Runtime\Invoker;
use Psr\Container\NotFoundExceptionInterface;

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
} else {
    /** @noinspection PhpIncludeInspection */
    require_once __DIR__ . '/../../../../autoload.php';
}

[$_, $handler, $data] = $argv;

try {
    $handler = Bref::getContainer()->get($handler);
} catch (NotFoundExceptionInterface $e) {
    throw new Exception($e->getMessage() . PHP_EOL . 'Reminder: `serverless bref:local` can invoke event-driven functions that use the FUNCTION runtime, not the web app (or "FPM") runtime. Check out https://bref.sh/docs/web-apps/local-development.html to run web applications locally.');
}

try {
    $event = $data ? json_decode($data, true, 512, JSON_THROW_ON_ERROR) : null;
} catch (JsonException $e) {
    throw new Exception('The JSON provided for the event data is invalid JSON.');
}

// Same configuration as the Bref runtime on Lambda
ini_set('display_errors', '1');
error_reporting(E_ALL);

$requestId = '8f507cfc-example-4697-b07a-ac58fc914c95';
$startTime = logStart();

try {
    $invoker = new Invoker;
    $result = $invoker->invoke($handler, $event, new Context($requestId, 0, '', ''));
} catch (Throwable $e) {
    echo get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
    echo 'Stack trace:' . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}

logEnd($startTime);
// Show the invocation result
echo json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;

function logStart(): float
{
    echo "START\n";
    return microtime(true);
}

function logEnd(float $startTime): void
{
    $duration = ceil((microtime(true) - $startTime) * 1000);
    $memoryUsed = ceil(memory_get_usage() / 1024 / 1024);
    echo "END Duration: $duration ms Max Memory Used: $memoryUsed MB\n\n";
}
