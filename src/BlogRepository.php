<?php

declare(strict_types=1);

namespace PianoTrainer;

final class BlogRepository
{
  public function __construct(
    private readonly string $blogDir,
  ) {}

  /** @return list<BlogPost> */
  public function all(): array
  {
    $posts = [];
    $files = glob($this->blogDir . '/*.json') ?: [];

    foreach ($files as $file) {
      $post = $this->loadFromFile($file);
      if ($post !== null) {
        $posts[] = $post;
      }
    }

    usort($posts, static function (BlogPost $a, BlogPost $b): int {
      return strcmp($b->publishedAt, $a->publishedAt);
    });

    return $posts;
  }

  public function find(string $slug): ?BlogPost
  {
    if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
      return null;
    }

    $path = $this->blogDir . '/' . $slug . '.json';
    if (!is_file($path)) {
      return null;
    }

    return $this->loadFromFile($path);
  }

  private function loadFromFile(string $path): ?BlogPost
  {
    $raw = file_get_contents($path);
    if ($raw === false) {
      return null;
    }

    try {
      /** @var array<string, mixed> $data */
      $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException) {
      return null;
    }

    $slug = (string) ($data['slug'] ?? '');
    $title = trim((string) ($data['title'] ?? ''));
    if ($slug === '' || $title === '') {
      return null;
    }

    $sections = $data['sections'] ?? [];
    if (!is_array($sections)) {
      $sections = [];
    }

    $related = $data['relatedSlugs'] ?? [];
    if (!is_array($related)) {
      $related = [];
    }

    return new BlogPost(
      slug: $slug,
      title: $title,
      description: trim((string) ($data['description'] ?? $title)),
      keywords: trim((string) ($data['keywords'] ?? '')),
      publishedAt: (string) ($data['publishedAt'] ?? date('Y-m-d')),
      updatedAt: (string) ($data['updatedAt'] ?? ($data['publishedAt'] ?? date('Y-m-d'))),
      lead: trim((string) ($data['lead'] ?? '')),
      sections: $sections,
      relatedSlugs: array_values(array_filter(array_map('strval', $related))),
    );
  }
}
