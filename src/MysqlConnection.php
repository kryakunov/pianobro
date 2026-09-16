<?php

declare(strict_types=1);

namespace PianoTrainer;

use PDO;
use PDOException;

final class MysqlConnection
{
  /** @return array{host:string,port:string,database:string,user:string,password:string,socket:string} */
  public static function config(): array
  {
    return [
      'host' => trim(Env::get('DB_HOST', '127.0.0.1')),
      'port' => trim(Env::get('DB_PORT', '3306')),
      'database' => trim(Env::get('DB_NAME', '')),
      'user' => trim(Env::get('DB_USER', '')),
      'password' => Env::get('DB_PASSWORD', ''),
      'socket' => trim(Env::get('DB_SOCKET', '')),
    ];
  }

  /** @param array{host:string,port:string,database:string,user:string,password:string,socket:string} $config */
  public static function buildDsn(array $config): string
  {
    if ($config['socket'] !== '') {
      return sprintf(
        'mysql:unix_socket=%s;dbname=%s;charset=utf8mb4',
        $config['socket'],
        $config['database'],
      );
    }

    return sprintf(
      'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
      $config['host'],
      $config['port'],
      $config['database'],
    );
  }

  public static function endpointLabel(array $config): string
  {
    if ($config['socket'] !== '') {
      return $config['user'] . '@socket:' . $config['socket'];
    }

    return $config['user'] . '@' . $config['host'] . ':' . $config['port'];
  }

  public static function connect(): PDO
  {
    $config = self::config();
    if ($config['database'] === '' || $config['user'] === '') {
      throw new \RuntimeException('MySQL: задайте DB_NAME и DB_USER в .env');
    }

    if ($config['password'] === '') {
      throw new \RuntimeException(
        'MySQL: DB_PASSWORD пустой. Проверьте .env в корне проекта (рядом с composer.json). '
        . 'Если в пароле есть #, пробелы или кавычки — возьмите значение в двойные кавычки: DB_PASSWORD="p@ss#word"',
      );
    }

    $dsn = self::buildDsn($config);

    try {
      $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ]);
      $pdo->exec("SET time_zone = '+00:00'");

      return $pdo;
    } catch (PDOException $e) {
      $hint = self::hintForAccessDenied($e, $config);
      throw new \RuntimeException(
        'MySQL: не удалось подключиться (' . self::endpointLabel($config) . ', БД '
        . $config['database'] . '): ' . $e->getMessage() . $hint,
        (int) $e->getCode(),
        $e,
      );
    }
  }

  /** @param array{host:string,port:string,database:string,user:string,password:string,socket:string} $config */
  private static function hintForAccessDenied(PDOException $e, array $config): string
  {
    if (!str_contains($e->getMessage(), '1045')) {
      return '';
    }

    $lines = [
      '',
      ' Подсказка (1045 Access denied):',
      ' — логин/пароль/имя БД должны совпадать с панелью хостинга (часто префикс host1380688_);',
      ' — пароль с символами # $ ! пробелами укажите в .env в кавычках: DB_PASSWORD="...";',
      ' — запускайте скрипт на том же сервере, где сайт (SSH), если MySQL не принимает внешние подключения;',
      ' — если в панели указан сокет, добавьте DB_SOCKET=/path/to/mysql.sock и DB_HOST=localhost;',
      ' — проверка: php scripts/test-mysql-connection.php',
    ];

    return implode("\n", $lines);
  }
}
