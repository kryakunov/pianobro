<?php

declare(strict_types=1);

namespace PianoTrainer;

use PDO;

final class TeacherService
{
  public function __construct(
    private readonly PDO $db,
    private readonly RoadmapService $roadmap,
    private readonly StatsRepository $stats,
    private readonly MailService $mail,
    private readonly ?RoleService $roles = null,
  ) {}

  public function isTeacher(?array $user): bool
  {
    if ($this->roles !== null) {
      return $this->roles->isTeacher($user);
    }

    if ($user === null) {
      return false;
    }

    return in_array(strtolower((string) $user['email']), self::teacherEmails(), true);
  }

  /** @return list<string> */
  public static function teacherEmails(): array
  {
    $raw = Env::get('TEACHER_EMAILS');
    if ($raw === '') {
      return [];
    }

    $emails = [];
    foreach (explode(',', $raw) as $part) {
      $email = strtolower(trim($part));
      if ($email !== '') {
        $emails[] = $email;
      }
    }

    return array_values(array_unique($emails));
  }

  /** @return array<string, mixed> */
  public function getDashboard(int $teacherId): array
  {
    return [
      'students' => $this->listStudents($teacherId),
      'invitations' => $this->listPendingInvitations($teacherId),
      'summary' => $this->getSummary($teacherId),
    ];
  }

  /** @return array<string, int> */
  private function getSummary(int $teacherId): array
  {
    $students = $this->db->prepare(
      'SELECT COUNT(*) FROM teacher_students WHERE teacher_id = :teacher_id',
    );
    $students->execute(['teacher_id' => $teacherId]);

    $pendingInvites = $this->db->prepare(
      'SELECT COUNT(*) FROM teacher_invitations WHERE teacher_id = :teacher_id AND status = \'pending\'',
    );
    $pendingInvites->execute(['teacher_id' => $teacherId]);

    $assignments = $this->db->prepare(
      'SELECT COUNT(*) FROM student_assignments WHERE teacher_id = :teacher_id',
    );
    $assignments->execute(['teacher_id' => $teacherId]);

    $pendingHomework = $this->db->prepare(
      'SELECT COUNT(*) FROM student_assignments WHERE teacher_id = :teacher_id AND status = \'pending\'',
    );
    $pendingHomework->execute(['teacher_id' => $teacherId]);

    return [
      'students' => (int) $students->fetchColumn(),
      'pendingInvitations' => (int) $pendingInvites->fetchColumn(),
      'assignments' => (int) $assignments->fetchColumn(),
      'pendingHomework' => (int) $pendingHomework->fetchColumn(),
    ];
  }

  /** @return array<string, mixed> */
  public function inviteStudent(int $teacherId, string $email): array
  {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new \InvalidArgumentException('Некорректный email');
    }

    $teacher = $this->getUserBrief($teacherId);
    if ($teacher === null) {
      throw new \InvalidArgumentException('Преподаватель не найден');
    }

    if (strtolower((string) $teacher['email']) === $email) {
      throw new \InvalidArgumentException('Нельзя пригласить самого себя');
    }

    $existingUser = $this->findUserIdByEmail($email);
    if ($existingUser !== null) {
      $this->linkStudent($teacherId, $existingUser);
      $this->markInvitationsAccepted($teacherId, $email, $existingUser);

      return [
        'status' => 'linked',
        'email' => $email,
        'message' => 'Ученик уже зарегистрирован и добавлен в ваш список',
      ];
    }

    $pending = $this->db->prepare(
      'SELECT id, token FROM teacher_invitations
       WHERE teacher_id = :teacher_id AND email = :email AND status = \'pending\'',
    );
    $pending->execute(['teacher_id' => $teacherId, 'email' => $email]);
    $existingInvite = $pending->fetch();

    $token = $existingInvite !== false
      ? (string) $existingInvite['token']
      : $this->createInvitation($teacherId, $email);

