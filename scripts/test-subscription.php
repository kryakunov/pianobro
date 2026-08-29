<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use PianoTrainer\Database;
use PianoTrainer\PaymentService;
use PianoTrainer\PricingConfig;
use PianoTrainer\SubscriptionService;

$db = Database::connection();
$subscriptions = new SubscriptionService($db);
$payments = new PaymentService($db, $subscriptions);

$email = 'sub-test-' . bin2hex(random_bytes(4)) . '@example.com';
$db->prepare('INSERT INTO users (email, password_hash, name) VALUES (:email, :hash, :name)')
  ->execute(['email' => $email, 'hash' => password_hash('test123', PASSWORD_DEFAULT), 'name' => 'Test User']);
$userId = (int) $db->lastInsertId();

assert($subscriptions->isPremium($userId) === false, 'new user should be free');

$check = $subscriptions->canStartTraining($userId, 'training');
assert($check['allowed'] === true, 'free user should start first training');

$subscriptions->recordTrainingSession($userId, 'training');
$used = $subscriptions->getDailyUsage($userId);
assert($used === 1, 'usage should be 1');

$notesCheck = $subscriptions->canPlayNotes($userId, 1);
assert($notesCheck['allowed'] === true, 'free user should play first note');

$subscriptions->recordNoteAttempts($userId, 80);
$notesBlocked = $subscriptions->canPlayNotes($userId, 1);
assert($notesBlocked['allowed'] === false, 'free user should hit daily notes limit');
assert($notesBlocked['reason'] === 'daily_notes_limit', 'notes limit reason');

$payments->completeMockPayment(
  (int) $payments->createCheckout($userId, 'monthly', 'http://localhost/payment')['paymentId'],
  $userId,
);

assert($subscriptions->isPremium($userId) === true, 'user should be premium after mock payment');

$state = $subscriptions->getForUser($userId);
assert($state['plan'] === 'monthly', 'plan should be monthly');
assert($state['status'] === 'active', 'status should be active');

assert(count(PricingConfig::plans()) === 3, 'should have 3 plans');
assert(PricingConfig::plan('quarterly')['priceRub'] === 249, 'quarterly price');

$db->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);

echo "Subscription tests passed\n";
