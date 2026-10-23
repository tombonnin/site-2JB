/* Optional enhancements only: navigation, content and contact work without JS. */
(() => {
  'use strict';
  const menus = [...document.querySelectorAll('.mobile-menu, .services-menu')];
  const desktopHover = window.matchMedia('(min-width: 1001px) and (hover: hover) and (pointer: fine)');
  document.querySelectorAll('.desktop-nav .services-menu').forEach(menu => {
    const summary = menu.querySelector(':scope > summary');
    let closeTimer;
    let pointerType = 'mouse';
    const cancelClose = () => window.clearTimeout(closeTimer);
    menu.addEventListener('pointerenter', event => {
      if (!desktopHover.matches || event.pointerType === 'touch') return;
      cancelClose();
      menu.open = true;
    });
    menu.addEventListener('pointerleave', event => {
      if (!desktopHover.matches || event.pointerType === 'touch') return;
      cancelClose();
      closeTimer = window.setTimeout(() => {
        const focused = document.activeElement;
        if (menu.contains(focused) && focused?.matches(':focus-visible')) return;
        menu.open = false;
      }, 180);
    });
    summary.addEventListener('pointerdown', event => { pointerType = event.pointerType; });
    summary.addEventListener('click', event => {
      // Keep mouse clicks from closing an already hovered menu; retain native touch/keyboard activation.
      if (!desktopHover.matches || event.detail === 0 || pointerType === 'touch') return;
      event.preventDefault();
      cancelClose();
      menu.open = true;
    });
    desktopHover.addEventListener('change', () => {
      cancelClose();
      menu.open = false;
    });
  });
  document.addEventListener('click', event => {
    for (const menu of menus) {
      if (menu.open && !menu.contains(event.target)) menu.open = false;
    }
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    const activeMenu = document.activeElement?.closest('.mobile-menu') || document.activeElement?.closest('.services-menu');
    for (const menu of menus) menu.open = false;
    if (activeMenu && menus.includes(activeMenu)) activeMenu.querySelector(':scope > summary')?.focus();
  });
  document.addEventListener('focusin', event => {
    for (const menu of menus) {
      if (menu.open && !menu.contains(event.target)) menu.open = false;
    }
  });
  document.querySelectorAll('.mobile-menu a').forEach(link => {
    link.addEventListener('click', () => {
      document.querySelector('.mobile-menu')?.removeAttribute('open');
    });
  });
  function openLinkedQuestion() {
    let id;
    try { id = decodeURIComponent(location.hash.slice(1)); } catch { return; }
    if (!id) return;
    const target = document.getElementById(id);
    const item = target?.closest('.faq-item');
    if (item instanceof HTMLDetailsElement) {
      item.open = true;
      requestAnimationFrame(() => item.scrollIntoView({ block: 'start' }));
    }
  }
  window.addEventListener('hashchange', openLinkedQuestion);
  openLinkedQuestion();
  // Local file previews cannot submit to the hosting server. Keep the entered message intact.
  // In production the native POST and the server response own the confirmation page.
  if (location.protocol === 'file:') {
    document.querySelector('.contact-form')?.addEventListener('submit', event => {
      event.preventDefault();
      const note = document.querySelector('.form-local-note');
      if (note) {
        note.hidden = false;
        note.textContent = 'L’envoi du formulaire est disponible sur le site en ligne. Vous pouvez aussi me joindre au 06 31 74 05 60.';
      }
    });
  }
})();
