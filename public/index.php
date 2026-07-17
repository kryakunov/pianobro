<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use PianoTrainer\AuthService;
use PianoTrainer\Database;
use PianoTrainer\LessonRepository;
use PianoTrainer\MidiSearch;
use PianoTrainer\OAuthConfig;
use PianoTrainer\OAuthService;
use PianoTrainer\RoadmapService;
use PianoTrainer\Router;
use PianoTrainer\StatsRepository;

$lessonsDir = dirname(__DIR__) . '/data/lessons';
$repository = new LessonRepository($lessonsDir);
$db = Database::connection();
$auth = new AuthService($db);
$auth->startSession();
$stats = new StatsRepository($db);
$oauthConfig = OAuthConfig::fromEnv();
$oauth = new OAuthService($oauthConfig, $auth);
$roadmap = new RoadmapService($db);
$router = new Router($repository, new MidiSearch(), $auth, $stats, $oauth, $roadmap);

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$router->dispatch($uri, $method);
