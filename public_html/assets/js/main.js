/* Carrera 7K Phaway Phu'spu — UI general
 * Countdown, navbar, menú móvil, FAQ, toast, scroll reveal, nav activa. */
(function () {
    'use strict';

    // -------------------------------------------------------------------------
    // Configuración pública (no sensible)
    // -------------------------------------------------------------------------
    const RACE_ISO = document.documentElement.dataset.raceDate || '2026-08-09T09:00:00-05:00';

    // -------------------------------------------------------------------------
    // Countdown
    // -------------------------------------------------------------------------
    const cdEls = {
        days:  document.getElementById('cd-days'),
        hours: document.getElementById('cd-hours'),
        mins:  document.getElementById('cd-mins'),
        secs:  document.getElementById('cd-secs')
    };
    const raceMs = new Date(RACE_ISO).getTime();

    function updateCountdown() {
        if (!cdEls.days) return;
        const diff = raceMs - Date.now();
        if (diff <= 0) {
            cdEls.days.textContent = '00';
            cdEls.hours.textContent = '00';
            cdEls.mins.textContent = '00';
            cdEls.secs.textContent = '00';
            return false;
        }
        const days  = Math.floor(diff / 86400000);
        const hours = Math.floor((diff % 86400000) / 3600000);
        const mins  = Math.floor((diff % 3600000) / 60000);
        const secs  = Math.floor((diff % 60000) / 1000);
        cdEls.days.textContent  = String(days).padStart(2, '0');
        cdEls.hours.textContent = String(hours).padStart(2, '0');
        cdEls.mins.textContent  = String(mins).padStart(2, '0');
        cdEls.secs.textContent  = String(secs).padStart(2, '0');
        return true;
    }
    if (updateCountdown() !== false) {
        setInterval(updateCountdown, 1000);
    }

    // -------------------------------------------------------------------------
    // Navbar scroll effect
    // -------------------------------------------------------------------------
    const navbar = document.getElementById('navbar');
    if (navbar) {
        const onScroll = () => {
            if (window.scrollY > 50) {
                navbar.classList.add('nav-scrolled');
                navbar.style.background = 'rgba(15, 23, 42, 0.95)';
                navbar.style.backdropFilter = 'blur(12px)';
                navbar.style.borderBottom = '1px solid rgba(255,255,255,0.05)';
            } else {
                navbar.classList.remove('nav-scrolled');
                navbar.style.background = 'transparent';
                navbar.style.backdropFilter = 'none';
                navbar.style.borderBottom = 'none';
            }
        };
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    // -------------------------------------------------------------------------
    // Menú móvil
    // -------------------------------------------------------------------------
    const menuBtn  = document.getElementById('mobile-menu-btn');
    const menu     = document.getElementById('mobile-menu');
    if (menuBtn && menu) {
        menuBtn.addEventListener('click', () => menu.classList.toggle('hidden'));
        menu.querySelectorAll('a').forEach(a => {
            a.addEventListener('click', () => menu.classList.add('hidden'));
        });
    }

    // -------------------------------------------------------------------------
    // FAQ toggle (accordion)
    // -------------------------------------------------------------------------
    document.querySelectorAll('.faq-item > button').forEach(btn => {
        btn.addEventListener('click', () => {
            const item = btn.parentElement;
            const wasOpen = item.classList.contains('open');
            document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
            if (!wasOpen) item.classList.add('open');
        });
    });

    // -------------------------------------------------------------------------
    // Scroll reveal
    // -------------------------------------------------------------------------
    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
        document.querySelectorAll('.scroll-reveal').forEach(el => revealObserver.observe(el));
    } else {
        document.querySelectorAll('.scroll-reveal').forEach(el => el.classList.add('visible'));
    }

    // -------------------------------------------------------------------------
    // Active nav link on scroll (throttled con requestAnimationFrame)
    // -------------------------------------------------------------------------
    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.nav-link');
    let scrollTicking = false;

    function updateActiveNav() {
        let current = '';
        sections.forEach(section => {
            if (window.scrollY >= section.offsetTop - 100) {
                current = section.id;
            }
        });
        navLinks.forEach(link => {
            link.classList.toggle('active', link.getAttribute('href') === `#${current}`);
        });
        scrollTicking = false;
    }
    window.addEventListener('scroll', () => {
        if (!scrollTicking) {
            requestAnimationFrame(updateActiveNav);
            scrollTicking = true;
        }
    }, { passive: true });

    // -------------------------------------------------------------------------
    // Toast helper expuesto globalmente
    // -------------------------------------------------------------------------
    window.showToast = function (message, type = 'success') {
        const toast = document.getElementById('toast');
        const msg = document.getElementById('toast-msg');
        if (!toast || !msg) return;
        msg.textContent = message;
        const colors = {
            success: 'rgba(22,163,74,0.95)',
            warning: 'rgba(234,179,8,0.95)',
            error:   'rgba(220,38,38,0.95)'
        };
        toast.style.background = colors[type] || colors.success;
        toast.classList.add('show');
        clearTimeout(toast._t);
        toast._t = setTimeout(() => toast.classList.remove('show'), 5000);
    };
})();
