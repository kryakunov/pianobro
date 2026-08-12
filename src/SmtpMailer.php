<?php

declare(strict_types=1);

namespace PianoTrainer;

final class SmtpMailer
{
  public function __construct(
    private readonly string $host,
    private readonly int $port,
    private readonly string $user,
    private readonly string $pass,
    private readonly string $encryption,
  ) {}

  public function send(string $from, string $to, string $subject, string $body, array $headers = []): void
  {
    if ($this->host === '') {
      throw new \RuntimeException('SMTP_HOST не задан');
    }

    $fromEmail = $this->extractEmail($from);
    $socket = $this->connect();
    $this->command($socket, 'MAIL FROM:<' . $fromEmail . '>', 250);
    $this->command($socket, 'RCPT TO:<' . $to . '>', 250);
    $this->command($socket, 'DATA', 354);

    $message = 'Subject: ' . $subject . "\r\n"
      . 'To: <' . $to . ">\r\n"
      . 'From: ' . $from . "\r\n"
      . implode("\r\n", $headers) . "\r\n\r\n"
      . $this->normalizeBody($body);

    fwrite($socket, $message . "\r\n.\r\n");
    $this->expect($socket, 250);
    $this->command($socket, 'QUIT', 221);
    fclose($socket);
  }

  /** @return resource */
  private function connect()
  {
    $remote = $this->encryption === 'ssl'
      ? 'ssl://' . $this->host . ':' . $this->port
      : 'tcp://' . $this->host . ':' . $this->port;

    $socket = @stream_socket_client($remote, $errno, $errstr, 30, STREAM_CLIENT_CONNECT);
    if ($socket === false) {
      throw new \RuntimeException('Не удалось подключиться к SMTP: ' . ($errstr ?: (string) $errno));
    }

    stream_set_timeout($socket, 30);
    $this->expect($socket, 220);
    $this->command($socket, 'EHLO piano-bro.local', 250);

    if ($this->encryption === 'tls') {
      $this->command($socket, 'STARTTLS', 220);
      if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        throw new \RuntimeException('Не удалось установить TLS-соединение с SMTP');
      }
      $this->command($socket, 'EHLO piano-bro.local', 250);
    }

    if ($this->user !== '') {
      $this->command($socket, 'AUTH LOGIN', 334);
      $this->command($socket, base64_encode($this->user), 334);
      $this->command($socket, base64_encode($this->pass), 235);
    }

    return $socket;
  }

  /** @param resource $socket */
  private function command($socket, string $command, int $expectedCode): void
  {
    fwrite($socket, $command . "\r\n");
    $this->expect($socket, $expectedCode);
  }

  /** @param resource $socket */
  private function expect($socket, int $expectedCode): void
  {
    $response = $this->readResponse($socket);
    $code = (int) substr($response, 0, 3);
    if ($code !== $expectedCode) {
      throw new \RuntimeException(trim($response));
    }
  }

  /** @param resource $socket */
  private function readResponse($socket): string
  {
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
      $response .= $line;
      if (isset($line[3]) && $line[3] === ' ') {
        break;
      }
    }

    if ($response === '') {
      throw new \RuntimeException('SMTP-сервер не ответил');
    }

    return $response;
  }

  private function normalizeBody(string $body): string
  {
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $body);
    $normalized = [];

    foreach ($lines as $line) {
      if (str_starts_with($line, '.')) {
        $line = '.' . $line;
      }
      $normalized[] = $line;
    }

    return implode("\r\n", $normalized);
  }

  private function extractEmail(string $from): string
  {
    if (preg_match('/<([^>]+)>/', $from, $matches) === 1) {
      return trim($matches[1]);
    }

    return trim($from);
  }
}
