<?php

declare(strict_types=1);

namespace PianoTrainer;

final class MailService
{
  public function send(string $to, string $subject, string $bodyText, ?string $bodyHtml = null): bool
  {
    $to = trim($to);
    if ($to === '') {
      return false;
    }

    $driver = strtolower(Env::get('MAIL_DRIVER', 'mail'));
    if ($driver === 'log') {
      $this->logMessage($to, $subject, $bodyText);

      return true;
    }

    $from = Env::get('MAIL_FROM', 'Piano Bro <noreply@localhost>');
    $encodedSubject = $this->encodeHeader($subject);
    $headers = [
      'MIME-Version: 1.0',
      'From: ' . $from,
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

    return @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
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