    $inviteUrl = AppUrl::canonical('/?invite=' . urlencode($token));
    $teacherName = (string) $teacher['name'];
    $subject = "{$teacherName} приглашает вас в Piano Bro";
    $bodyText = <<<TEXT
Здравствуйте!

{$teacherName} приглашает вас зарегистрироваться в Piano Bro и заниматься под его руководством.

Перейдите по ссылке для регистрации:
{$inviteUrl}

После регистрации вы появитесь в кабинете преподавателя, а он сможет назначать вам задания и отслеживать прогресс.

— Piano Bro
TEXT;

    $bodyHtml = '<p>Здравствуйте!</p>'
      . '<p><strong>' . htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8') . '</strong> приглашает вас зарегистрироваться в Piano Bro и заниматься под его руководством.</p>'
      . '<p><a href="' . htmlspecialchars($inviteUrl, ENT_QUOTES, 'UTF-8') . '">Зарегистрироваться</a></p>'
      . '<p>После регистрации вы появитесь в кабинете преподавателя.</p>';

    $sent = $this->mail->send($email, $subject, $bodyText, $bodyHtml);
    if (!$sent && strtolower(Env::get('MAIL_DRIVER', 'log')) === 'log') {
      return [
        'status' => 'invited',
        'email' => $email,
        'message' => 'Приглашение создано (письмо записано в data/mail-log/)',
      ];
    }

    if (!$sent) {
      $details = $this->mail->getLastError();
      $message = 'Не удалось отправить письмо. Проверьте настройки SMTP в .env.';
      if ($details !== null && $details !== '') {
        $message .= ' ' . $details;
      }
      throw new \RuntimeException($message);
    }

