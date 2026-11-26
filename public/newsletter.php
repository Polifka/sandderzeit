<?php
declare(strict_types=1);

/**
 * Newsletter, Warteliste & Individuelle Kartendeutung für sandderzeit.de
 *
 * Ablauf (100 % kostenlos mit Mailjet Free · Send API v3.1 + Contacts API v3):
 * 1. POST /newsletter.php
 *    -> Validiert E-Mail & Einwilligung, erzeugt einen signierten Double-Opt-In-Link (48h gültig)
 *       und versendet eine gebrandete Bestätigungsmail via Mailjet Send API v3.1.
 * 2. GET /newsletter.php?confirm=<token>
 *    -> Prüft Signatur, trägt den Kontakt in Mailjet-Liste 10648702 ein,
 *       versendet sofort die individuelle Deutungs- & Übungsmail passend zur gezogenen Karte
 *       und zeigt eine gebrandete Bestätigungsseite.
 */

$configFile = __DIR__ . '/mailjet-config.php';
$cfg = is_file($configFile) ? (require $configFile) : [];

$apiKey    = (string)($cfg['api_key']    ?? getenv('MAILJET_API_KEY')    ?: '');
$apiSecret = (string)($cfg['api_secret'] ?? getenv('MAILJET_API_SECRET') ?: '');
$fromEmail = (string)($cfg['from_email'] ?? 'info@sandderzeit.de');
$fromName  = (string)($cfg['from_name']  ?? 'Filip & Jan · Sand der Zeit');
$listId    = (int)($cfg['list_id']       ?? 10648702);
$siteUrl   = rtrim((string)($cfg['site_url'] ?? 'https://sandderzeit.de'), '/');

