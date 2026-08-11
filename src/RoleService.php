<?php

declare(strict_types=1);

namespace PianoTrainer;

use PDO;

final class RoleService
{
  public const ROLE_TEACHER = 'teacher';
  public const ROLE_STUDENT = 'student';

  public function __construct(private readonly PDO $db) {}

  /** @return list<string> */
  public function getRoles(int $userId): array
  {
    $stmt = $this->db->prepare(
      'SELECT role FROM user_roles WHERE user_id = :user_id ORDER BY role',
    );
    $stmt->execute(['user_id' => $userId]);

    return array_map(
      static fn(array $row): string => (string) $row['role'],
      $stmt->fetchAll(),
    );
  }

  public function hasRole(int $userId, string $role): bool
  {
    $stmt = $this->db->prepare(
      'SELECT 1 FROM user_roles WHERE user_id = :user_id AND role = :role LIMIT 1',
    );
    $stmt->execute(['user_id' => $userId, 'role' => $role]);

    return $stmt->fetch() !== false;
  }

  public function grantRole(int $userId, string $role): void
  {
    $stmt = $this->db->prepare(
      'INSERT OR IGNORE INTO user_roles (user_id, role) VALUES (:user_id, :role)',
    );
    $stmt->execute(['user_id' => $userId, 'role' => $role]);

    if ($role === self::ROLE_TEACHER) {
      $this->syncLegacyUserRole($userId, self::ROLE_TEACHER);
    }
  }

  public function revokeRole(int $userId, string $role): void
  {
    $stmt = $this->db->prepare(
      'DELETE FROM user_roles WHERE user_id = :user_id AND role = :role',
    );
    $stmt->execute(['user_id' => $userId, 'role' => $role]);

    if ($role === self::ROLE_TEACHER) {
      $this->syncLegacyUserRole($userId, self::ROLE_STUDENT);
    }
  }

  /** @return array<int, list<string>> */
  public function getRolesByUser(): array
  {
    $stmt = $this->db->query('SELECT user_id, role FROM user_roles ORDER BY user_id, role');
    $map = [];

    foreach ($stmt->fetchAll() as $row) {
      $userId = (int) $row['user_id'];
      $map[$userId][] = (string) $row['role'];
    }

    return $map;
  }

  public function isTeacher(?array $user): bool
  {
    if ($user === null) {
      return false;
    }

    if ($this->hasRole((int) $user['id'], self::ROLE_TEACHER)) {
      return true;
    }

    return in_array(strtolower((string) ($user['email'] ?? '')), TeacherService::teacherEmails(), true);
  }

  public function isStudent(?array $user): bool
  {
    if ($user === null) {
      return false;
    }

    return $this->hasRole((int) $user['id'], self::ROLE_STUDENT);
  }

  public function syncTeacherFromEnv(int $userId, string $email): void
  {
    $email = strtolower(trim($email));
    if (!in_array($email, TeacherService::teacherEmails(), true)) {
      return;
    }

    $this->grantRole($userId, self::ROLE_TEACHER);
  }

  private function syncLegacyUserRole(int $userId, string $role): void
  {
    $stmt = $this->db->prepare('UPDATE users SET role = :role WHERE id = :id');
    $stmt->execute(['id' => $userId, 'role' => $role]);
  }
}
