<?php

declare(strict_types=1);

namespace PianoTrainer;

final class BlogPost
{
  /**
   * @param list<array<string, mixed>> $sections
   * @param list<string> $relatedSlugs
   */
  public function __construct(
    public readonly string $slug,
    public readonly string $title,
    public readonly string $description,
    public readonly string $keywords,
    public readonly string $publishedAt,
    public readonly string $updatedAt,
    public readonly string $lead,
    public readonly array $sections,
    public readonly array $relatedSlugs = [],
  ) {}

  public function path(): string
  {
    return '/blog/' . $this->slug;
  }

  /** @return array<string, mixed> */
  public function toSummary(): array
  {
    return [
      'slug' => $this->slug,
      'title' => $this->title,
      'description' => $this->description,
      'publishedAt' => $this->publishedAt,
      'updatedAt' => $this->updatedAt,
      'lead' => $this->lead,
      'path' => $this->path(),
    ];
  }
}
