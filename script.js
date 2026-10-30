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

/* ─── Newsletter (eigenes Formular, Anmeldung läuft über /newsletter.php zu Mailjet) ─── */
const nlForm = document.querySelector('.nl-form')
if (nlForm) {
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
      const res = await fetch('/newsletter.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, consent: true, website: nlForm.website.value }),
      })
      const data = await res.json().catch(() => ({}))
      if (!res.ok || !data.ok) throw new Error(data.error || 'fail')
      nlForm.classList.add('is-done')
      say('Fast geschafft. Schau in dein Postfach und bestätige deine Anmeldung.', 'ok')
    } catch (err) {
      say('Das hat leider nicht geklappt. Versuch es bitte gleich noch einmal oder schreib uns an info@sandderzeit.de.', 'error')
    } finally {
      btn.disabled = false
    }
  })
}