// ─── Die 7 Archetypen (Texte + konkrete Wochenübung) ───
$CARDS = [
    'der-magier' => [
        'name'     => 'Der Magier',
        'eyebrow'  => 'I &middot; Der Magier',
        'headline' => 'Du hast alle Werkzeuge.<br>Jetzt zählt der Schnitt.',
        'p1'       => 'Auf dem Tisch des Magiers liegen alle vier Elemente – Kelch, Schwert, Stab und Münze. Im echten Leben zeigt diese Karte selten einen Mangel an Talent oder Wissen. Sie zeigt fast immer das Gegenteil: Du weißt bereits genug, aber deine Kraft zerfasert, weil du drei Türen gleichzeitig offen hältst.',
        'p2'       => 'In der Alchemie ist Fokus das Feuer unter dem Tiegel. Ohne Bündelung bleibt selbst das reinste Gold roh.',
        'exercise' => 'Schreib heute Abend drei Dinge auf, die du seit Wochen vor dir herschiebst. Streich zwei davon bewusst für die nächsten 7 Tage – und setz morgen früh 30 Minuten lang den ersten konkreten Schritt für das <strong>eine</strong>, das übrig bleibt.',
    ],
    'acht-der-muenzen' => [
        'name'     => 'Acht der Münzen',
        'eyebrow'  => 'VIII &middot; Acht der Münzen',
        'headline' => 'Die Karte der Steinschleifer.',
        'p1'       => 'Kein Zufall, dass du genau diese Karte gezogen hast. Die Acht der Münzen zeigt den Handwerker an seiner Werkbank – abseits vom Lärm der Stadt, Schlag für Schlag, völlig versunken in seine Arbeit. Sie erinnert dich daran, dass echte Verwandlung nicht im großen Geistesblitz entsteht, sondern in der ruhigen, wiederholten Praxis.',
        'p2'       => 'Deine Rohkante liegt gerade in der Ungeduld: dem Wunsch, schneller fertig zu sein, statt den Prozess selbst zu meistern.',
        'exercise' => 'Wähle eine kleine tägliche Praxis (10 Minuten Stille, Journaling, Bewegung oder dein Handwerk) und zieh sie die nächsten 7 Tage lang zur exakt selben Uhrzeit durch – ohne sie zu bewerten und ohne sie zu posten.',
    ],
    'der-turm' => [
        'name'     => 'Der Turm',
        'eyebrow'  => 'XVI &middot; Der Turm',
        'headline' => 'Was fällt, war nicht dein Fundament.',
        'p1'       => 'Der Turm erschreckt fast jeden – dabei ist er eine der ehrlichsten Karten im ganzen Deck. Ein Blitz trifft nur Gemäuer, das zu eng und zu starr geworden ist. In der Alchemie heißt diese Phase <em>Nigredo</em>: Das Alte muss zerfallen, damit das Echte darunter wieder atmen kann.',
        'p2'       => 'Je länger du versuchst, eine überholte Rolle, Beziehung oder Gewohnheit künstlich zusammenzuhalten, desto mehr Kraft kostet es dich.',
        'exercise' => 'Beende diesen Satz schriftlich – ohne dich zu zensieren: <em>„Wenn ich ganz ehrlich bin, halte ich gerade noch an ______ fest, obwohl es längst vorbei ist.“</em> Schau dir an, welcher erste kleine Schritt Raum schafft.',
    ],
    'as-der-schwerter' => [
        'name'     => 'As der Schwerter',
        'eyebrow'  => 'As der Schwerter',
        'headline' => 'Klarheit statt Weichspüler.',
        'p1'       => 'Das As der Schwerter ist ein sauberer, kalter Schnitt durch den Nebel. Es zeigt: Tief im Inneren kennst du die Antwort auf deine aktuelle Frage längst. Was dich festhält, ist nicht Unwissenheit – sondern die Angst vor der Konsequenz, wenn du es laut aussprichst.',
        'p2'       => 'Wahrheit fühlt sich im ersten Moment scharf an. Aber erst nach dem Schnitt beginnt die Heilung.',
        'exercise' => 'Formuliere in einem einzigen, klaren Satz ohne „eigentlich“ und ohne „vielleicht“ die Entscheidung, die du seit Wochen aufschiebst. Sprich ihn laut aus – und leg fest, bis wann du danach handelst.',
    ],
    'die-liebenden' => [
        'name'     => 'Die Liebenden',
        'eyebrow'  => 'VI &middot; Die Liebenden',
        'headline' => 'Die Wahl deiner Werte.',
        'p1'       => 'Im ursprünglichen Tarot geht es bei den Liebenden nicht nur um Romantik, sondern um die große Weggabelung: Zwischen dem bequemen Pfad, den andere für dich erwarten, und dem lebendigen Pfad, der deinen eigenen Werten entspricht.',
        'p2'       => 'Halbheiten zermürben dich. Erst wenn Kopf und Herz wieder in dieselbe Richtung ziehen, kehrt deine volle Energie zurück.',
        'exercise' => 'Nimm dir 10 Minuten für zwei Spalten auf einem Blatt: <em>„Wo sage ich Ja aus Angst?“</em> und <em>„Wo will ich ein echtes Ja aus Überzeugung geben?“</em> Setz diese Woche mindestens eine klare Grenze aus der linken Spalte.',
    ],
    'drei-der-staebe' => [
        'name'     => 'Drei der Stäbe',
        'eyebrow'  => 'III &middot; Drei der Stäbe',
        'headline' => 'Dein Horizont ruft.',
        'p1'       => 'Die Figur auf der Drei der Stäbe steht auf einer Anhöhe und blickt aufs Meer hinaus – ihre Schiffe sind bereits unterwegs. Diese Karte taucht auf, wenn dein bisheriges Umfeld dir zu eng geworden ist und du spürst, dass dein nächstes Kapitel größer sein will als dein altes.',
        'p2'       => 'Die Gefahr liegt im Zögern am Ufer: zu lange zu warten, bis sich alles 100 % sicher anfühlt.',
        'exercise' => 'Mach diese Woche eine einzige bewusste Handlung für dein größeres Bild – schreib die Nachricht, führe das Gespräch oder block dir die Zeit für das Projekt, das dich gleichzeitig leicht nervös und lebendig macht.',
    ],
    'die-welt' => [
        'name'     => 'Die Welt',
        'eyebrow'  => 'XXI &middot; Die Welt',
        'headline' => 'Ein Zyklus schließt sich.',
        'p1'       => 'Die Welt ist das Ende der Großen Arkana – und zugleich der Auftakt einer neuen, höheren Spirale. Du hast eine wichtige Lektion der letzten Monate oder Jahre im Kern verstanden. Doch genau hier liegt die subtile Falle: aus Gewohnheit im abgeschlossenen Kapitel zu verweilen.',
        'p2'       => 'Integration bedeutet: Würdige, was dich bis hierhin getragen hat – und tritt bewusst auf die nächste Stufe.',
        'exercise' => 'Schreib dir drei Dinge auf, die du im letzten Jahr gemeistert hast, ohne sie je wirklich gefeiert zu haben. Danach notiere in einem Satz, worum es in deinem <strong>nächsten</strong> Kapitel gehen soll.',
    ],
];

