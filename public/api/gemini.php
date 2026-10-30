<?php
// Sand der Zeit · KI-Szenen für das Thumbnail-Werkzeug
// Leitet Anfragen an die Gemini API weiter, damit der API Key nie im Browser landet.
// Zugangsdaten kommen aus config.php (wird beim Deploy aus den GitHub Secrets erzeugt).

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

function fail($code, $msg) {
    http_response_code($code);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'Nur POST erlaubt.');

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) fail(500, 'config.php fehlt auf dem Server. GEMINI_API_KEY und TOOL_PASSWORD als GitHub Secrets anlegen und neu deployen.');
$config = require $configFile;

$apiKey   = $config['gemini_api_key'] ?? '';
$password = $config['tool_password'] ?? '';
$model    = $config['model'] ?? 'gemini-3-pro-image-preview';
$daily    = (int)($config['daily_limit'] ?? 80);
if ($apiKey === '' || $password === '') fail(500, 'API Key oder Passwort ist in config.php leer.');

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) fail(400, 'Ungültige Anfrage.');

// Passwort prüfen (kurze Pause bremst Rateversuche)
if (!hash_equals($password, (string)($in['password'] ?? ''))) { usleep(800000); fail(401, 'Passwort falsch.'); }

$prompt = trim((string)($in['prompt'] ?? ''));
if ($prompt === '') fail(400, 'Bitte einen Prompt eingeben.');
if (mb_strlen($prompt) > 4000) fail(400, 'Prompt ist zu lang.');

// Tageslimit, damit ein offener Tab nicht das Budget verbrennt
$counterFile = __DIR__ . '/.usage-' . gmdate('Y-m-d') . '.txt';
$used = is_file($counterFile) ? (int)file_get_contents($counterFile) : 0;
if ($used >= $daily) fail(429, "Tageslimit von $daily Bildern erreicht. Morgen geht es weiter.");
foreach (glob(__DIR__ . '/.usage-*.txt') ?: [] as $old) { if ($old !== $counterFile) @unlink($old); }

// Teile zusammenbauen: zuerst Referenzbilder, dann der Text
$parts = [];
$images = is_array($in['images'] ?? null) ? array_slice($in['images'], 0, 4) : [];
foreach ($images as $img) {
    $mime = (string)($img['mime'] ?? '');
    $data = (string)($img['data'] ?? '');
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || $data === '') continue;
    if (strlen($data) > 8 * 1024 * 1024) fail(413, 'Ein Referenzbild ist zu groß.');
    $parts[] = ['inline_data' => ['mime_type' => $mime, 'data' => $data]];
}
$parts[] = ['text' => $prompt];

$size = in_array($in['size'] ?? '', ['1K', '2K', '4K'], true) ? $in['size'] : '2K';
$body = [
    'contents' => [['role' => 'user', 'parts' => $parts]],
    'generationConfig' => [
        'responseModalities' => ['TEXT', 'IMAGE'],
        'imageConfig' => ['aspectRatio' => '16:9', 'imageSize' => $size],
    ],
];

@set_time_limit(180);
$ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $apiKey],
    CURLOPT_POSTFIELDS => json_encode($body),
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 170,
]);
$raw = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($raw === false) fail(502, 'Gemini nicht erreichbar: ' . $err);
$res = json_decode($raw, true);
if ($status >= 400) fail($status === 429 ? 429 : 502, 'Gemini meldet: ' . ($res['error']['message'] ?? "HTTP $status"));

$outImages = [];
$text = '';
foreach ($res['candidates'][0]['content']['parts'] ?? [] as $p) {
    $d = $p['inlineData'] ?? $p['inline_data'] ?? null;
    if ($d && !empty($d['data'])) $outImages[] = ['mime' => $d['mimeType'] ?? $d['mime_type'] ?? 'image/png', 'data' => $d['data']];
    elseif (!empty($p['text']) && empty($p['thought'])) $text .= $p['text'];
}

if (!$outImages) {
    $reason = $res['candidates'][0]['finishReason'] ?? ($res['promptFeedback']['blockReason'] ?? '');
    fail(422, 'Kein Bild erhalten' . ($reason ? " ($reason)" : '') . ($text ? ': ' . $text : '. Prompt etwas umformulieren.'));
}

file_put_contents($counterFile, (string)($used + 1), LOCK_EX);
echo json_encode(['images' => $outImages, 'text' => trim($text), 'left' => max(0, $daily - $used - 1)], JSON_UNESCAPED_UNICODE);
