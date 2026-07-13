<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use PianoTrainer\LessonRepository;
use PianoTrainer\MidiSearch;
use PianoTrainer\Router;

$lessonsDir = dirname(__DIR__) . '/data/lessons';
$repository = new LessonRepository($lessonsDir);
$router = new Router($repository, new MidiSearch());

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$router->dispatch($uri, $method);
