/* ===================================================
   SAND DER ZEIT – JavaScript v2
   - Layered particle system (parallax depth)
   - Scroll reveal (Intersection Observer)
   - Header scroll behaviour
   - 3D card tilt effect
   =================================================== */

document.addEventListener('DOMContentLoaded', () => {

    /* ─── 1. LAYERED PARTICLE / SAND CANVAS ─── */
    const canvas = document.getElementById('sand-canvas');
    const ctx = canvas.getContext('2d');
    let W, H;

    const LAYERS = [
        // depth 0 = back (small, slow, dim)
        { count: 60,  minSize: 0.3, maxSize: 0.8,  minSpeed: 0.08, maxSpeed: 0.2, goldRatio: 0.1, alpha: 0.15 },
        // depth 1 = mid
        { count: 40,  minSize: 0.8, maxSize: 1.5,  minSpeed: 0.2,  maxSpeed: 0.45, goldRatio: 0.25, alpha: 0.30 },
        // depth 2 = front (large, fast, bright)
        { count: 20,  minSize: 1.5, maxSize: 2.5,  minSpeed: 0.45, maxSpeed: 0.9, goldRatio: 0.5, alpha: 0.55 },
    ];

    // Static starfield
    const STARS = [];
    const STAR_COUNT = 180;
    function initStars() {
        STARS.length = 0;
        for (let i = 0; i < STAR_COUNT; i++) {
            STARS.push({
                x: Math.random() * W,
                y: Math.random() * H,
                r: Math.random() * 0.8 + 0.2,
                alpha: Math.random() * 0.35 + 0.05,
                twinkleSpeed: 0.005 + Math.random() * 0.008,
                twinkleOffset: Math.random() * Math.PI * 2,
                isGold: Math.random() < 0.08,
            });
        }
    }

    let particles = [];

    function resize() {
        W = canvas.width  = window.innerWidth;
        H = canvas.height = window.innerHeight;
        initStars();
    }
    window.addEventListener('resize', resize);
    resize();

    class Particle {
        constructor(layer) {
            this.layer = layer;
            this.reset(true);
        }
        reset(randomY = false) {
            const l = LAYERS[this.layer];
            this.x      = Math.random() * W;
            this.y      = randomY ? Math.random() * H : -10;
            this.size   = l.minSize + Math.random() * (l.maxSize - l.minSize);
            this.speedY = l.minSpeed + Math.random() * (l.maxSpeed - l.minSpeed);
            this.speedX = (Math.random() - 0.5) * 0.15;
            this.isGold = Math.random() < l.goldRatio;
            this.alpha  = l.alpha * (0.6 + Math.random() * 0.4);
            this.wobble = Math.random() * Math.PI * 2;
            this.wobbleSpeed = 0.005 + Math.random() * 0.01;
        }
        update() {
            this.wobble += this.wobbleSpeed;
            this.x += this.speedX + Math.sin(this.wobble) * 0.15;
            this.y += this.speedY;
            if (this.y > H + 5) this.reset();
            if (this.x > W + 5)  this.x = -5;
            if (this.x < -5)     this.x = W + 5;
        }
        draw() {
            ctx.save();
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
            if (this.isGold) {
                ctx.fillStyle = `rgba(212, 175, 55, ${this.alpha})`;
                ctx.shadowBlur  = this.size * 4;
                ctx.shadowColor = '#d4af37';
            } else {
                ctx.fillStyle   = `rgba(210, 200, 180, ${this.alpha * 0.6})`;
                ctx.shadowBlur  = 0;
            }
            ctx.fill();
            ctx.restore();
        }
    }

    function initParticles() {
        particles = [];
        LAYERS.forEach((_, i) => {
            for (let j = 0; j < LAYERS[i].count; j++) {
                particles.push(new Particle(i));
            }
        });
    }

    function drawStars(t) {
        STARS.forEach(s => {
            const twinkle = s.alpha + Math.sin(t * s.twinkleSpeed + s.twinkleOffset) * 0.12;
            ctx.beginPath();
            ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
            if (s.isGold) {
                ctx.fillStyle = `rgba(212, 175, 55, ${Math.max(0, Math.min(1, twinkle))})`;
            } else {
                ctx.fillStyle = `rgba(220, 210, 195, ${Math.max(0, Math.min(1, twinkle * 0.7))})`;
            }
            ctx.fill();
        });
    }

    let frame = 0;
    function animateCanvas() {
        ctx.clearRect(0, 0, W, H);
        frame++;
        drawStars(frame);
        particles.forEach(p => { p.update(); p.draw(); });
        requestAnimationFrame(animateCanvas);
    }

    initStars();
    initParticles();
    animateCanvas();


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
    const tiltCards = document.querySelectorAll('.tilt-card');

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
            hamburger.classList.toggle('active');
            navMenu.classList.toggle('active');
        });

        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                hamburger.classList.remove('active');
                navMenu.classList.remove('active');
            });
        });
    }

});
