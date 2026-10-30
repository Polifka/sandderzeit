// Schreibt dist/api/config.php für die KI-Szene im Thumbnail-Werkzeug.
// Die Werte kommen aus GitHub Secrets/Variables und landen nie im Repo.
import { writeFileSync, existsSync } from 'fs'

const { GEMINI_API_KEY = '', TOOL_PASSWORD = '', GEMINI_MODEL = '', KI_DAILY_LIMIT = '' } = process.env
if (!GEMINI_API_KEY || !TOOL_PASSWORD) {
  console.log('::warning::GEMINI_API_KEY oder TOOL_PASSWORD fehlt, die KI-Szene bleibt aus.')
  process.exit(0)
}
if (!existsSync('dist/api')) { console.error('dist/api fehlt, erst npm run build ausführen.'); process.exit(1) }

const q = (v) => "'" + String(v).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'"
const php = `<?php
return [
  'gemini_api_key' => ${q(GEMINI_API_KEY.trim())},
  'tool_password' => ${q(TOOL_PASSWORD)},
  'model' => ${q(GEMINI_MODEL.trim() || 'gemini-3-pro-image-preview')},
  'daily_limit' => ${parseInt(KI_DAILY_LIMIT, 10) || 80},
];
`
writeFileSync('dist/api/config.php', php)
console.log('KI-Konfiguration geschrieben.')
