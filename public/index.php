<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use PianoTrainer\AdminService;
use PianoTrainer\AuthService;
use PianoTrainer\MailService;
use PianoTrainer\TeacherService;
use PianoTrainer\Database;
use PianoTrainer\Env;
use PianoTrainer\LessonRepository;
use PianoTrainer\MidiSearch;
use PianoTrainer\OAuthConfig;
use PianoTrainer\OAuthService;
use PianoTrainer\RoadmapService;
use PianoTrainer\RoleService;
use PianoTrainer\Router;
use PianoTrainer\StatsRepository;

Env::load(dirname(__DIR__) . '/.env');

$lessonsDir = dirname(__DIR__) . '/data/lessons';
$repository = new LessonRepository($lessonsDir);
$db = Database::connection();
$auth = new AuthService($db);
$auth->startSession();
$stats = new StatsRepository($db);
$oauthConfig = OAuthConfig::fromEnv();
$oauth = new OAuthService($oauthConfig, $auth);
$roadmap = new RoadmapService($db);
$roles = new RoleService($db);
$admin = new AdminService($db, $roadmap, $roles);
$mail = new MailService();
$teacher = new TeacherService($db, $roadmap, $stats, $mail, $roles);
$auth->setTeacherService($teacher);
$auth->setRoleService($roles);
$router = new Router($repository, new MidiSearch(), $auth, $stats, $oauth, $roadmap, $admin, $teacher, $roles);

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$router->dispatch($uri, $method);
