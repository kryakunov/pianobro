<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $section
 */
function renderBlogSection(array $section): string
{
  $type = (string) ($section['type'] ?? 'p');

  return match ($type) {
    'h2' => '<h2 class="blog-article__h2">' . htmlspecialchars((string) ($section['text'] ?? ''), ENT_QUOTES, 'UTF-8') . '</h2>',
    'h3' => '<h3 class="blog-article__h3">' . htmlspecialchars((string) ($section['text'] ?? ''), ENT_QUOTES, 'UTF-8') . '</h3>',
    'p' => '<p class="blog-article__p">' . htmlspecialchars((string) ($section['text'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>',
    'ul' => renderBlogList($section['items'] ?? [], 'ul'),
    'ol' => renderBlogList($section['items'] ?? [], 'ol'),
    'cta' => renderBlogCta($section),
    default => '',
  };
}

/**
 * @param list<mixed> $items
 */
function renderBlogList(array $items, string $tag): string
{
  if ($items === []) {
    return '';
  }

  $lis = '';
  foreach ($items as $item) {
    $lis .= '<li>' . htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8') . '</li>';
  }

  $class = $tag === 'ol' ? 'blog-article__ol' : 'blog-article__ul';

  return "<{$tag} class=\"{$class}\">{$lis}</{$tag}>";
}

/** @param array<string, mixed> $section */
function renderBlogCta(array $section): string
{
  $href = (string) ($section['href'] ?? '/');
  $label = (string) ($section['label'] ?? 'Открыть');
  $variant = (string) ($section['variant'] ?? 'primary');
  $class = $variant === 'secondary' ? 'btn btn--secondary' : 'btn btn--primary';

  return '<p class="blog-article__cta"><a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="' . $class . '">'
    . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></p>';
}

function formatBlogDate(string $isoDate): string
{
  $timestamp = strtotime($isoDate);
  if ($timestamp === false) {
    return $isoDate;
  }

  $months = [
    1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
    5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
    9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
  ];

  $day = (int) date('j', $timestamp);
  $month = $months[(int) date('n', $timestamp)] ?? date('m', $timestamp);
  $year = date('Y', $timestamp);

  return "{$day} {$month} {$year}";
}
