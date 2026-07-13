<?php

declare(strict_types=1);

namespace PianoTrainer;

final class MidiSearch
{
  private const BASE_URL = 'https://freemidi.org';
  private const USER_AGENT = 'Mozilla/5.0 (compatible; PianoBro/1.0)';

  /** @return list<array{id: int, title: string, slug: string}> */
  public function search(string $query, int $limit = 12): array
  {
    $query = trim($query);
    if ($query === '') {
      return [];
    }

    $url = self::BASE_URL . '/search?q=' . rawurlencode($query);
    $html = $this->fetch($url);
    if ($html === null) {
      return [];
    }

    if (!preg_match_all(
      '#<h5 class=card-title><a href=download3-(\d+)-([^ >]+)(?:\s+title="([^"]*)")?[^>]*>([^<]*)</a></h5>#u',
      $html,
      $matches,
      PREG_SET_ORDER,
    )) {
      return [];
    }

    $results = [];
    foreach ($matches as $match) {
      if (count($results) >= $limit) {
        break;
      }

      $title = trim(html_entity_decode($match[3] !== '' ? $match[3] : $match[4], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
      if ($title === '') {
        continue;
      }

      $results[] = [
        'id' => (int) $match[1],
        'title' => $title,
        'slug' => (string) $match[2],
      ];
    }

    return $results;
  }

  public function fetchMidi(int $id): ?string
  {
    if ($id <= 0) {
      return null;
    }

    $getterUrl = self::BASE_URL . '/getter-' . $id;
    $referer = self::BASE_URL . '/download3-' . $id;

    $ch = curl_init($getterUrl);
    if ($ch === false) {
      return null;
    }

    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => false,
      CURLOPT_TIMEOUT => 20,
      CURLOPT_USERAGENT => self::USER_AGENT,
      CURLOPT_HTTPHEADER => [
        'Referer: ' . $referer,
        'Accept: application/octet-stream,*/*',
      ],
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (!is_string($body) || $body === '' || $status < 200 || $status >= 400) {
      return null;
    }

    if (!str_starts_with($body, 'MThd')) {
      return null;
    }

    return $body;
  }

  private function fetch(string $url): ?string
  {
    $ch = curl_init($url);
    if ($ch === false) {
      return null;
    }

    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_TIMEOUT => 15,
      CURLOPT_USERAGENT => self::USER_AGENT,
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (!is_string($body) || $body === '' || $status < 200 || $status >= 400) {
      return null;
    }

    return $body;
  }
}
