import { resolve } from 'path'
import { readFileSync, existsSync } from 'fs'
import { defineConfig } from 'vite'

const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')

// Setzt die neuesten Folgen (aus data/episodes.json) an der Stelle <!--EPISODES--> ein.
function podcastEpisodes() {
  return {
    name: 'podcast-episodes',
    transformIndexHtml(html) {
      if (!html.includes('<!--EPISODES-->')) return html
      const episodes = JSON.parse(readFileSync(resolve(__dirname, 'data/episodes.json'), 'utf8'))
      const cards = episodes.map((ep) => {
        const local = resolve(__dirname, `public/episodes/${ep.id}.jpg`)
        const thumb = existsSync(local) ? `/episodes/${ep.id}.jpg` : `https://i.ytimg.com/vi/${ep.id}/hqdefault.jpg`
        const date = ep.published
          ? new Date(ep.published).toLocaleDateString('de-DE', { day: 'numeric', month: 'long', year: 'numeric' })
          : ''
        return `
                    <article class="episode reveal">
                        <div class="parchment">
                            <button class="video" type="button" data-video="${esc(ep.id)}" aria-label="Folge abspielen: ${esc(ep.title)}">
                                ${thumb ? `<img src="${thumb}" alt="" loading="lazy" width="480" height="360">` : ''}
                                <span class="video__play" aria-hidden="true"></span>
                            </button>
                        </div>
                        <h3 class="episode__title">${esc(ep.title)}</h3>
                        ${date ? `<p class="episode__date">${date}</p>` : ''}
                    </article>`
      }).join('')
      return html.replace('<!--EPISODES-->', cards)
    },
  }
}

export default defineConfig({
  plugins: [podcastEpisodes()],
  build: {
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'index.html'),
        imprint: resolve(__dirname, 'imprint.html'),
        agb: resolve(__dirname, 'agb.html'),
        datenschutz: resolve(__dirname, 'datenschutz.html'),
        thumbnail: resolve(__dirname, 'thumbnail.html'),
        designsystem: resolve(__dirname, 'design-system.html'),
      },
    },
  },
})
