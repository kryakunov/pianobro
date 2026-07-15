<?php

declare(strict_types=1);

namespace PianoTrainer;

final class OAuthService
{
  public function __construct(
    private readonly OAuthConfig $config,
    private readonly AuthService $auth,
  ) {}

  /** @return list<array{id:string,label:string}> */
  public function availableProviders(): array
  {
    return $this->config->availableProviders();
  }

  public function start(string $provider): void
  {
    $this->assertProvider($provider);

    $state = bin2hex(random_bytes(16));
    $verifier = $this->createCodeVerifier();
    $_SESSION['oauth_state'] = $state;
    $_SESSION['oauth_provider'] = $provider;
    $_SESSION['oauth_code_verifier'] = $verifier;

    $url = match ($provider) {
      'google' => $this->googleAuthorizeUrl($state, $verifier),
      'yandex' => $this->yandexAuthorizeUrl($state, $verifier),
      'vk' => $this->vkAuthorizeUrl($state, $verifier),
      default => throw new \InvalidArgumentException('Неизвестный провайдер'),
    };

    header('Location: ' . $url);
    exit;
  }

  public function handleCallback(string $provider): void
  {
    $this->assertProvider($provider);

    $error = (string) ($_GET['error'] ?? '');
    if ($error !== '') {
      $description = (string) ($_GET['error_description'] ?? $error);
      $this->redirectWithError($description);
    }

    $state = (string) ($_GET['state'] ?? '');
    $code = (string) ($_GET['code'] ?? '');
    $expectedState = (string) ($_SESSION['oauth_state'] ?? '');
    $expectedProvider = (string) ($_SESSION['oauth_provider'] ?? '');
    $verifier = (string) ($_SESSION['oauth_code_verifier'] ?? '');

    unset($_SESSION['oauth_state'], $_SESSION['oauth_provider'], $_SESSION['oauth_code_verifier']);

    if ($code === '' || $state === '' || !hash_equals($expectedState, $state) || $expectedProvider !== $provider) {
      $this->redirectWithError('Не удалось подтвердить вход через соцсеть');
    }

    try {
      $profile = match ($provider) {
        'google' => $this->fetchGoogleProfile($code, $verifier),
        'yandex' => $this->fetchYandexProfile($code, $verifier),
        'vk' => $this->fetchVkProfile($code, $verifier),
        default => throw new \InvalidArgumentException('Неизвестный провайдер'),
      };

      $this->auth->loginWithOAuth(
        $provider,
        $profile['providerUserId'],
        $profile['email'],
        $profile['name'],
      );
      $this->redirectWithSuccess();
    } catch (\Throwable $e) {
      $this->redirectWithError('Не удалось войти через соцсеть');
    }
  }

  private function assertProvider(string $provider): void
  {
    if (!$this->config->isConfigured($provider)) {
      throw new \InvalidArgumentException('Вход через эту соцсеть не настроен');
    }
  }

  private function googleAuthorizeUrl(string $state, string $verifier): string
  {
    $challenge = $this->codeChallenge($verifier);

    return $this->buildUrl('https://accounts.google.com/o/oauth2/v2/auth', [
      'client_id' => $this->config->googleClientId(),
      'redirect_uri' => $this->config->redirectUri('google'),
      'response_type' => 'code',
      'scope' => 'openid email profile',
      'state' => $state,
      'code_challenge' => $challenge,
      'code_challenge_method' => 'S256',
      'access_type' => 'online',
      'prompt' => 'select_account',
    ]);
  }

  private function yandexAuthorizeUrl(string $state, string $verifier): string
  {
    $challenge = $this->codeChallenge($verifier);

    return $this->buildUrl('https://oauth.yandex.ru/authorize', [
      'response_type' => 'code',
      'client_id' => $this->config->yandexClientId(),
      'redirect_uri' => $this->config->redirectUri('yandex'),
      'state' => $state,
      'code_challenge' => $challenge,
      'code_challenge_method' => 'S256',
    ]);
  }

  private function vkAuthorizeUrl(string $state, string $verifier): string
  {
    $challenge = $this->codeChallenge($verifier);

    return $this->buildUrl('https://id.vk.com/authorize', [
      'response_type' => 'code',
      'client_id' => $this->config->vkClientId(),
      'redirect_uri' => $this->config->redirectUri('vk'),
      'state' => $state,
      'scope' => 'email',
      'code_challenge' => $challenge,
      'code_challenge_method' => 'S256',
    ]);
  }