// ─── Hilfsfunktionen ───
function jsonOut(int $code, array $body): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function b64urlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64urlDecode(string $data): string {
    $pad = strlen($data) % 4;
    if ($pad > 0) $data .= str_repeat('=', 4 - $pad);
    $decoded = base64_decode(strtr($data, '-_', '+/'), true);
    return $decoded === false ? '' : $decoded;
}

function mailjetRequest(string $url, array $payload, string $apiKey, string $apiSecret): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERPWD        => $apiKey . ':' . $apiSecret,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    $resp   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok' => ($resp !== false && $status >= 200 && $status < 300), 'status' => $status, 'body' => $resp];
}

function sendMailjetEmail(
    string $toEmail,
    string $subject,
    string $htmlBody,
    string $textBody,
    string $fromEmail,
    string $fromName,
    string $apiKey,
    string $apiSecret
): array {
    return mailjetRequest('https://api.mailjet.com/v3.1/send', [
        'Messages' => [[
            'From'     => ['Email' => $fromEmail, 'Name' => $fromName],
            'ReplyTo'  => ['Email' => $fromEmail, 'Name' => $fromName],
            'To'       => [['Email' => $toEmail]],
            'Subject'  => $subject,
            'TextPart' => $textBody,
            'HTMLPart' => $htmlBody,
        ]],
    ], $apiKey, $apiSecret);
}

function addContactToMailjetList(
    int $listId,
    string $email,
    string $card,
    string $list,
    string $apiKey,
    string $apiSecret
): array {
    $url = "https://api.mailjet.com/v3/REST/contactslist/{$listId}/managecontact";
    $props = [];
    if ($card !== '') $props['gezogene_karte'] = $card;
    if ($list !== '') $props['quelle'] = $list;

    $payload = ['Email' => $email, 'Action' => 'addnoforce'];
    if (!empty($props)) $payload['Properties'] = $props;

    $res = mailjetRequest($url, $payload, $apiKey, $apiSecret);
    // Fallback ohne Properties, falls Custom Properties in Mailjet noch nicht angelegt wurden
    if (!$res['ok'] && !empty($props)) {
        $res = mailjetRequest($url, ['Email' => $email, 'Action' => 'addnoforce'], $apiKey, $apiSecret);
    }
    return $res;
}

