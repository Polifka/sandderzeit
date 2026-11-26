<?php
// Vorlage für Mailjet-Zugangsdaten.
// Als "mailjet-config.php" neben newsletter.php auf den Server legen (oder per GitHub Secrets deployen).
return [
    'api_key'      => 'DEIN_MAILJET_API_KEY',
    'api_secret'   => 'DEIN_MAILJET_API_SECRET',
    'from_email'   => 'info@sandderzeit.de',
    'from_name'    => 'Filip & Jan · Sand der Zeit',
    'list_id'      => 10648702,
    'site_url'     => 'https://sandderzeit.de',
];
