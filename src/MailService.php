<?php

declare(strict_types=1);

namespace PianoTrainer;

final class MailService
{
  private ?string $lastError = null;

  public function send(string $to, string $subject, string $bodyText, ?string $bodyHtml = null): bool
  {
    $this->lastError = null;
    $to = trim($to);
    if ($to === '') {
      $this->lastError = 'Пустой адрес получателя';

      return false;
    }

    $driver = strtolower(Env::get('MAIL_DRIVER', 'log'));
    if ($driver === 'log') {
      $this->logMessage($to, $subject, $bodyText);

      return true;
    }

    $from = Env::get('MAIL_FROM', 'Piano Bro <noreply@localhost>');
    $encodedSubject = $this->encodeHeader($subject);
    $headers = [
      'MIME-Version: 1.0',
      'Reply-To: ' . $from,
      'X-Mailer: PianoBro',
    ];

    if ($bodyHtml !== null) {
      $boundary = 'pb_' . bin2hex(random_bytes(8));
      $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
      $body = "--{$boundary}\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
        . $bodyText . "\r\n\r\n"
        . "--{$boundary}\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
        . $bodyHtml . "\r\n\r\n"
        . "--{$boundary}--";
    } else {
      $headers[] = 'Content-Type: text/plain; charset=UTF-8';
      $body = $bodyText;
    }

    if ($driver === 'smtp') {
      try {
        $this->sendViaSmtp($from, $to, $encodedSubject, $body, $headers);

        return true;
      } catch (\Throwable $e) {
        $this->lastError = $e->getMessage();

        return false;
      }
    }

    $headers[] = 'From: ' . $from;
    $sent = @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
    if (!$sent) {
      $this->lastError = 'PHP mail() не смог отправить письмо. Настройте SMTP (MAIL_DRIVER=smtp).';
    }

    return $sent;
  }

  public function getLastError(): ?string
  {
    return $this->lastError;
  }

  /** @param list<string> $headers */
  private function sendViaSmtp(string $from, string $to, string $subject, string $body, array $headers): void
  {
    $mailer = new SmtpMailer(
      Env::get('SMTP_HOST'),
      (int) Env::get('SMTP_PORT', '587'),
      Env::get('SMTP_USER'),
      Env::get('SMTP_PASS'),
      strtolower(Env::get('SMTP_ENCRYPTION', 'tls')),
    );

    $mailer->send($from, $to, $subject, $body, $headers);
  }

  private function logMessage(string $to, string $subject, string $bodyText): void
  {
    $dir = dirname(__DIR__) . '/data/mail-log';
    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }

    $file = $dir . '/' . date('Y-m-d') . '.log';
    $entry = sprintf(
      "[%s] TO: %s\nSUBJECT: %s\n%s\n%s\n",
      date('c'),
      $to,
      $subject,
      str_repeat('-', 40),
      $bodyText,
    );
    file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
  }

  private function encodeHeader(string $value): string
  {
    if (preg_match('/[^\x20-\x7E]/', $value)) {
      return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    return $value;
  }
}
