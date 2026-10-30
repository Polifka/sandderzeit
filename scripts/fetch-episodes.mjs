// Holt beim Build die neuesten Folgen aus dem öffentlichen YouTube-Feed der Playlist
// und speichert Titel und Vorschaubilder lokal. Schlägt etwas fehl, bleibt der letzte
// Stand in data/episodes.json erhalten und der Build läuft trotzdem weiter.
import { readFile, writeFile, mkdir } from 'node:fs/promises'

const config = JSON.parse(await readFile(new URL('../data/podcast.config.json', import.meta.url)))
const episodesFile = new URL('../data/episodes.json', import.meta.url)
const thumbDir = new URL('../public/episodes/', import.meta.url)

const decode = (s) => s
  .replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"')
  .replace(/&#39;/g, "'").replace(/&amp;/g, '&')

async function main() {
  const url = `https://www.youtube.com/feeds/videos.xml?playlist_id=${encodeURIComponent(config.playlistId)}`
  const res = await fetch(url, { signal: AbortSignal.timeout(15000) })
  if (!res.ok) throw new Error(`Feed antwortet mit ${res.status}`)
  const xml = await res.text()

  const entries = [...xml.matchAll(/<entry>([\s\S]*?)<\/entry>/g)].map(([, e]) => ({
    id: (e.match(/<yt:videoId>([^<]+)<\/yt:videoId>/) || [])[1],
    title: decode((e.match(/<title>([^<]*)<\/title>/) || [])[1] || ''),
    published: (e.match(/<published>([^<]+)<\/published>/) || [])[1] || ''
  })).filter((e) => e.id)

  if (!entries.length) throw new Error('Feed enthält keine Folgen')

  const latest = entries
    .sort((a, b) => b.published.localeCompare(a.published))
    .slice(0, config.count)

  await mkdir(thumbDir, { recursive: true })
  for (const ep of latest) {
    try {
      const img = await fetch(`https://i.ytimg.com/vi/${ep.id}/hqdefault.jpg`, { signal: AbortSignal.timeout(15000) })
      if (img.ok) await writeFile(new URL(`${ep.id}.jpg`, thumbDir), Buffer.from(await img.arrayBuffer()))
    } catch { /* Vorschaubild fehlt: die Seite zeigt dann nur den Rahmen */ }
  }

  await writeFile(episodesFile, JSON.stringify(latest, null, 2) + '\n')
  console.log(`Podcast: ${latest.length} Folgen geladen (${latest.map((e) => e.title).join(' | ')})`)
}

main().catch((err) => {
  console.warn(`Podcast: Feed nicht geladen (${err.message}). Der letzte Stand bleibt erhalten.`)
})