    return [
      'status' => 'invited',
      'email' => $email,
      'message' => 'Приглашение отправлено на почту',
    ];
  }

  /** @return array<string, mixed>|null */
  public function getInvitationPreview(string $token): ?array
  {
    $token = trim($token);
    if ($token === '') {
      return null;
    }

    $stmt = $this->db->prepare(
      'SELECT i.email, i.status, u.name AS teacher_name
       FROM teacher_invitations i
       JOIN users u ON u.id = i.teacher_id
       WHERE i.token = :token',
    );
    $stmt->execute(['token' => $token]);
    $row = $stmt->fetch();
    if ($row === false || (string) $row['status'] !== 'pending') {
      return null;
    }

    return [
      'email' => (string) $row['email'],
      'teacherName' => (string) $row['teacher_name'],
    ];
  }

  public function acceptInvitationToken(int $studentId, string $token): void
  {
    $token = trim($token);
    if ($token === '') {
      return;
    }

    $stmt = $this->db->prepare(
      'SELECT id, teacher_id, email FROM teacher_invitations
       WHERE token = :token AND status = \'pending\'',
    );
    $stmt->execute(['token' => $token]);
    $invite = $stmt->fetch();
    if ($invite === false) {
      return;
    }

    $student = $this->getUserBrief($studentId);
    if ($student === null) {
      return;
    }

    if (strtolower((string) $student['email']) !== strtolower((string) $invite['email'])) {
      throw new \InvalidArgumentException('Email аккаунта не совпадает с приглашением');
    }

    $teacherId = (int) $invite['teacher_id'];
    $this->linkStudent($teacherId, $studentId);
    $this->markInvitationAccepted((int) $invite['id'], $studentId);
  }

  public function acceptPendingInvitationsForEmail(int $studentId, string $email): void
  {
    $email = strtolower(trim($email));
    $stmt = $this->db->prepare(
      'SELECT id, teacher_id FROM teacher_invitations
       WHERE email = :email AND status = \'pending\'',
    );
    $stmt->execute(['email' => $email]);

    foreach ($stmt->fetchAll() as $row) {
      $this->linkStudent((int) $row['teacher_id'], $studentId);
      $this->markInvitationAccepted((int) $row['id'], $studentId);
    }
  }

  /** @return list<array<string, mixed>> */
  public function listStudents(int $teacherId): array
  {
    $stmt = $this->db->prepare(
      'SELECT u.id, u.name, u.email, u.last_login_at, ts.joined_at
       FROM teacher_students ts
       JOIN users u ON u.id = ts.student_id
       WHERE ts.teacher_id = :teacher_id
       ORDER BY u.name COLLATE NOCASE',
    );
    $stmt->execute(['teacher_id' => $teacherId]);

    $students = [];
    foreach ($stmt->fetchAll() as $row) {
      $studentId = (int) $row['id'];
      $stats = $this->stats->getNoteStats($studentId);
      $roadmap = $this->roadmap->getRoadmap($studentId);
      $pendingCount = $this->countPendingAssignments($teacherId, $studentId);

      $students[] = [
        'id' => $studentId,
        'name' => (string) $row['name'],
        'email' => (string) $row['email'],
        'joinedAt' => (string) $row['joined_at'],
        'lastLoginAt' => $row['last_login_at'],
        'masteredNotes' => (int) ($stats['summary']['mastered'] ?? 0),
        'totalAttempts' => (int) ($stats['summary']['totalAttempts'] ?? 0),
        'roadmap' => [
          'totalXp' => (int) ($roadmap['progress']['totalXp'] ?? 0),
          'rank' => $roadmap['progress']['rank'] ?? ['title' => '—'],
          'completedCount' => (int) ($roadmap['progress']['completedCount'] ?? 0),
          'totalStages' => (int) ($roadmap['progress']['totalStages'] ?? 0),
        ],
        'pendingAssignments' => $pendingCount,
      ];
    }

    return $students;
  }

  /** @return list<array<string, mixed>> */
  private function listPendingInvitations(int $teacherId): array
  {
    $stmt = $this->db->prepare(
      'SELECT email, created_at FROM teacher_invitations
       WHERE teacher_id = :teacher_id AND status = \'pending\'
       ORDER BY datetime(created_at) DESC',
    );
    $stmt->execute(['teacher_id' => $teacherId]);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
      $items[] = [
        'email' => (string) $row['email'],
        'createdAt' => (string) $row['created_at'],
      ];
    }

    return $items;
  }

  /** @return array<string, mixed> */
  public function getStudentStats(int $teacherId, int $studentId): array
  {
    $this->assertTeacherHasStudent($teacherId, $studentId);

    $user = $this->getUserBrief($studentId);
    if ($user === null) {
      throw new \InvalidArgumentException('Ученик не найден');
    }

    return [
      'user' => $user,
      'noteStats' => $this->stats->getNoteStats($studentId),
      'roadmap' => $this->roadmap->getRoadmap($studentId),
      'assignments' => $this->listStudentAssignments($teacherId, $studentId),
    ];
  }

  /**
   * @param array<string, mixed> $payload
   * @return array<string, mixed>
   */
  public function createStudentAssignment(
    int $teacherId,
    int $studentId,
    string $title,
    string $type,
    array $payload,
    ?string $dueAt = null,
  ): array {
    $this->assertTeacherHasStudent($teacherId, $studentId);

    $title = trim($title);
    if ($title === '') {
      throw new \InvalidArgumentException('Укажите название задания');
    }

    if (!in_array($type, ['notes', 'melody'], true)) {
      throw new \InvalidArgumentException('Неверный тип задания');
    }

    if ($type === 'melody' && empty($payload['lessonId'])) {
      throw new \InvalidArgumentException('Выберите мелодию');
    }

    if ($type === 'notes') {
      $sessionLimit = (int) ($payload['sessionLimit'] ?? 0);
      if (!in_array($sessionLimit, [10, 20, 30, 50], true)) {
        throw new \InvalidArgumentException('Выберите длину сессии: 10, 20, 30 или 50 нот');
      }
      if (empty($payload['settings']) || !is_array($payload['settings'])) {
        throw new \InvalidArgumentException('Укажите настройки тренировки нот');
      }
    }

    $stmt = $this->db->prepare(
      'INSERT INTO student_assignments (teacher_id, student_id, title, type, payload_json, due_at)
       VALUES (:teacher_id, :student_id, :title, :type, :payload, :due_at)',
    );
    $stmt->execute([
      'teacher_id' => $teacherId,
      'student_id' => $studentId,
      'title' => $title,
      'type' => $type,
      'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
      'due_at' => $dueAt,
    ]);

    return [
      'id' => (int) $this->db->lastInsertId(),
      'title' => $title,
    ];
  }

  public function addAssignmentComment(int $teacherId, int $assignmentId, string $comment): void
  {
    $comment = trim($comment);
    if ($comment === '') {
      throw new \InvalidArgumentException('Комментарий не может быть пустым');
    }

    $stmt = $this->db->prepare(
      'SELECT id FROM student_assignments WHERE id = :id AND teacher_id = :teacher_id',
    );
    $stmt->execute(['id' => $assignmentId, 'teacher_id' => $teacherId]);
    if ($stmt->fetch() === false) {
      throw new \InvalidArgumentException('Задание не найдено');
    }

    $update = $this->db->prepare(
      'UPDATE student_assignments
       SET teacher_comment = :comment,
           commented_at = datetime(\'now\'),
           status = CASE WHEN status = \'pending\' THEN status ELSE \'reviewed\' END
       WHERE id = :id',
    );
    $update->execute(['comment' => $comment, 'id' => $assignmentId]);
  }

  /** @return list<array<string, mixed>> */
  public function listHomeworkForStudent(int $studentId): array
  {
    $stmt = $this->db->prepare(
      'SELECT sa.id, sa.title, sa.type, sa.payload_json, sa.due_at, sa.status,
              sa.result_json, sa.teacher_comment, sa.commented_at, sa.completed_at, sa.created_at,
              u.name AS teacher_name
       FROM student_assignments sa
       JOIN users u ON u.id = sa.teacher_id
       WHERE sa.student_id = :student_id
       ORDER BY
         CASE WHEN sa.status = \'pending\' THEN 0 ELSE 1 END,
         datetime(sa.due_at) ASC,
         datetime(sa.created_at) DESC',
    );
    $stmt->execute(['student_id' => $studentId]);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
      $items[] = [
        'submissionId' => (int) $row['id'],
        'assignmentId' => (int) $row['id'],
        'title' => (string) $row['title'],
        'type' => (string) $row['type'],
        'payload' => json_decode((string) $row['payload_json'], true) ?: [],
        'teacherName' => (string) $row['teacher_name'],
        'status' => (string) $row['status'],
        'dueAt' => $row['due_at'],
        'createdAt' => (string) $row['created_at'],
        'completedAt' => $row['completed_at'],
        'result' => json_decode((string) ($row['result_json'] ?? ''), true),
        'teacherComment' => $row['teacher_comment'],
        'commentedAt' => $row['commented_at'],
      ];
    }

    return $items;
  }

  /**
   * @param array<string, mixed> $result
   * @param list<array<string, mixed>> $errors
   */
  public function completeAssignment(
    int $studentId,
    int $assignmentId,
    array $result,
    array $errors = [],
  ): void {
    $stmt = $this->db->prepare(
      'SELECT id, payload_json FROM student_assignments
       WHERE id = :id AND student_id = :student_id',
    );
    $stmt->execute(['id' => $assignmentId, 'student_id' => $studentId]);
    $row = $stmt->fetch();
    if ($row === false) {
      throw new \InvalidArgumentException('Задание не найдено');
    }

    $payload = json_decode((string) $row['payload_json'], true) ?: [];
    $minAccuracy = (int) ($payload['minAccuracy'] ?? 0);
    $accuracy = (int) ($result['accuracy'] ?? 0);
    $status = ($minAccuracy === 0 || $accuracy >= $minAccuracy) ? 'completed' : 'submitted';

    $update = $this->db->prepare(
      'UPDATE student_assignments
       SET status = :status,
           result_json = :result,
           errors_json = :errors,
           completed_at = datetime(\'now\')
       WHERE id = :id',
    );
    $update->execute([
      'status' => $status,
      'result' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
      'errors' => json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
      'id' => $assignmentId,
    ]);
  }

  /** @return list<array<string, mixed>> */
  private function listStudentAssignments(int $teacherId, int $studentId): array
  {
    $stmt = $this->db->prepare(
      'SELECT id, title, type, payload_json, due_at, status, result_json, errors_json,
              teacher_comment, commented_at, completed_at, created_at
       FROM student_assignments
       WHERE teacher_id = :teacher_id AND student_id = :student_id
       ORDER BY datetime(created_at) DESC',
    );
    $stmt->execute(['teacher_id' => $teacherId, 'student_id' => $studentId]);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
      $items[] = [
        'id' => (int) $row['id'],
        'title' => (string) $row['title'],
        'type' => (string) $row['type'],
        'payload' => json_decode((string) $row['payload_json'], true) ?: [],
        'dueAt' => $row['due_at'],
        'status' => (string) $row['status'],
        'result' => json_decode((string) ($row['result_json'] ?? ''), true),
        'errors' => json_decode((string) ($row['errors_json'] ?? ''), true) ?: [],
        'teacherComment' => $row['teacher_comment'],
        'commentedAt' => $row['commented_at'],
        'completedAt' => $row['completed_at'],
        'createdAt' => (string) $row['created_at'],
      ];
    }

    return $items;
  }

  private function countPendingAssignments(int $teacherId, int $studentId): int
  {
    $stmt = $this->db->prepare(
      'SELECT COUNT(*) FROM student_assignments
       WHERE teacher_id = :teacher_id AND student_id = :student_id AND status = \'pending\'',
    );
    $stmt->execute(['teacher_id' => $teacherId, 'student_id' => $studentId]);

    return (int) $stmt->fetchColumn();
  }

  private function linkStudent(int $teacherId, int $studentId): void
  {
    if ($teacherId === $studentId) {
      return;
    }

    $stmt = $this->db->prepare(
      'INSERT OR IGNORE INTO teacher_students (teacher_id, student_id) VALUES (:teacher_id, :student_id)',
    );
    $stmt->execute(['teacher_id' => $teacherId, 'student_id' => $studentId]);
    $this->roles?->grantRole($studentId, RoleService::ROLE_STUDENT);
  }

  private function createInvitation(int $teacherId, string $email): string
  {
    do {
      $token = bin2hex(random_bytes(16));
      $check = $this->db->prepare('SELECT 1 FROM teacher_invitations WHERE token = :token');
      $check->execute(['token' => $token]);
      $exists = $check->fetch() !== false;
    } while ($exists);

    $stmt = $this->db->prepare(
      'INSERT INTO teacher_invitations (teacher_id, email, token) VALUES (:teacher_id, :email, :token)',
    );
    $stmt->execute(['teacher_id' => $teacherId, 'email' => $email, 'token' => $token]);

    return $token;
  }

  private function markInvitationAccepted(int $invitationId, int $studentId): void
  {
    $stmt = $this->db->prepare(
      'UPDATE teacher_invitations
       SET status = \'accepted\', accepted_at = datetime(\'now\'), student_id = :student_id
       WHERE id = :id',
    );
    $stmt->execute(['student_id' => $studentId, 'id' => $invitationId]);
  }

  private function markInvitationsAccepted(int $teacherId, string $email, int $studentId): void
  {
    $stmt = $this->db->prepare(
      'UPDATE teacher_invitations
       SET status = \'accepted\', accepted_at = datetime(\'now\'), student_id = :student_id
       WHERE teacher_id = :teacher_id AND email = :email AND status = \'pending\'',
    );
    $stmt->execute(['student_id' => $studentId, 'teacher_id' => $teacherId, 'email' => $email]);
  }

  /** @return array{id:int,name:string,email:string,createdAt?:string,lastLoginAt?:?string}|null */
  private function getUserBrief(int $userId): ?array
  {
    $stmt = $this->db->prepare(
      'SELECT id, name, email, created_at, last_login_at FROM users WHERE id = :id',
    );
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();
    if ($row === false) {
      return null;
    }

    return [
      'id' => (int) $row['id'],
      'name' => (string) $row['name'],
      'email' => (string) $row['email'],
      'createdAt' => (string) $row['created_at'],
      'lastLoginAt' => $row['last_login_at'],
    ];
  }

  private function findUserIdByEmail(string $email): ?int
  {
    $stmt = $this->db->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
  }

  private function assertTeacherHasStudent(int $teacherId, int $studentId): void
  {
    $stmt = $this->db->prepare(
      'SELECT 1 FROM teacher_students WHERE teacher_id = :teacher_id AND student_id = :student_id',
    );
    $stmt->execute(['teacher_id' => $teacherId, 'student_id' => $studentId]);
    if ($stmt->fetch() === false) {
      throw new \InvalidArgumentException('Ученик не найден');
    }
  }
}
