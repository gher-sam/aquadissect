<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$deployDirectory = dirname(__DIR__).'/storage/app/deploy';
$secretPath = $deployDirectory.'/github-webhook.secret';
$triggerPath = $deployDirectory.'/github-webhook-trigger.json';

if (! is_file($secretPath)) {
    http_response_code(503);
    echo json_encode(['accepted' => false, 'reason' => 'deployment-not-configured']);
    exit;
}

$secret = trim((string) file_get_contents($secretPath));
$payload = (string) file_get_contents('php://input');
$signature = (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
$expected = 'sha256='.hash_hmac('sha256', $payload, $secret);

if ($secret === '' || ! hash_equals($expected, $signature)) {
    http_response_code(403);
    echo json_encode(['accepted' => false, 'reason' => 'invalid-signature']);
    exit;
}

$event = (string) ($_SERVER['HTTP_X_GITHUB_EVENT'] ?? '');
if ($event === 'ping') {
    echo json_encode(['accepted' => true, 'event' => 'ping']);
    exit;
}

$data = json_decode($payload, true);
if ($event !== 'push' || ! is_array($data) || ($data['ref'] ?? '') !== 'refs/heads/main') {
    http_response_code(202);
    echo json_encode(['accepted' => false, 'reason' => 'ignored-event']);
    exit;
}

if (! is_dir($deployDirectory) && ! mkdir($deployDirectory, 0750, true) && ! is_dir($deployDirectory)) {
    http_response_code(500);
    echo json_encode(['accepted' => false, 'reason' => 'trigger-directory-unavailable']);
    exit;
}

$trigger = json_encode(
    [
        'after' => (string) ($data['after'] ?? ''),
        'ref' => (string) $data['ref'],
        'received_at' => gmdate(DATE_ATOM),
    ],
    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
);
$temporaryPath = $triggerPath.'.tmp';

if (file_put_contents($temporaryPath, $trigger."\n", LOCK_EX) === false || ! rename($temporaryPath, $triggerPath)) {
    http_response_code(500);
    echo json_encode(['accepted' => false, 'reason' => 'trigger-write-failed']);
    exit;
}

http_response_code(202);
echo json_encode(['accepted' => true, 'event' => 'push']);
