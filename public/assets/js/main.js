/* Carrera 7K Phaway Phu'spu — UI general
 * Countdown, navbar, menú móvil, FAQ, toast, scroll reveal, nav activa. */
(function () {
    'use strict';

    // -------------------------------------------------------------------------
    // Configuración pública (no sensible)
    // -------------------------------------------------------------------------
    const RACE_ISO = document.documentElement.dataset.raceDate || '2026-08-09T09:00:00-05:00';

    // -------------------------------------------------------------------------
    // Countdown de Carrera
    // -------------------------------------------------------------------------
    const EVENT_DATE = new Date(RACE_ISO);
    const START_DATE = new Date('2026-07-01T00:00:00');

    const cdEls = {
        days:  document.getElementById('cdDays'),
        hours: document.getElementById('cdHours'),
        mins:  document.getElementById('cdMinutes'),
        secs:  document.getElementById('cdSeconds'),
        fill:  document.getElementById('raceFill'),
        runner: document.getElementById('raceRunner')
    };

    function updateCountdown() {
        if (!cdEls.days) return false;
        
        const now = new Date();
        const diff = EVENT_DATE - now;

        if (diff <= 0) {
            cdEls.days.textContent = '00';
            cdEls.hours.textContent = '00';
            cdEls.mins.textContent = '00';
            cdEls.secs.textContent = '00';
            if(cdEls.fill) cdEls.fill.style.width = '100%';
            if(cdEls.runner) cdEls.runner.style.left = '100%';
            return false;
        }

        const days = Math.floor(diff / 86400000);
        const hours = Math.floor((diff % 86400000) / 3600000);
        const mins = Math.floor((diff % 3600000) / 60000);
        const secs = Math.floor((diff % 60000) / 1000);

        cdEls.days.textContent = String(days).padStart(2, '0');
        cdEls.hours.textContent = String(hours).padStart(2, '0');
        cdEls.mins.textContent = String(mins).padStart(2, '0');
        cdEls.secs.textContent = String(secs).padStart(2, '0');

        if(cdEls.fill && cdEls.runner) {
            const totalSpan = EVENT_DATE - START_DATE;
            const elapsed = now - START_DATE;
            const progress = Math.min(100, Math.max(0, (elapsed / totalSpan) * 100));
            cdEls.fill.style.width = progress + '%';
            cdEls.runner.style.left = progress + '%';
        }

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
            } else {
                navbar.classList.remove('nav-scrolled');
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
    // SPA TAB NAVIGATION
    // -------------------------------------------------------------------------
    const navLinks = document.querySelectorAll('.nav-link');
    const tabContents = document.querySelectorAll('.tab-content');
    
    const tabMapping = {
        'hero': ['hero', 'hero-countdown'],
        'evento': ['evento'],
        'categorias': ['categorias'],
        'recorrido': ['recorrido', 'seguridad'],
        'cronograma': ['cronograma'],
        'kits': ['kits'],
        'inscripcion': ['inscripcion', 'bases']
    };

    function showTab(targetId, scrollToTop = true) {
        navLinks.forEach(nav => nav.classList.remove('active'));
        document.querySelectorAll(`.nav-link[href="#${targetId}"]`).forEach(nav => nav.classList.add('active'));

        const sectionsToShow = tabMapping[targetId] || [targetId];
        tabContents.forEach(section => {
            if (sectionsToShow.includes(section.id)) {
                section.classList.add('active-tab');
            } else {
                section.classList.remove('active-tab');
            }
        });

        const mobileMenu = document.getElementById('mobile-menu');
        if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
            mobileMenu.classList.add('hidden');
            document.body.style.overflow = '';
            const menuBtnIcon = document.querySelector('#mobile-menu-btn iconify-icon');
            if(menuBtnIcon) menuBtnIcon.setAttribute('icon', 'lucide:menu');
        }

        if (scrollToTop) {
            window.scrollTo({top: 0});
        }

        setTimeout(() => {
            window.dispatchEvent(new Event('resize'));
        }, 50);
    }

    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            const targetAttr = link.getAttribute('href');
            if(!targetAttr || !targetAttr.startsWith('#')) return;
            
            e.preventDefault();
            const targetId = targetAttr.replace('#', '');
            
            // Update active link classes
            navLinks.forEach(nav => nav.classList.remove('active'));
            document.querySelectorAll(`.nav-link[href="#${targetId}"]`).forEach(nav => nav.classList.add('active'));
            
            // Get sections to show
            const sectionsToShow = tabMapping[targetId] || [targetId];
            
            // Hide all, show targeted
            tabContents.forEach(section => {
                if (sectionsToShow.includes(section.id)) {
                    section.classList.add('active-tab');
                } else {
                    section.classList.remove('active-tab');
                }
            });
            
            // Close mobile menu if open
            const mobileMenu = document.getElementById('mobile-menu');
            if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
                mobileMenu.classList.add('hidden');
                document.body.style.overflow = '';
                const menuBtnIcon = document.querySelector('#mobile-menu-btn iconify-icon');
                if(menuBtnIcon) menuBtnIcon.setAttribute('icon', 'lucide:menu');
            }
            
            // Scroll to top
            window.scrollTo({top: 0});
            
            // Fix para componentes que requieren recalcular su tamaño (ej. Leaflet Maps)
            // Se ejecuta ligeramente después para asegurar que el display: block ya aplicó.
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
            }, 50);
        });
    });

    function applyHashTab(scrollToTop = false) {
        const targetId = window.location.hash.replace('#', '');
        if (targetId && tabMapping[targetId]) {
            showTab(targetId, scrollToTop);
        }
    }

    applyHashTab(false);
    window.addEventListener('load', () => applyHashTab(false));
    window.addEventListener('hashchange', () => applyHashTab(true));

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
