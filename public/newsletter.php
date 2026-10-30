<?php
// Newsletter-Anmeldung für sandderzeit.de
// Nimmt die E-Mail aus dem eigenen Formular entgegen und leitet sie serverseitig an das
// Mailjet-Anmeldeformular weiter. Double-Opt-in und Willkommensmail laufen weiter in Mailjet.
// Wenn das Formular in Mailjet neu gespeichert wird, kann sich die Adresse unten ändern:
// Sie steht im Quelltext von https://155tu.mjt.lu/wgt/155tu/09ln/form (Suche nach "subscribe?c=").

const MAILJET_SUBSCRIBE_URL = 'https://155tu.mjt.lu/wgt/155tu/09ln/subscribe?c=64dd593f';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['ok' => false, 'error' => 'method']);

$raw = file_get_contents('php://input');
$in = json_decode($raw, true);
if (!is_array($in)) $in = $_POST;

// Honeypot: Bots füllen das versteckte Feld aus
if (!empty($in['website'])) out(200, ['ok' => true]);

$email = trim((string)($in['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) out(400, ['ok' => false, 'error' => 'email']);
if (empty($in['consent'])) out(400, ['ok' => false, 'error' => 'consent']);

$payload = json_encode(['Email' => $email, 'Fields' => []]);
$ch = curl_init(MAILJET_SUBSCRIBE_URL);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Origin: https://155tu.mjt.lu',
        'Referer: https://155tu.mjt.lu/wgt/155tu/09ln/form',
    ],
]);
$resp = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false || $status < 200 || $status >= 300) out(502, ['ok' => false, 'error' => 'mailjet', 'status' => $status]);
out(200, ['ok' => true]);
