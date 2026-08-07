<?php

declare(strict_types=1);

/** @var array<string, mixed> $page */
/** @var int $assetVersion */

$canonical = \PianoTrainer\AppUrl::canonical((string) ($page['path'] ?? '/'));
$title = (string) ($page['title'] ?? 'Piano Bro');
$description = (string) ($page['description'] ?? '');
$keywords = (string) ($page['keywords'] ?? '');
$robots = (string) ($page['robots'] ?? 'index, follow');
$ogTitle = (string) ($page['ogTitle'] ?? $title);
$ogDescription = (string) ($page['ogDescription'] ?? $description);
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
<meta name="description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
<?php if ($keywords !== ''): ?>
<meta name="keywords" content="<?= htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<meta name="author" content="Piano Bro">
<meta name="robots" content="<?= htmlspecialchars($robots, ENT_QUOTES, 'UTF-8') ?>">
<meta name="theme-color" content="#6c8cff">
<link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>">
<link rel="icon" href="/assets/favicon.svg?v=<?= (int) $assetVersion ?>" type="image/svg+xml">
<link rel="icon" href="/favicon.ico?v=<?= (int) $assetVersion ?>" sizes="32x32">
<link rel="apple-touch-icon" href="/assets/favicon.svg?v=<?= (int) $assetVersion ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="ru_RU">
<meta property="og:title" content="<?= htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:description" content="<?= htmlspecialchars($ogDescription, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:site_name" content="Piano Bro">
<meta property="og:url" content="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="<?= htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($ogDescription, ENT_QUOTES, 'UTF-8') ?>">
<?php if (!empty($page['jsonLd']) && is_array($page['jsonLd'])): ?>
<script type="application/ld+json">
<?= json_encode($page['jsonLd'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>
</script>
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css?v=<?= (int) $assetVersion ?>">
<?php require __DIR__ . '/yandex-metrika.php'; ?>
