<?php

declare(strict_types=1);

namespace PianoTrainer;

use PDO;
use PDOException;

final class MysqlConnectionProbe
{
  /** @param array{host:string,port:string,database:string,user:string,password:string,socket:string} $base
   * @return list<array{label:string,ok:bool,error:string}>
   */
  public static function run(array $base): array
  {
    $attempts = [];

    $attempts[] = ['label' => 'current .env', 'config' => $base];

    if ($base['host'] === 'localhost' && $base['socket'] === '') {
      $tcp = $base;
      $tcp['host'] = '127.0.0.1';
      $attempts[] = ['label' => '127.0.0.1 instead of localhost', 'config' => $tcp];
    }

    foreach (self::commonSockets() as $socket) {
      if ($base['socket'] === $socket) {
        continue;
      }
      $withSocket = $base;
      $withSocket['host'] = 'localhost';
      $withSocket['socket'] = $socket;
      $attempts[] = ['label' => 'socket ' . $socket, 'config' => $withSocket];
    }

    $results = [];
    foreach ($attempts as $attempt) {
      $results[] = self::tryOne($attempt['label'], $attempt['config']);
    }

    return $results;
  }

  /** @return list<string> */
  private static function commonSockets(): array
  {
    $candidates = [
      '/var/run/mysqld/mysqld.sock',
      '/var/lib/mysql/mysql.sock',
      '/tmp/mysql.sock',
      '/var/mysql/mysql.sock',
    ];

    return array_values(array_filter($candidates, static fn(string $path): bool => is_file($path)));
  }

  /** @param array{host:string,port:string,database:string,user:string,password:string,socket:string} $config
   * @return array{label:string,ok:bool,error:string}
   */
  private static function tryOne(string $label, array $config): array
  {
    try {
      $dsn = MysqlConnection::buildDsn($config);
      $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3,
      ]);
      $pdo->query('SELECT 1');

      return ['label' => $label, 'ok' => true, 'error' => ''];
    } catch (PDOException $e) {
      return ['label' => $label, 'ok' => false, 'error' => $e->getMessage()];
    }
  }
}