  /** @return array{providerUserId:string,email:?string,name:string} */
  private function fetchGoogleProfile(string $code, string $verifier): array
  {
    $token = $this->requestToken('https://oauth2.googleapis.com/token', [
      'grant_type' => 'authorization_code',
      'code' => $code,
      'redirect_uri' => $this->config->redirectUri('google'),
      'client_id' => $this->config->googleClientId(),
      'client_secret' => $this->config->googleClientSecret(),
      'code_verifier' => $verifier,
    ]);

    $user = $this->requestJson(
      'https://www.googleapis.com/oauth2/v2/userinfo',
      headers: ['Authorization: Bearer ' . $token['access_token']],
    );

    $email = isset($user['email']) ? strtolower((string) $user['email']) : null;
    $name = trim((string) ($user['name'] ?? $user['given_name'] ?? 'Пользователь Google'));

    return [
      'providerUserId' => (string) ($user['id'] ?? ''),
      'email' => $email,
      'name' => $name !== '' ? $name : 'Пользователь Google',
    ];
  }

  /** @return array{providerUserId:string,email:?string,name:string} */
  private function fetchYandexProfile(string $code, string $verifier): array
  {
    $token = $this->requestToken('https://oauth.yandex.ru/token', [
      'grant_type' => 'authorization_code',
      'code' => $code,
      'redirect_uri' => $this->config->redirectUri('yandex'),
      'client_id' => $this->config->yandexClientId(),
      'client_secret' => $this->config->yandexClientSecret(),
      'code_verifier' => $verifier,
    ]);

    $user = $this->requestJson(
      'https://login.yandex.ru/info?format=json',
      headers: ['Authorization: OAuth ' . $token['access_token']],
    );

    $email = isset($user['default_email']) ? strtolower((string) $user['default_email']) : null;
    if ($email === null && isset($user['emails'][0])) {
      $email = strtolower((string) $user['emails'][0]);
    }

    $name = trim((string) ($user['real_name'] ?? $user['display_name'] ?? $user['login'] ?? 'Пользователь Яндекса'));

    return [
      'providerUserId' => (string) ($user['id'] ?? ''),
      'email' => $email,
      'name' => $name !== '' ? $name : 'Пользователь Яндекса',
    ];
  }

  /** @return array{providerUserId:string,email:?string,name:string} */
  private function fetchVkProfile(string $code, string $verifier): array
  {
    $payload = [
      'grant_type' => 'authorization_code',
      'code' => $code,
      'redirect_uri' => $this->config->redirectUri('vk'),
      'client_id' => $this->config->vkClientId(),
      'code_verifier' => $verifier,
      'device_id' => (string) ($_GET['device_id'] ?? ''),
    ];

    $secret = $this->config->vkClientSecret();
    if ($secret !== '') {
      $payload['client_secret'] = $secret;
    }

    $token = $this->requestToken('https://id.vk.com/oauth2/auth', $payload);
    $user = $this->requestJson(
      'https://id.vk.com/oauth2/user_info',
      method: 'POST',
      body: [
        'access_token' => $token['access_token'],
        'client_id' => $this->config->vkClientId(),
      ],
    );

    $profile = is_array($user['user'] ?? null) ? $user['user'] : $user;
    $email = isset($profile['email']) ? strtolower((string) $profile['email']) : null;
    $firstName = trim((string) ($profile['first_name'] ?? ''));
    $lastName = trim((string) ($profile['last_name'] ?? ''));
    $name = trim($firstName . ' ' . $lastName);

    return [
      'providerUserId' => (string) ($profile['user_id'] ?? $profile['id'] ?? ''),
      'email' => $email,
      'name' => $name !== '' ? $name : 'Пользователь ВКонтакте',
    ];
  }

  /** @param array<string, string> $fields */
  private function requestToken(string $url, array $fields): array
  {
    $response = $this->requestJson($url, method: 'POST', body: $fields);
    if (!isset($response['access_token'])) {
      throw new \RuntimeException('OAuth token missing');
    }

    return $response;
  }

  /**
   * @param array<string, string>|null $body
   * @param list<string> $headers
   * @return array<string, mixed>
   */
  private function requestJson(string $url, string $method = 'GET', ?array $body = null, array $headers = []): array
  {
    $ch = curl_init($url);
    if ($ch === false) {
      throw new \RuntimeException('curl init failed');
    }

    $requestHeaders = $headers;
    if ($method === 'POST' && $body !== null) {
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
      $requestHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
    }

    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => $requestHeaders,
      CURLOPT_TIMEOUT => 15,
    ]);

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($raw === false || $status >= 400) {
      throw new \RuntimeException('OAuth request failed');
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
      throw new \RuntimeException('OAuth response invalid');
    }

    return $data;
  }

  /** @param array<string, string> $params */
  private function buildUrl(string $base, array $params): string
  {
    return $base . '?' . http_build_query($params);
  }

  private function createCodeVerifier(): string
  {
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
  }

  private function codeChallenge(string $verifier): string
  {
    return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
  }

  private function redirectWithSuccess(): void
  {
    header('Location: ' . $this->config->appUrl() . '/?oauth=success');
    exit;
  }

  private function redirectWithError(string $message): void
  {
    $query = http_build_query(['oauth_error' => $message]);
    header('Location: ' . $this->config->appUrl() . '/?' . $query);
    exit;
  }
}
