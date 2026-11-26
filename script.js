/* ===================================================
   SAND DER ZEIT – JavaScript v2
   - Scroll reveal (Intersection Observer)
   - Header scroll behaviour
   - 3D card tilt effect
   =================================================== */

document.addEventListener('DOMContentLoaded', () => {

    /* ─── 2. SMOOTH SCROLL ─── */
    document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', e => {
            const target = document.querySelector(a.getAttribute('href'));
            if (target) {
                e.preventDefault();
                const offset = 80; // header height
                const top = target.getBoundingClientRect().top + window.scrollY - offset;
                window.scrollTo({ top, behavior: 'smooth' });
            }
        });
    });


    /* ─── 3. HEADER SCROLL SHRINK ─── */
    const header = document.getElementById('main-header');
    window.addEventListener('scroll', () => {
        header.classList.toggle('scrolled', window.scrollY > 60);
    }, { passive: true });


    /* ─── 4. SCROLL REVEAL ─── */
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                // stagger children if they have reveal class too
                entry.target.querySelectorAll('.reveal').forEach((child, i) => {
                    setTimeout(() => child.classList.add('visible'), i * 120);
                });
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));


    /* ─── 5. 3D TILT EFFECT ─── */
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const tiltCards = reduceMotion ? [] : document.querySelectorAll('.tilt-card');

    tiltCards.forEach(card => {
        card.addEventListener('mousemove', e => {
            const rect   = card.getBoundingClientRect();
            const cx     = rect.left + rect.width  / 2;
            const cy     = rect.top  + rect.height / 2;
            const dx     = (e.clientX - cx) / (rect.width  / 2);
            const dy     = (e.clientY - cy) / (rect.height / 2);
            const rotX   =  dy * -6;  // degrees
            const rotY   =  dx *  6;
            card.style.transform = `perspective(800px) rotateX(${rotX}deg) rotateY(${rotY}deg) translateY(-6px)`;
            card.style.transition = 'transform 0.08s linear';
        });
        card.addEventListener('mouseleave', () => {
            card.style.transition = 'transform 0.55s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
            card.style.transform  = 'perspective(800px) rotateX(0deg) rotateY(0deg) translateY(0px)';
        });
    });

    /* ─── 5. MOBILE HAMBURGER MENU ─── */
    const hamburger = document.getElementById('hamburger');
    const navMenu = document.getElementById('nav-menu');
    const navLinks = document.querySelectorAll('#nav-menu ul li a');

    if (hamburger && navMenu) {
        hamburger.addEventListener('click', () => {
            const open = navMenu.classList.toggle('active');
            hamburger.classList.toggle('active', open);
            hamburger.setAttribute('aria-expanded', String(open));
        });

        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                hamburger.classList.remove('active');
                navMenu.classList.remove('active');
                hamburger.setAttribute('aria-expanded', 'false');
            });
        });
    }


    /* ─── 6. PODCAST: Video erst beim Klick laden ─── */
    document.querySelectorAll('.video[data-video]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.video;
            const frame = document.createElement('iframe');
            frame.src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(id)}?autoplay=1&rel=0`;
            frame.title = btn.getAttribute('aria-label') || 'Podcastfolge';
            frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
            frame.allowFullscreen = true;
            const box = document.createElement('div');
            box.className = 'video';
            box.appendChild(frame);
            btn.replaceWith(box);
        }, { once: true });
    });

});

/* ─── Newsletter & Warteliste (eigene Formulare, Anmeldung läuft über /newsletter.php zu Mailjet) ─── */
document.querySelectorAll('.nl-form').forEach((nlForm) => {
  const msg = nlForm.querySelector('.nl-form__msg')
  const btn = nlForm.querySelector('button[type="submit"]')
  const say = (text, kind) => { msg.textContent = text; msg.className = 'nl-form__msg' + (kind ? ' is-' + kind : '') }
  nlForm.addEventListener('submit', async (e) => {
    e.preventDefault()
    const email = nlForm.email.value.trim()
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return say('Bitte gib eine gültige E-Mail-Adresse ein.', 'error')
    if (!nlForm.consent.checked) return say('Bitte bestätige noch das Häkchen.', 'error')
    btn.disabled = true
    say('Einen Moment …')
    try {
      const cardVal = nlForm.card ? nlForm.card.value : ''
      const res = await fetch('/newsletter.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          email,
          consent: true,
          website: nlForm.website.value,
          list: nlForm.list ? nlForm.list.value : 'newsletter',
          card: cardVal
        }),
      })
      const data = await res.json().catch(() => ({}))
      if (!res.ok || !data.ok) throw new Error(data.error || 'fail')
      nlForm.classList.add('is-done')
      if (cardVal) {
        say('Fast geschafft! Deine persönliche Deutung ist unterwegs. Schau in dein Postfach und bestätige kurz deine Anmeldung.', 'ok')
      } else {
        say('Fast geschafft. Schau in dein Postfach und bestätige deine Anmeldung.', 'ok')
      }
    } catch (err) {
      say('Das hat leider nicht geklappt. Versuch es bitte gleich noch einmal oder schreib uns an info@sandderzeit.de.', 'error')
    } finally {
      btn.disabled = false
    }
  })
})

/* ─── Orakel-Funnel: Interaktive Kartenziehung ─── */
const ORACLE_CARDS = {
  'der-magier': {
    badge: 'I · DER MAGIER',
    archetype: 'Schöpferkraft & Umsetzung',
    hook: 'Dein Werkzeugkasten ist voll, aber deine Kraft verstreut sich.',
    text: 'Du trägst enorme Schöpferkraft in dir. Doch oft verpufft die Energie in tausend Ideen, bevor der erste echte Stein gesetzt ist. Dein Geist weiß alles – jetzt braucht es Erdung, klaren Fokus und Meisterschaft.',
    impuls: 'An welchem einen Projekt musst du jetzt aufhören nachzudenken und anfangen zu schleifen?'
  },
  'acht-der-muenzen': {
    badge: 'VIII · ACHT DER MÜNZEN',
    archetype: 'Meisterschaft & Handwerk (Der Steinschleifer)',
    hook: 'Echte Verwandlung geschieht im Stillen – Schlag für Schlag.',
    text: 'Du spürst den Ruf nach wahrer Meisterschaft. Doch die moderne Welt will schnelle Abkürzungen, während deine Seele nach echtem Handwerk verlangt. Die Gefahr: Ungeduld oder Zweifel an der scheinbar monotonen täglichen Praxis.',
    impuls: 'Bist du bereit, dich der stillen Hingabe deines eigenen Wachstums zu verschreiben?'
  },
  'der-turm': {
    badge: 'XVI · DER TURM',
    archetype: 'Umbruch & Befreiung',
    hook: 'Was einst Schutz bot, ist zum Gefängnis geworden.',
    text: 'Eine alte Struktur, Gewohnheit oder Identität bricht ein. Das fühlt sich bedrohlich an, ist aber das größte Geschenk: Alchemie beginnt immer mit dem Zerfall des Unwahren (Nigredo). Was fällt, war nicht dein wahrer Kern.',
    impuls: 'Welche Illusion darfst du jetzt loslassen, um Raum für dein wahres Selbst zu schaffen?'
  },
  'as-der-schwerter': {
    badge: 'AS DER SCHWERTER',
    archetype: 'Klarheit & Radikale Wahrheit',
    hook: 'Die Wahrheit schneidet tief – aber sie befreit dich.',
    text: 'Du spürst schon lange, was nicht mehr stimmig ist. Doch der Verstand weicht aus und sucht Ausflüchte. Das Schwert fordert unmissverständliche Klarheit und den Mut zu einer klaren Entscheidung ohne Weichspüler.',
    impuls: 'Welche unbequeme Wahrheit sprichst du dir selbst noch nicht ehrlich aus?'
  },
  'die-liebenden': {
    badge: 'VI · DIE LIEBENDEN',
    archetype: 'Herzensentscheidung & Polarität',
    hook: 'Zwischen dem, was sicher ist, und dem, was dich lebendig macht.',
    text: 'Du stehst an einer Weggabelung. Es geht nicht um eine rationale Pro-und-Contra-Liste, sondern um die Frage: Nach welchen Werten willst du dein Leben führen? Halbheiten kosten dich tagtäglich deine Lebenskraft.',
    impuls: 'Wählst du aus Angst vor Verlust oder aus tiefer Hingabe an deine Wahrheit?'
  },
  'drei-der-staebe': {
    badge: 'III · DREI DER STÄBE',
    archetype: 'Vision & Aufbruch',
    hook: 'Deine alte Welt ist zu klein geworden. Der Horizont ruft.',
    text: 'Die ersten Schritte sind getan, deine Schiffe sind auf See. Jetzt überkommt dich die Ungeduld des Wartens oder die Angst vor der Weite. Du brauchst Vertrauen in deine Vision und Gefährten, die mit dir blicken.',
    impuls: 'Welcher nächste große Horizont wartet darauf, von dir betreten zu werden?'
  },
  'die-welt': {
    badge: 'XXI · DIE WELT',
    archetype: 'Vollendung & Neuanfang',
    hook: 'Ein Kapitel schließt sich. Bereit für die nächste Stufe?',
    text: 'Du hast einen wichtigen Reifezyklus abgeschlossen. Die Lektion ist gelernt, doch das Verweilen in der alten Komfortzone hemmt dich. Wahre Integration bedeutet: Das Gelernte jetzt ins Handeln zu übersetzen.',
    impuls: 'Was musst du endgültig als vollendet anerkennen, um die nächste Spirale zu betreten?'
  }
};

function initOracle() {
  const deck = document.getElementById('oracle-deck');
  if (!deck) return;

  const oracleSection = document.getElementById('orakel');
  const stage = document.getElementById('oracle-stage');
  const cards = Array.from(deck.querySelectorAll('.flip-card'));
  const intro = document.getElementById('oracle-intro');
  const result = document.getElementById('oracle-result');
  const metaEl = document.getElementById('oracle-card-meta');
  const hookEl = document.getElementById('oracle-card-hook');
  const textEl = document.getElementById('oracle-card-text');
  const impulsEl = document.getElementById('oracle-card-impuls');
  const cardInput = document.getElementById('oracle-card-input');
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const STORAGE_KEY = 'sdz_drawn_card';

  const todayKey = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  };

  // Zum Testen: ?reset=1 in der URL löscht die gespeicherte Tageskarte
  const params = new URLSearchParams(window.location.search);
  if (params.get('reset') === '1') {
    try { localStorage.removeItem(STORAGE_KEY); } catch {}
    params.delete('reset');
    const clean = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + window.location.hash;
    window.history.replaceState({}, '', clean);
  }

  // Karten im Fächer mischen, damit jede Ziehung echt intuitiv ist
  function shuffleDeck() {
    const slots = cards.map((_, idx) => idx);
    for (let i = slots.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [slots[i], slots[j]] = [slots[j], slots[i]];
    }
    cards.forEach((cardEl, idx) => {
      cardEl.dataset.slot = String(slots[idx]);
    });
  }

  shuffleDeck();

  function revealCard(cardEl, { instant = false } = {}) {
    const cardId = cardEl.dataset.card;
    const data = ORACLE_CARDS[cardId];
    if (!data) return;

    // 1. Karte zentrieren & aufdecken (verbindlich für heute)
    cardEl.classList.add('is-flipped');
    cardEl.setAttribute('aria-label', `Deine gezogene Karte: ${data.badge}`);
    deck.classList.add('has-selected');
    if (stage) stage.classList.add('is-revealed');
    if (oracleSection) oracleSection.classList.add('is-revealed');

    // 2. Inhalte befüllen
    if (metaEl) metaEl.textContent = `${data.badge} · ${data.archetype}`;
    if (hookEl) hookEl.textContent = data.hook;
    if (textEl) textEl.textContent = data.text;
    if (impulsEl) impulsEl.textContent = data.impuls;
    if (cardInput) cardInput.value = cardId;

    // 3. Für heute speichern
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify({ cardId, date: todayKey() }));
    } catch {}

    // 4. Weicher Cross-Fade (oder sofort bei Wiederherstellung)
    const showResult = () => {
      if (intro) {
        intro.hidden = true;
        intro.classList.remove('is-leaving');
      }
      if (result) {
        result.hidden = false;
        if (instant || reduceMotion) {
          result.classList.add('is-visible');
        } else {
          requestAnimationFrame(() => {
            requestAnimationFrame(() => {
              result.classList.add('is-visible');
            });
          });
        }
      }
      if (!instant && window.innerWidth <= 960 && oracleSection) {
        window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
      }
    };

    if (!instant && intro && !reduceMotion) {
      intro.classList.add('is-leaving');
      setTimeout(showResult, 220);
    } else {
      showResult();
    }
  }

  function selectCard(cardEl) {
    if (deck.classList.contains('has-selected')) return;
    revealCard(cardEl);
  }

  // Falls heute bereits eine Karte gezogen wurde, diese direkt wiederherstellen
  try {
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
    if (saved && saved.date === todayKey() && saved.cardId) {
      const savedCard = cards.find(c => c.dataset.card === saved.cardId);
      if (savedCard) revealCard(savedCard, { instant: true });
    }
  } catch {}

  cards.forEach(cardEl => {
    cardEl.addEventListener('click', () => selectCard(cardEl));
    cardEl.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        selectCard(cardEl);
      }
    });
  });
}

document.addEventListener('DOMContentLoaded', initOracle);

/* ─── Countdown bis zum Start (Steinschleifer) ─── */
document.querySelectorAll('[data-countdown]').forEach((el) => {
  const target = new Date(el.dataset.countdown).getTime()
  const days = Math.ceil((target - Date.now()) / 86400000)
  if (days < 1) return
  el.querySelector('b').textContent = days === 1 ? '1 Tag' : days + ' Tage'
  el.hidden = false
})