function mailShell(string $preheader, string $contentHtml, string $siteUrl): string {
    return '<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600&family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&display=swap" rel="stylesheet">
<style>body{margin:0;padding:0;background:#120e09;}a{color:#d6a441;}@media(max-width:600px){.wrap{width:100%!important;}.pad{padding-left:24px!important;padding-right:24px!important;}.h1{font-size:28px!important;}}</style>
</head><body style="margin:0;padding:0;background:#120e09;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8') . '</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#120e09;">
<tr><td align="center" style="padding:32px 12px;">
<table role="presentation" class="wrap" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background:#1c1610;border:1px solid #6b5426;">
  <tr><td align="center" class="pad" style="padding:40px 48px 8px;">
    <a href="' . $siteUrl . '/"><img src="' . $siteUrl . '/mail/logo.png" width="72" alt="Sand der Zeit" style="display:block;width:72px;height:auto;border:0;"></a>
  </td></tr>
  <tr><td align="center" class="pad" style="padding:20px 48px 0;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
      <td style="padding:0 6px;"><img src="' . $siteUrl . '/mail/filip.png" width="80" height="80" alt="Filip" style="display:block;width:80px;height:80px;border:0;"></td>
      <td style="padding:0 6px;"><img src="' . $siteUrl . '/mail/jan.png" width="80" height="80" alt="Jan" style="display:block;width:80px;height:80px;border:0;"></td>
    </tr></table>
  </td></tr>
  <tr><td align="center" class="pad" style="padding:12px 48px 0;font-family:\'Cinzel\',Georgia,serif;font-size:12px;letter-spacing:4px;text-transform:uppercase;color:#d6a441;">Filip &amp; Jan</td></tr>
  <tr><td align="center" style="padding:28px 48px 0;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
      <td width="80" style="border-top:1px solid #6b5426;font-size:0;line-height:0;">&nbsp;</td>
      <td style="padding:0 12px;font-size:12px;color:#d6a441;line-height:1;">&#9670;</td>
      <td width="80" style="border-top:1px solid #6b5426;font-size:0;line-height:0;">&nbsp;</td>
    </tr></table>
  </td></tr>
  ' . $contentHtml . '
  <tr><td class="pad" align="center" style="padding:40px 48px 36px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="border-top:1px solid #3a2e1e;font-size:0;line-height:0;">&nbsp;</td></tr></table>
    <p style="margin:20px 0 8px;font-family:\'Cinzel\',Georgia,serif;font-size:11px;letter-spacing:3px;text-transform:uppercase;">
      <a href="https://www.instagram.com/sand.der.zeit/" style="color:#b6a07c;text-decoration:none;">Instagram</a> &nbsp;&middot;&nbsp;
      <a href="https://www.tiktok.com/@sand.der.zeit" style="color:#b6a07c;text-decoration:none;">TikTok</a> &nbsp;&middot;&nbsp;
      <a href="' . $siteUrl . '/" style="color:#b6a07c;text-decoration:none;">sandderzeit.de</a>
    </p>
    <p style="margin:0;font-family:Georgia,serif;font-size:12px;line-height:1.6;color:#8a7658;">
      Hoffmann &amp; Polifka GbR &middot; Holbeinstraße 2 &middot; 50733 Köln<br>
      Du kannst dich jederzeit per kurzer Antwort oder Mail an info@sandderzeit.de abmelden.<br>
      <a href="' . $siteUrl . '/datenschutz.html" style="color:#8a7658;">Datenschutz</a> &middot; <a href="' . $siteUrl . '/imprint.html" style="color:#8a7658;">Impressum</a>
    </p>
  </td></tr>
</table></td></tr></table></body></html>';
}

function renderStatusPage(string $title, string $eyebrow, string $heading, string $body, string $ctaUrl, string $ctaLabel): void {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ' · Sand der Zeit</title>'
       . '<link rel="stylesheet" href="/styles.css"></head>'
       . '<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 16px;">'
       . '<main style="max-width:580px;width:100%;text-align:center;background:var(--surface);border:1px solid var(--line-strong);border-radius:var(--radius-card);padding:48px 32px;box-shadow:var(--glow),var(--card-lift);">'
       . '<p style="font-family:var(--font-display);font-size:12px;letter-spacing:.28em;text-transform:uppercase;color:var(--gold);margin-bottom:12px;">' . $eyebrow . '</p>'
       . '<h1 style="font-family:var(--font-display);font-weight:400;font-size:clamp(28px,5vw,40px);line-height:1.15;color:var(--ink);margin-bottom:20px;">' . $heading . '</h1>'
       . '<p style="font-size:20px;line-height:1.6;color:var(--ink-muted);margin-bottom:32px;">' . $body . '</p>'
       . '<a href="' . htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary">' . htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8') . '</a>'
       . '</main></body></html>';
    exit;
}

// ─── 1. GET: Double-Opt-In Bestätigung ───
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['confirm'])) {
    if ($apiKey === '' || $apiSecret === '') {
        renderStatusPage('Fehler', 'Hinweis', 'Konfiguration fehlt', 'Die E-Mail-Konfiguration ist auf dem Server noch nicht hinterlegt.', '/', 'Zur Startseite');
    }

    $token = (string)$_GET['confirm'];
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) {
        renderStatusPage('Ungültiger Link', 'Hinweis', 'Dieser Link ist leider ungültig.', 'Bitte trag dich auf der Startseite einfach noch einmal kurz ein.', '/', 'Zur Startseite');
    }

    [$b64, $sig] = $parts;
    $expected = hash_hmac('sha256', $b64, $apiSecret);
    if (!hash_equals($expected, $sig)) {
        renderStatusPage('Ungültiger Link', 'Hinweis', 'Dieser Link ist leider ungültig.', 'Bitte trag dich auf der Startseite einfach noch einmal kurz ein.', '/', 'Zur Startseite');
    }

    $data = json_decode(b64urlDecode($b64), true);
    if (!is_array($data) || empty($data['e']) || !filter_var($data['e'], FILTER_VALIDATE_EMAIL)) {
        renderStatusPage('Ungültiger Link', 'Hinweis', 'Dieser Link ist leider ungültig.', 'Bitte trag dich auf der Startseite einfach noch einmal kurz ein.', '/', 'Zur Startseite');
    }

    // 48 Stunden Gültigkeit
    if (time() - (int)($data['t'] ?? 0) > 172800) {
        renderStatusPage('Link abgelaufen', 'Hinweis', 'Der Bestätigungslink ist abgelaufen.', 'Trag dich einfach kurz neu ein, dann schicken wir dir sofort einen frischen Link.', '/', 'Zur Startseite');
    }

    $email = (string)$data['e'];
    $card  = (string)($data['c'] ?? '');
    $list  = (string)($data['l'] ?? 'steinschleifer');

    // Doppelklick / Mail-Scanner Schutz (verhindert doppelten Versand)
    $lockFile = rtrim(sys_get_temp_dir(), '/') . '/sdz_doi_' . hash('sha256', $sig);
    $alreadyProcessed = is_file($lockFile);

    if (!$alreadyProcessed) {
        @file_put_contents($lockFile, (string)time());

        // 1. In Mailjet-Kontaktliste eintragen
        addContactToMailjetList($listId, $email, $card, $list, $apiKey, $apiSecret);

        // 2. Individuelle Deutungs- & Willkommensmail versenden
        $cardData = ($card !== '' && isset($CARDS[$card])) ? $CARDS[$card] : null;
        if ($cardData !== null) {
            $subject   = 'Deine Karte: ' . $cardData['name'] . ' – und deine Übung für diese Woche';
            $preheader = 'Deine Tiefendeutung zu ' . $cardData['name'] . ', deine Wochenübung – und dein Platz auf der Warteliste.';
            $introHtml = '<tr><td class="pad" style="padding:28px 48px 0;font-family:\'Cormorant Garamond\',Georgia,serif;color:#f1e4c6;">'
                . '<p style="margin:0 0 8px;font-family:\'Cinzel\',Georgia,serif;font-size:12px;letter-spacing:4px;text-transform:uppercase;color:#d6a441;text-align:center;">' . $cardData['eyebrow'] . '</p>'
                . '<h1 class="h1" style="margin:0 0 24px;font-family:\'Cinzel\',Georgia,serif;font-weight:600;font-size:32px;line-height:1.2;color:#f1e4c6;text-align:center;">' . $cardData['headline'] . '</h1>'
                . '<p style="margin:0 0 16px;font-size:20px;line-height:1.6;">' . $cardData['p1'] . '</p>'
                . '<p style="margin:0 0 24px;font-size:20px;line-height:1.6;">' . $cardData['p2'] . '</p>'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#16110c;border-left:3px solid #d6a441;">'
                . '<tr><td style="padding:20px 24px;font-family:\'Cormorant Garamond\',Georgia,serif;color:#f1e4c6;">'
                . '<p style="margin:0 0 6px;font-family:\'Cinzel\',Georgia,serif;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#d6a441;">Deine Übung für diese Woche</p>'
                . '<p style="margin:0;font-size:19px;line-height:1.55;">' . $cardData['exercise'] . '</p>'
                . '</td></tr></table>'
                . '<p style="margin:24px 0 0;font-size:20px;line-height:1.6;">Eine Karte zeigt dir den Punkt, an dem dein Stein noch rau ist. Aber allein im Kopf bleibt jede Erkenntnis nach drei Tagen wieder liegen. Genau deshalb bauen wir gerade etwas auf, das tiefer geht.</p>'
                . '</td></tr>';
            $textPart  = "Deine Karte: {$cardData['name']}\n\n"
                . strip_tags($cardData['p1']) . "\n\n"
                . strip_tags($cardData['p2']) . "\n\n"
                . "Deine Übung für diese Woche:\n" . strip_tags($cardData['exercise']) . "\n\n"
                . "Du stehst auf der Warteliste für Die Steinschleifer (Start am 21. Dezember, 33 € statt 55 €/Monat, nur 10 Gründungsplätze): {$siteUrl}/steinschleifer/\n\n"
                . "Filip & Jan · Sand der Zeit";
        } else {
            $subject   = 'Willkommen. Dein Stein wartet.';
            $preheader = 'Schön, dass du da bist. Und du stehst auf der Warteliste für die Steinschleifer.';
            $introHtml = '<tr><td class="pad" style="padding:28px 48px 0;font-family:\'Cormorant Garamond\',Georgia,serif;color:#f1e4c6;">'
                . '<p style="margin:0 0 8px;font-family:\'Cinzel\',Georgia,serif;font-size:12px;letter-spacing:4px;text-transform:uppercase;color:#d6a441;text-align:center;">Willkommen bei Sand der Zeit</p>'
                . '<h1 class="h1" style="margin:0 0 24px;font-family:\'Cinzel\',Georgia,serif;font-weight:600;font-size:34px;line-height:1.2;color:#f1e4c6;text-align:center;">Schön, dass du da bist.</h1>'
                . '<p style="margin:0 0 16px;font-size:20px;line-height:1.6;">Wir melden uns nur, wenn wir dir wirklich etwas zu sagen haben: eine Karte, die gerade wichtig ist, eine neue Folge oder etwas, das bei uns passiert. Kein Lärm, versprochen.</p>'
                . '<p style="margin:0;font-size:20px;line-height:1.6;">Und du stehst auf der Warteliste für etwas, das uns gerade sehr am Herzen liegt.</p>'
                . '</td></tr>';
            $textPart  = "Schön, dass du da bist. Du stehst auf der Warteliste für Die Steinschleifer (Start am 21. Dezember, 33 € statt 55 €/Monat, nur 10 Gründungsplätze): {$siteUrl}/steinschleifer/\n\nFilip & Jan · Sand der Zeit";
        }

        $communityHtml = '<tr><td class="pad" style="padding:28px 48px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#2a2117;border:1px solid #d6a441;">
              <tr><td align="center" style="padding:32px 28px;font-family:\'Cormorant Garamond\',Georgia,serif;color:#f1e4c6;">
                <p style="margin:0 0 8px;font-family:\'Cinzel\',Georgia,serif;font-size:11px;letter-spacing:4px;text-transform:uppercase;color:#d6a441;">Start am 21. Dezember &middot; Gelebte Mystik</p>
                <p style="margin:0 0 16px;font-family:\'Cinzel\',Georgia,serif;font-weight:600;font-size:26px;line-height:1.25;color:#f1e4c6;">Die Steinschleifer</p>
                <p style="margin:0 0 16px;font-size:19px;line-height:1.55;">Unsere Community für gelebte Mystik – für Menschen, die wirklich an sich arbeiten wollen. Live-Gespräche, Gruppenlegungen, Tarot, Astrologie, antike Geschichte und okkultes Wissen, angewendet im echten Leben.</p>
                <p style="margin:0 0 24px;font-size:19px;line-height:1.55;">Auf der Warteliste bist du <strong>48 Stunden vor allen anderen dabei</strong>. Die ersten 10 Mitglieder bekommen das Gründungsangebot: <strong style="color:#f0c35a;">33 € statt 55 € im Monat, für immer</strong>.</p>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                  <td align="center" style="background:#d6a441;"><a href="' . $siteUrl . '/steinschleifer/" style="display:inline-block;padding:14px 28px;font-family:\'Cinzel\',Georgia,serif;font-size:13px;letter-spacing:3px;text-transform:uppercase;color:#1a0f0a;text-decoration:none;">&rarr; Zu den Steinschleifern</a></td>
                </tr></table>
              </td></tr>
            </table>
          </td></tr>
          <tr><td class="pad" style="padding:32px 48px 0;font-family:\'Cormorant Garamond\',Georgia,serif;color:#f1e4c6;">
            <p style="margin:0 0 16px;font-size:20px;line-height:1.6;">Wenn du magst, antworte einfach kurz auf diese Mail: <strong>Hat deine Karte getroffen – und woran schleifst du gerade?</strong> Wir lesen jede einzelne Antwort persönlich.</p>
            <p style="margin:24px 0 0;font-size:22px;font-style:italic;line-height:1.4;color:#d6a441;text-align:center;">„Ich schleife meinen Stein.“</p>
            <p style="margin:16px 0 0;font-size:20px;line-height:1.6;text-align:center;">Filip &amp; Jan</p>
          </td></tr>';

        $htmlBody = mailShell($preheader, $introHtml . $communityHtml, $siteUrl);
        sendMailjetEmail($email, $subject, $htmlBody, $textPart, $fromEmail, $fromName, $apiKey, $apiSecret);
    }

    $cardLabel = ($card !== '' && isset($CARDS[$card])) ? $CARDS[$card]['name'] : '';
    $msgBody   = $cardLabel !== ''
        ? 'Deine Anmeldung ist bestätigt. Deine persönliche Tiefendeutung zu <strong>' . htmlspecialchars($cardLabel, ENT_QUOTES, 'UTF-8') . '</strong> inklusive Wochenübung ist gerade in deinem Postfach gelandet – und du stehst auf der Warteliste für Die Steinschleifer.'
        : 'Deine Anmeldung ist bestätigt. Schau in dein Postfach – deine Willkommensmail ist unterwegs und du stehst auf der Warteliste für Die Steinschleifer.';

    renderStatusPage(
        'Anmeldung bestätigt',
        'Sand der Zeit · Bestätigt',
        'Dein Stein wartet.',
        $msgBody,
        '/steinschleifer/',
        'Zu den Steinschleifern'
    );
}

