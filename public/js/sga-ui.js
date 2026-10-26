(() => {
    const sidebar = document.getElementById('main-navigation');
    const toggle = document.getElementById('nav-toggle');
    if (!sidebar || !toggle) return;

    const mobile = window.matchMedia('(max-width: 960px)');
    const backdrop = document.querySelector('.nav-backdrop');
    document.documentElement.classList.add('ui-ready');
    toggle.hidden = false;

    const setOpen = (open, restoreFocus = false) => {
        open = mobile.matches && open;
        document.body.classList.toggle('nav-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
        sidebar.inert = mobile.matches && !open;
        backdrop.hidden = !open;
        if (open) sidebar.querySelector('button, a')?.focus();
        else if (restoreFocus) toggle.focus();
    };

    toggle.addEventListener('click', () => setOpen(!document.body.classList.contains('nav-open')));
    document.querySelectorAll('[data-nav-close]').forEach(button => button.addEventListener('click', () => setOpen(false, true)));
    sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', event => {
        if (!mobile.matches || !document.body.classList.contains('nav-open')) return;
        if (event.key === 'Escape') { setOpen(false, true); return; }
        if (event.key !== 'Tab') return;
        const elements = [...sidebar.querySelectorAll('a, button:not([disabled])')].filter(element => element.getClientRects().length);
        const first = elements[0], last = elements[elements.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    });
    mobile.addEventListener('change', () => setOpen(false));
    setOpen(false);
})();
