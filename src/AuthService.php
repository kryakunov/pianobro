<?php

declare(strict_types=1);

namespace PianoTrainer;

use PDO;

final class AuthService
{
  public function __construct(private readonly PDO $db) {}

  public function startSession(): void
  {
    if (session_status() !== PHP_SESSION_ACTIVE) {
      session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
      ]);
      session_start();
    }
  }

  /** @return array{id:int,email:string,name:string}|null */
  public function currentUser(): ?array
  {
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
      return null;
    }

    $stmt = $this->db->prepare('SELECT id, email, name FROM users WHERE id = :id');
    $stmt->execute(['id' => (int) $id]);
    $user = $stmt->fetch();

    return $user !== false ? [
      'id' => (int) $user['id'],
      'email' => (string) $user['email'],
      'name' => (string) $user['name'],
    ] : null;
  }

  public function requireUser(): ?array
  {
    return $this->currentUser();
  }

  /** @return array{id:int,email:string,name:string} */
  public function register(
    string $name,
    string $email,
    string $password,
    string $passwordConfirm,
    string $honeypot = '',
  ): array {
    $this->assertHoneypotEmpty($honeypot);

    $name = trim($name);
    $email = strtolower(trim($email));

    if ($name === '' || mb_strlen($name) < 2) {
      throw new \InvalidArgumentException('Имя должно содержать минимум 2 символа');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new \InvalidArgumentException('Некорректный email');
    }
    if (strlen($password) < 6) {
      throw new \InvalidArgumentException('Пароль должен быть не короче 6 символов');
    }
    if ($password !== $passwordConfirm) {
      throw new \InvalidArgumentException('Пароли не совпадают');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $this->db->prepare(
      'INSERT INTO users (email, password_hash, name) VALUES (:email, :hash, :name)',
    );

    try {
      $stmt->execute(['email' => $email, 'hash' => $hash, 'name' => $name]);
    } catch (\PDOException $e) {
      if (str_contains($e->getMessage(), 'UNIQUE')) {
        throw new \InvalidArgumentException('Пользователь с таким email уже существует');
      }
      throw $e;
    }

    $userId = (int) $this->db->lastInsertId();
    $_SESSION['user_id'] = $userId;

    return ['id' => $userId, 'email' => $email, 'name' => $name];
  }

  /** @return array{id:int,email:string,name:string} */
  public function login(string $email, string $password): array
  {
    $email = strtolower(trim($email));
    $stmt = $this->db->prepare('SELECT id, email, name, password_hash FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user === false || !password_verify($password, (string) $user['password_hash'])) {
      throw new \InvalidArgumentException('Неверный email или пароль');
    }

    $_SESSION['user_id'] = (int) $user['id'];

    return [
      'id' => (int) $user['id'],
      'email' => (string) $user['email'],
      'name' => (string) $user['name'],
    ];
  }

  public function logout(): void
  {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
  }

  private function assertHoneypotEmpty(string $value): void
  {
    if (trim($value) !== '') {
      throw new \InvalidArgumentException('Не удалось обработать запрос');
    }
  }
}
