<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use PianoTrainer\Env;
use PianoTrainer\MysqlConnection;
use PianoTrainer\MysqlConnectionProbe;

$projectRoot = dirname(__DIR__);
$envFile = $projectRoot . '/.env';
$probe = false;

foreach (array_slice($argv, 1) as $arg) {
  if ($arg === '--probe') {
    $probe = true;
    continue;
  }
  if (str_starts_with($arg, '--env-file=')) {
    $envFile = substr($arg, strlen('--env-file='));
    if (!str_starts_with($envFile, '/')) {
      $envFile = $projectRoot . '/' . $envFile;
    }
    continue;
  }
  if ($arg === '-h' || $arg === '--help') {
    fwrite(STDERR, "Usage: php scripts/test-mysql-connection.php [--env-file=PATH] [--probe]\n");
    exit(1);
  }
}

Env::load($envFile, true);

$config = MysqlConnection::config();

echo 'CWD: ' . getcwd() . "\n";
echo 'Project: ' . $projectRoot . "\n";
echo 'Env file: ' . (is_file($envFile) ? $envFile : 'NOT FOUND') . "\n";
echo 'Env loaded from: ' . (Env::loadedPath() ?? '(not loaded)') . "\n";
echo 'Endpoint: ' . MysqlConnection::endpointLabel($config) . "\n";
echo 'Database: ' . ($config['database'] !== '' ? $config['database'] : '(empty)') . "\n";
echo 'User: ' . ($config['user'] !== '' ? $config['user'] : '(empty)') . "\n";

if ($config['password'] === '') {
  echo "Password set: NO\n\n";
  fwrite(STDERR, "DB_PASSWORD пустой. Проверьте файл .env.\n");
  exit(1);
}

echo 'Password set: yes (' . strlen($config['password']) . " chars)\n";
echo 'Password fingerprint (md5): ' . md5($config['password']) . "\n";
echo "  ↑ после смены пароля в панели md5 должен измениться. Сравните с md5 в блокноте локально.\n\n";

try {
  $pdo = MysqlConnection::connect();
  $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
  echo "OK — connected. MySQL {$version}\n";
  exit(0);
} catch (Throwable $e) {
  fwrite(STDERR, $e->getMessage() . "\n\n");
}

if (!$probe) {
  fwrite(STDERR, "Запустите с --probe для проверки localhost / 127.0.0.1 / сокета.\n");
  exit(1);
}

echo "Probe mode:\n";
$results = MysqlConnectionProbe::run($config);
foreach ($results as $result) {
  $status = $result['ok'] ? 'OK' : 'FAIL';
  echo "  [{$status}] {$result['label']}";
  if (!$result['ok'] && $result['error'] !== '') {
    echo ' — ' . $result['error'];
  }
  echo "\n";
}

exit(1);
