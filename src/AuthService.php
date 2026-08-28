<?php

declare(strict_types=1);

namespace PianoTrainer;

use PDO;

final class AuthService
{
  private ?TeacherService $teacher = null;
  private ?RoleService $roles = null;
  private ?SubscriptionService $subscriptions = null;

  public function __construct(private readonly PDO $db) {}

  public function setSubscriptionService(SubscriptionService $subscriptions): void
  {
    $this->subscriptions = $subscriptions;
  }

  public function setTeacherService(TeacherService $teacher): void
  {
    $this->teacher = $teacher;
  }

  public function setRoleService(RoleService $roles): void
  {
    $this->roles = $roles;
  }

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

  /** @return array{id:int,email:string,name:string,roles:list<string>,subscription?:array<string,mixed>}|null */
  public function currentUser(): ?array
  {
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
      return null;
    }

    $userId = (int) $id;
    $stmt = $this->db->prepare('SELECT id, email, name FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if ($user === false) {
      return null;
    }

    $this->syncRoleFromEnv($userId, (string) $user['email']);

    $result = [
      'id' => $userId,
      'email' => (string) $user['email'],
      'name' => (string) $user['name'],
      'roles' => $this->roles?->getRoles($userId) ?? [],
    ];

    if ($this->subscriptions !== null) {
      $result['subscription'] = $this->subscriptions->getForUser($userId);
    }

    return $result;
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
    string $inviteToken = '',
    bool $isTeacher = false,
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
    $this->touchLastLogin($userId);
    $this->syncRoleFromEnv($userId, $email);
    if ($isTeacher && trim($inviteToken) === '') {
      $this->roles?->grantRole($userId, RoleService::ROLE_TEACHER);
    }
    $this->linkTeacherInvitations($userId, $email, $inviteToken);

    return $this->currentUser() ?? [
      'id' => $userId,
      'email' => $email,
      'name' => $name,
      'roles' => $this->roles?->getRoles($userId) ?? [],
    ];
  }

  /** @return array{id:int,email:string,name:string} */
  public function login(string $email, string $password, string $inviteToken = ''): array
  {
    $email = strtolower(trim($email));
    $stmt = $this->db->prepare('SELECT id, email, name, password_hash FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user === false) {
      throw new \InvalidArgumentException('Неверный email или пароль');
    }
    if (empty($user['password_hash'])) {
      throw new \InvalidArgumentException('Этот аккаунт создан через соцсеть — войдите через неё');
    }
    if (!password_verify($password, (string) $user['password_hash'])) {
      throw new \InvalidArgumentException('Неверный email или пароль');
    }

    $_SESSION['user_id'] = (int) $user['id'];
    $this->touchLastLogin((int) $user['id']);
    $this->syncRoleFromEnv((int) $user['id'], (string) $user['email']);
    $this->linkTeacherInvitations((int) $user['id'], (string) $user['email'], $inviteToken);

    $current = $this->currentUser();
    if ($current !== null) {
      return $current;
    }

    return [
      'id' => (int) $user['id'],
      'email' => (string) $user['email'],
      'name' => (string) $user['name'],
      'roles' => $this->roles?->getRoles((int) $user['id']) ?? [],
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

  /** @return array{id:int,email:string,name:string} */
  public function loginWithOAuth(
    string $provider,
    string $providerUserId,
    ?string $email,
    string $name,
  ): array {
    $providerUserId = trim($providerUserId);
    $name = trim($name);
    $email = $email !== null ? strtolower(trim($email)) : null;

    if ($providerUserId === '') {
      throw new \InvalidArgumentException('Не удалось получить профиль');
    }
    if ($name === '') {
      $name = 'Пользователь';
    }

    $stmt = $this->db->prepare(
      'SELECT u.id, u.email, u.name
       FROM oauth_accounts oa
       JOIN users u ON u.id = oa.user_id
       WHERE oa.provider = :provider AND oa.provider_user_id = :provider_user_id',
    );
    $stmt->execute(['provider' => $provider, 'provider_user_id' => $providerUserId]);
    $existingOAuth = $stmt->fetch();
    if ($existingOAuth !== false) {
      $_SESSION['user_id'] = (int) $existingOAuth['id'];
      $this->touchLastLogin((int) $existingOAuth['id']);
      $this->syncRoleFromEnv((int) $existingOAuth['id'], (string) $existingOAuth['email']);

      return $this->currentUser() ?? [
        'id' => (int) $existingOAuth['id'],
        'email' => (string) $existingOAuth['email'],
        'name' => (string) $existingOAuth['name'],
        'roles' => $this->roles?->getRoles((int) $existingOAuth['id']) ?? [],
      ];
    }

    $userId = null;
    if ($email !== null && $email !== '') {
      $stmt = $this->db->prepare('SELECT id, email, name FROM users WHERE email = :email');
      $stmt->execute(['email' => $email]);
      $existingUser = $stmt->fetch();
      if ($existingUser !== false) {
        $userId = (int) $existingUser['id'];
      }
    }

    if ($userId === null) {
      if ($email === null || $email === '') {
        $email = $provider . '_' . $providerUserId . '@oauth.local';
      }

      $insert = $this->db->prepare(
        'INSERT INTO users (email, password_hash, name) VALUES (:email, NULL, :name)',
      );
      try {
        $insert->execute(['email' => $email, 'name' => $name]);
      } catch (\PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE') && $email !== null) {
          $stmt = $this->db->prepare('SELECT id FROM users WHERE email = :email');
          $stmt->execute(['email' => $email]);
          $row = $stmt->fetch();
          if ($row === false) {
            throw $e;
          }
          $userId = (int) $row['id'];
        } else {
          throw $e;
        }
      }

      if ($userId === null) {
        $userId = (int) $this->db->lastInsertId();
      }
    }

    $link = $this->db->prepare(
      'INSERT INTO oauth_accounts (user_id, provider, provider_user_id)
       VALUES (:user_id, :provider, :provider_user_id)',
    );
    $link->execute([
      'user_id' => $userId,
      'provider' => $provider,
      'provider_user_id' => $providerUserId,
    ]);

    $_SESSION['user_id'] = $userId;
    $this->touchLastLogin($userId);
    $this->syncRoleFromEnv($userId, (string) ($email ?? ''));
    if ($email !== null && $email !== '') {
      $this->linkTeacherInvitations($userId, $email);
    }

    $current = $this->currentUser();
    if ($current !== null) {
      return $current;
    }

    throw new \RuntimeException('User not found after OAuth login');
  }

  private function assertHoneypotEmpty(string $value): void
  {
    if (trim($value) !== '') {
      throw new \InvalidArgumentException('Не удалось обработать запрос');
    }
  }

  private function touchLastLogin(int $userId): void
  {
    $stmt = $this->db->prepare('UPDATE users SET last_login_at = datetime(\'now\') WHERE id = :id');
    $stmt->execute(['id' => $userId]);
  }

  private function syncRoleFromEnv(int $userId, string $email): void
  {
    $this->roles?->syncTeacherFromEnv($userId, $email);
  }

  private function linkTeacherInvitations(int $userId, string $email, string $inviteToken = ''): void
  {
    if ($this->teacher === null) {
      return;
    }

    if ($inviteToken !== '') {
      try {
        $this->teacher->acceptInvitationToken($userId, $inviteToken);
      } catch (\InvalidArgumentException) {
        // ignore invalid token
      }
    }

    $this->teacher->acceptPendingInvitationsForEmail($userId, $email);
  }
}
