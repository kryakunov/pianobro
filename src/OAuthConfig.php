<?php

declare(strict_types=1);

namespace PianoTrainer;

final class OAuthConfig
{
  public function __construct(private readonly string $appUrl) {}

  public static function fromEnv(): self
  {
    Env::load(dirname(__DIR__) . '/.env');

    $appUrl = rtrim(Env::get('APP_URL', self::detectAppUrl()), '/');

    return new self($appUrl);
  }

  private static function detectAppUrl(): string
  {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';

    return $scheme . '://' . $host;
  }

  public function appUrl(): string
  {
    return $this->appUrl;
  }

  public function redirectUri(string $provider): string
  {
    return $this->appUrl . '/api/auth/oauth/' . $provider . '/callback';
  }

  /** @return list<array{id:string,label:string}> */
  public function availableProviders(): array
  {
    $providers = [];

    if ($this->googleClientId() !== '' && $this->googleClientSecret() !== '') {
      $providers[] = ['id' => 'google', 'label' => 'Google'];
    }
    if ($this->yandexClientId() !== '' && $this->yandexClientSecret() !== '') {
      $providers[] = ['id' => 'yandex', 'label' => 'Яндекс'];
    }
    if ($this->vkClientId() !== '') {
      $providers[] = ['id' => 'vk', 'label' => 'ВКонтакте'];
    }

    return $providers;
  }

  public function isConfigured(string $provider): bool
  {
    return match ($provider) {
      'google' => $this->googleClientId() !== '' && $this->googleClientSecret() !== '',
      'yandex' => $this->yandexClientId() !== '' && $this->yandexClientSecret() !== '',
      'vk' => $this->vkClientId() !== '',
      default => false,
    };
  }

  public function googleClientId(): string
  {
    return Env::get('GOOGLE_CLIENT_ID');
  }

  public function googleClientSecret(): string
  {
    return Env::get('GOOGLE_CLIENT_SECRET');
  }

  public function yandexClientId(): string
  {
    return Env::get('YANDEX_CLIENT_ID');
  }

  public function yandexClientSecret(): string
  {
    return Env::get('YANDEX_CLIENT_SECRET');
  }

  public function vkClientId(): string
  {
    return Env::get('VK_CLIENT_ID');
  }

  public function vkClientSecret(): string
  {
    return Env::get('VK_CLIENT_SECRET');
  }
}