// ─── 2. POST: Formular-Anmeldung (Double-Opt-In Mail auslösen) ───
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonOut(405, ['ok' => false, 'error' => 'method']);

$raw = file_get_contents('php://input');
$in  = json_decode($raw ?: '', true);
if (!is_array($in)) $in = $_POST;

// Honeypot
if (!empty($in['website'])) jsonOut(200, ['ok' => true]);

$email = trim((string)($in['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) jsonOut(400, ['ok' => false, 'error' => 'email']);
if (empty($in['consent'])) jsonOut(400, ['ok' => false, 'error' => 'consent']);

$card = trim((string)($in['card'] ?? ''));
if ($card !== '' && !isset($CARDS[$card])) $card = '';
$list = trim((string)($in['list'] ?? 'newsletter'));

// Lokales Backup-Log
$logDir = __DIR__ . '/../data';
if (is_dir($logDir)) {
    @file_put_contents(
        $logDir . '/leads.log',
        json_encode(['time' => date('c'), 'email' => $email, 'list' => $list, 'card' => $card], JSON_UNESCAPED_UNICODE) . "\n",
        FILE_APPEND | LOCK_EX
    );
}

if ($apiKey === '' || $apiSecret === '') {
    jsonOut(500, ['ok' => false, 'error' => 'config']);
}

// Signierten Double-Opt-In Link bauen
$payload = b64urlEncode(json_encode([
    'e' => $email,
    'c' => $card,
    'l' => $list,
    't' => time(),
]));
$sig        = hash_hmac('sha256', $payload, $apiSecret);
$confirmUrl = $siteUrl . '/newsletter.php?confirm=' . urlencode($payload . '.' . $sig);

$cardName = ($card !== '' && isset($CARDS[$card])) ? $CARDS[$card]['name'] : '';
$subject  = $cardName !== ''
    ? 'Kurz bestätigen: Deine Deutung zu „' . $cardName . '“ wartet'
    : 'Bitte bestätige kurz deine Anmeldung bei Sand der Zeit';

$preheader = $cardName !== ''
    ? 'Ein Klick genügt – dann schicken wir dir sofort deine Tiefendeutung zu ' . $cardName . ' und deine Übung für diese Woche.'
    : 'Ein Klick genügt, um deine Anmeldung bei Sand der Zeit zu bestätigen.';

$ctaText = $cardName !== '' ? '&rarr; Bestätigen &amp; Deutung erhalten' : '&rarr; Anmeldung bestätigen';
$leadTxt = $cardName !== ''
    ? 'Du hast gerade <strong>' . htmlspecialchars($cardName, ENT_QUOTES, 'UTF-8') . '</strong> gezogen. Bestätige mit einem Klick kurz deine E-Mail-Adresse – danach schicken wir dir sofort deine persönliche Tiefendeutung inklusive Wochenübung und sichern deinen unverbindlichen Platz auf der Warteliste für <strong>Die Steinschleifer</strong>.'
    : 'Bestätige mit einem Klick kurz deine E-Mail-Adresse, damit wir dir schreiben und deinen Platz auf der Warteliste für <strong>Die Steinschleifer</strong> vormerken dürfen.';

$doiContent = '<tr><td class="pad" style="padding:28px 48px 0;font-family:\'Cormorant Garamond\',Georgia,serif;color:#f1e4c6;">'
    . '<p style="margin:0 0 8px;font-family:\'Cinzel\',Georgia,serif;font-size:12px;letter-spacing:4px;text-transform:uppercase;color:#d6a441;text-align:center;">Noch ein kurzer Schritt</p>'
    . '<h1 class="h1" style="margin:0 0 24px;font-family:\'Cinzel\',Georgia,serif;font-weight:600;font-size:32px;line-height:1.2;color:#f1e4c6;text-align:center;">Bestätige kurz deine E-Mail.</h1>'
    . '<p style="margin:0 0 28px;font-size:20px;line-height:1.6;">' . $leadTxt . '</p>'
    . '<table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0"><tr>'
    . '<td align="center" style="background:#d6a441;"><a href="' . htmlspecialchars($confirmUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:16px 32px;font-family:\'Cinzel\',Georgia,serif;font-size:13px;letter-spacing:3px;text-transform:uppercase;color:#1a0f0a;text-decoration:none;">' . $ctaText . '</a></td>'
    . '</tr></table>'
    . '<p style="margin:28px 0 0;font-size:16px;line-height:1.5;color:#b6a07c;text-align:center;">Falls du dich nicht selbst eingetragen hast, ignoriere diese Mail einfach – es passiert dann nichts weiter.</p>'
    . '</td></tr>';

$doiHtml = mailShell($preheader, $doiContent, $siteUrl);
$doiText = strip_tags($leadTxt) . "\n\nJetzt bestätigen:\n{$confirmUrl}\n\nFalls du dich nicht eingetragen hast, ignoriere diese Mail einfach.\n\nFilip & Jan · Sand der Zeit";

$res = sendMailjetEmail($email, $subject, $doiHtml, $doiText, $fromEmail, $fromName, $apiKey, $apiSecret);
if (!$res['ok']) {
    jsonOut(502, ['ok' => false, 'error' => 'mailjet', 'status' => $res['status']]);
}

jsonOut(200, ['ok' => true]);
