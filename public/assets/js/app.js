(() => {
  'use strict';

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const focusableSel = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

  /* Header shadow once the page scrolls ------------------------------- */
  const header = $('[data-header]');
  if (header && 'IntersectionObserver' in window) {
    const sentinel = document.createElement('div');
    sentinel.setAttribute('aria-hidden', 'true');
    sentinel.style.cssText = 'position:absolute;top:0;left:0;width:1px;height:8px;pointer-events:none';
    document.body.prepend(sentinel);
    new IntersectionObserver(([entry]) => header.classList.toggle('is-scrolled', !entry.isIntersecting)).observe(sentinel);
  }

  /* Desktop mega menus ------------------------------------------------ */
  const menuItems = $$('[data-menu]');
  const hoverCapable = window.matchMedia('(hover: hover) and (pointer: fine)');
  let hoverTimer;

  const setMenu = (item, open) => {
    const trigger = $('.nav-trigger', item);
    const panel = document.getElementById(trigger.getAttribute('aria-controls'));
    trigger.setAttribute('aria-expanded', String(open));
    panel.hidden = !open;
  };
  const closeMenus = (except) => menuItems.forEach((item) => item !== except && setMenu(item, false));

  menuItems.forEach((item) => {
    const trigger = $('.nav-trigger', item);
    trigger.addEventListener('click', () => {
      const open = trigger.getAttribute('aria-expanded') !== 'true';
      closeMenus(item);
      setMenu(item, open);
    });
    item.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && trigger.getAttribute('aria-expanded') === 'true') {
        setMenu(item, false);
        trigger.focus();
      }
    });
    item.addEventListener('focusout', (e) => {
      if (!item.contains(e.relatedTarget)) setMenu(item, false);
    });
    item.addEventListener('mouseenter', () => {
      if (!hoverCapable.matches) return;
      clearTimeout(hoverTimer);
      hoverTimer = setTimeout(() => { closeMenus(item); setMenu(item, true); }, 90);
    });
    item.addEventListener('mouseleave', () => {
      if (!hoverCapable.matches) return;
      clearTimeout(hoverTimer);
      hoverTimer = setTimeout(() => setMenu(item, false), 160);
    });
  });
  document.addEventListener('click', (e) => {
    if (!e.target.closest('[data-menu]')) closeMenus();
  });

  /* Mobile menu ------------------------------------------------------- */
  const mobileMenu = $('[data-mobile-menu]');
  const openBtn = $('[data-menu-open]');
  if (mobileMenu && openBtn) {
    const closeBtn = $('[data-menu-close]', mobileMenu);
    const open = () => {
      mobileMenu.hidden = false;
      openBtn.setAttribute('aria-expanded', 'true');
      document.body.classList.add('menu-open');
      closeBtn.focus();
    };
    const close = (restoreFocus = true) => {
      mobileMenu.hidden = true;
      openBtn.setAttribute('aria-expanded', 'false');
      document.body.classList.remove('menu-open');
      if (restoreFocus) openBtn.focus();
    };
    openBtn.addEventListener('click', open);
    closeBtn.addEventListener('click', () => close());
    mobileMenu.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { e.preventDefault(); close(); return; }
      if (e.key !== 'Tab') return;
      const items = $$(focusableSel, mobileMenu).filter((el) => el.offsetParent !== null);
      const first = items[0];
      const last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
    $$('a', mobileMenu).forEach((a) => a.addEventListener('click', () => close(false)));
    $$('.m-trigger', mobileMenu).forEach((btn) => {
      btn.addEventListener('click', () => {
        const expanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', String(!expanded));
        document.getElementById(btn.getAttribute('aria-controls')).hidden = expanded;
      });
    });
    window.matchMedia('(min-width: 1101px)').addEventListener('change', (mq) => {
      if (mq.matches && !mobileMenu.hidden) close(false);
    });
  }

  /* Forms: client validation + progressive fetch submit --------------- */
  const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  const fieldError = (form, field) => document.getElementById(`${field.id}-error`);

  const showFieldError = (form, field, message) => {
    const el = fieldError(form, field);
    if (message) field.setAttribute('aria-invalid', 'true');
    else field.removeAttribute('aria-invalid');
    if (el) { el.textContent = message || ''; el.hidden = !message; }
  };

  const validateField = (field) => {
    const v = field.value.trim();
    const label = field.dataset.label || 'This field';
    if (field.required && !v) {
      return field.type === 'email' ? 'Enter your email address.' : field.tagName === 'SELECT' ? 'Choose an option.' : `${label} is required.`;
    }
    if (v && field.type === 'email' && (!EMAIL_RE.test(v) || v.length > 254)) return 'Enter a valid email address, like name@example.com.';
    const min = Number(field.getAttribute('minlength') || 0);
    if (v && min && v.length < min) return `${label} needs at least ${min} characters.`;
    return '';
  };

  const setStatus = (form, status, message) => {
    const box = $('[data-form-status]', form);
    if (!box) return;
    box.className = `form-status is-${status}`;
    box.textContent = '';
    const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    icon.setAttribute('viewBox', '0 0 256 256');
    icon.setAttribute('class', 'icon');
    icon.setAttribute('aria-hidden', 'true');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('fill', 'currentColor');
    path.setAttribute('d', status === 'success'
      ? 'M173.66,98.34a8,8,0,0,1,0,11.32l-56,56a8,8,0,0,1-11.32,0l-24-24a8,8,0,0,1,11.32-11.32L112,148.69l50.34-50.35A8,8,0,0,1,173.66,98.34ZM232,128A104,104,0,1,1,128,24,104.11,104.11,0,0,1,232,128Zm-16,0a88,88,0,1,0-88,88A88.1,88.1,0,0,0,216,128Z'
      : 'M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm-8-80V80a8,8,0,0,1,16,0v56a8,8,0,0,1-16,0Zm20,36a12,12,0,1,1-12-12A12,12,0,0,1,140,172Z');
    icon.appendChild(path);
    const text = document.createElement('span');
    text.textContent = message;
    box.append(icon, text);
  };

  $$('form[data-ajax-form]').forEach((form) => {
    const fields = $$('input:not([type="hidden"]):not([name="awt_hp"]), select, textarea', form);
    const submit = $('button[type="submit"]', form);

    fields.forEach((field) => {
      field.addEventListener('blur', () => {
        if (field.value.trim() || field.getAttribute('aria-invalid')) showFieldError(form, field, validateField(field));
      });
      field.addEventListener('input', () => {
        if (field.getAttribute('aria-invalid')) showFieldError(form, field, validateField(field));
      });
    });

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const hp = form.elements.awt_hp;
      if (hp) hp.value = '';
      let firstInvalid = null;
      fields.forEach((field) => {
        const msg = validateField(field);
        showFieldError(form, field, msg);
        if (msg && !firstInvalid) firstInvalid = field;
      });
      if (firstInvalid) {
        setStatus(form, 'error', 'Please check the highlighted field' + (fields.filter((f) => f.getAttribute('aria-invalid')).length > 1 ? 's.' : '.'));
        firstInvalid.focus();
        return;
      }

      submit.setAttribute('aria-busy', 'true');
      submit.disabled = true;
      try {
        const res = await fetch(form.getAttribute('action'), {
          method: 'POST',
          body: new FormData(form),
          headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' },
          credentials: 'same-origin',
        });
        const data = await res.json();
        Object.entries(data.errors || {}).forEach(([name, message]) => {
          const field = form.elements[name];
          if (field) showFieldError(form, field, message);
        });
        setStatus(form, data.status === 'success' ? 'success' : 'error', data.message);
        if (data.status === 'success' && data.redirect) {
          window.location.assign(data.redirect);
          return;
        }
        if (data.status === 'success') {
          form.reset();
          fields.forEach((f) => showFieldError(form, f, ''));
        } else {
          const bad = fields.find((f) => f.getAttribute('aria-invalid'));
          if (bad) bad.focus();
        }
      } catch (err) {
        setStatus(form, 'error', "We couldn't reach the server. Check your connection and try again.");
      } finally {
        submit.removeAttribute('aria-busy');
        submit.disabled = false;
      }
    });
  });

  /* Hero tiles lean toward the cursor --------------------------------- */
  const tileField = $('.hero-tiles');
  if (tileField && window.matchMedia('(hover: hover) and (pointer: fine)').matches && !reduceMotion.matches) {
    const hero = tileField.parentElement;
    const RANGE = 380; const PULL = 35; const SOFT = 40; const EASE_MS = 900;
    const tiles = $$('.tile', tileField).map((el) => ({ el, x: 0, y: 0, tx: 0, ty: 0 }));
    let pointer = null;
    let frame = 0;
    let last = 0;

    const aim = () => {
      const box = tileField.getBoundingClientRect();
      tiles.forEach((t) => {
        t.tx = 0; t.ty = 0;
        if (!pointer) return;
        const dx = pointer.x - (box.left + t.el.offsetLeft + t.el.offsetWidth / 2);
        const dy = pointer.y - (box.top + t.el.offsetTop + t.el.offsetHeight / 2);
        const d = Math.hypot(dx, dy);
        if (d < 1 || d > RANGE) return;
        const scale = (PULL * Math.tanh(d / SOFT)) / d;
        t.tx = dx * scale; t.ty = dy * scale;
      });
    };
    const step = (now) => {
      const k = 1 - Math.exp(-Math.max(0, now - last) / EASE_MS);
      last = now;
      let moving = false;
      tiles.forEach((t) => {
        t.x += (t.tx - t.x) * k;
        t.y += (t.ty - t.y) * k;
        if (Math.abs(t.tx - t.x) > 0.05 || Math.abs(t.ty - t.y) > 0.05) moving = true;
        else { t.x = t.tx; t.y = t.ty; }
        t.el.style.translate = `${t.x.toFixed(2)}px ${t.y.toFixed(2)}px`;
      });
      frame = moving ? requestAnimationFrame(step) : 0;
    };
    const kick = () => {
      aim();
      if (!frame) { last = performance.now(); frame = requestAnimationFrame(step); }
    };
    hero.addEventListener('pointermove', (e) => {
      if (e.pointerType !== 'mouse') return;
      pointer = { x: e.clientX, y: e.clientY };
      kick();
    });
    hero.addEventListener('pointerleave', () => { pointer = null; kick(); });
  }

  /* Hero platform selector -------------------------------------------- */
  $$('[data-hero-demo]').forEach((stage) => {
    $$('[data-platform]', stage).forEach((btn) => {
      btn.addEventListener('click', () => {
        const on = btn.getAttribute('aria-pressed') !== 'true';
        btn.setAttribute('aria-pressed', String(on));
        $$(`.mini-post[data-channel="${btn.dataset.platform}"]`, stage).forEach((p) => p.classList.toggle('is-hidden', !on));
      });
    });
  });

  /* Calendar demo ----------------------------------------------------- */
  $$('[data-calendar]').forEach((cal) => {
    const range = $('[data-range]', cal);
    $$('[data-view]', cal).forEach((btn) => {
      btn.addEventListener('click', () => {
        $$('[data-view]', cal).forEach((b) => b.setAttribute('aria-pressed', String(b === btn)));
        $$('[data-panel]', cal).forEach((p) => { p.hidden = p.dataset.panel !== btn.dataset.view; });
        range.textContent = btn.dataset.view === 'month' ? range.dataset.monthLabel : range.dataset.weekLabel;
      });
    });

    $$('[data-filter]', cal).forEach((chip) => {
      chip.addEventListener('click', () => {
        const on = chip.getAttribute('aria-pressed') !== 'true';
        chip.setAttribute('aria-pressed', String(on));
        $$(`[data-channel="${chip.dataset.filter}"]`, cal).forEach((el) => {
          if (el.matches('li, .dot')) el.hidden = !on;
        });
      });
    });

    const pv = (k) => $(`[data-pv-${k}]`, cal);
    $$('[data-post]', cal).forEach((card) => {
      card.addEventListener('click', () => {
        $$('[data-post]', cal).forEach((c) => c.setAttribute('aria-pressed', String(c === card)));
        const d = card.dataset;
        pv('title').textContent = d.title;
        pv('text').textContent = d.text;
        pv('when').textContent = d.when;
        pv('channel').textContent = d.channelName;
        const status = pv('status');
        status.textContent = d.status === 'draft' ? 'Draft' : 'Scheduled';
        status.className = `state state--${d.status}`;
        const img = pv('image');
        if (d.image) { img.src = d.image; img.hidden = false; } else { img.hidden = true; img.removeAttribute('src'); }
      });
    });
  });

  /* Idea board demo --------------------------------------------------- */
  $$('[data-board]').forEach((board) => {
    const cols = $$('[data-col]', board);
    const live = $('[data-board-live]', board);
    let dragging = null;

    const refresh = () => {
      cols.forEach((col, i) => {
        $('[data-count]', col).textContent = $$('[data-card]', col).length;
        $$('[data-card]', col).forEach((card) => {
          $('[data-move="-1"]', card).disabled = i === 0;
          $('[data-move="1"]', card).disabled = i === cols.length - 1;
          const next = cols[i + 1]; const prev = cols[i - 1];
          $('[data-move="1"]', card).setAttribute('aria-label', next ? `Move to ${next.dataset.label}` : 'Already in the last stage');
          $('[data-move="-1"]', card).setAttribute('aria-label', prev ? `Move back to ${prev.dataset.label}` : 'Already in the first stage');
        });
      });
    };
    const moveCard = (card, toCol, announce = true) => {
      $('[data-list]', toCol).prepend(card);
      card.classList.remove('is-new');
      void card.offsetWidth;
      card.classList.add('is-new');
      refresh();
      if (announce) live.textContent = `Moved "${$('.idea-title', card).textContent}" to ${toCol.dataset.label}.`;
    };

    board.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-move]');
      if (!btn) return;
      const card = btn.closest('[data-card]');
      const idx = cols.indexOf(card.closest('[data-col]')) + Number(btn.dataset.move);
      if (cols[idx]) {
        moveCard(card, cols[idx]);
        const same = $(`[data-move="${btn.dataset.move}"]`, card);
        (same.disabled ? $('[data-move]:not(:disabled)', card) : same)?.focus();
      }
    });

    board.addEventListener('dragstart', (e) => {
      dragging = e.target.closest('[data-card]');
      if (!dragging) return;
      dragging.classList.add('is-dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', '');
    });
    board.addEventListener('dragend', () => {
      dragging?.classList.remove('is-dragging');
      cols.forEach((c) => c.classList.remove('is-over'));
      dragging = null;
    });
    cols.forEach((col) => {
      col.addEventListener('dragover', (e) => { if (dragging) { e.preventDefault(); col.classList.add('is-over'); } });
      col.addEventListener('dragleave', (e) => { if (!col.contains(e.relatedTarget)) col.classList.remove('is-over'); });
      col.addEventListener('drop', (e) => {
        e.preventDefault();
        col.classList.remove('is-over');
        if (dragging && dragging.closest('[data-col]') !== col) moveCard(dragging, col);
      });
    });

    const addForm = $('[data-board-add]', board);
    addForm?.addEventListener('submit', (e) => {
      e.preventDefault();
      const input = $('input', addForm);
      const title = input.value.trim();
      if (!title) { input.focus(); return; }
      const template = $('[data-card]', board);
      const card = template.cloneNode(true);
      $('img', card)?.remove();
      $('.idea-title', card).textContent = title;
      $('.idea-note', card).textContent = 'Added just now. Demo only, not saved.';
      const tags = $('.idea-tags', card);
      tags.textContent = '';
      const tag = document.createElement('span');
      tag.className = 'tag tag--plain';
      tag.textContent = 'New';
      tags.appendChild(tag);
      card.classList.remove('is-dragging');
      moveCard(card, cols[0], false);
      live.textContent = `Added "${title}" to Ideas.`;
      input.value = '';
    });
    refresh();
  });

  /* Inbox demo -------------------------------------------------------- */
  $$('[data-inbox]').forEach((inbox) => {
    const data = JSON.parse($('[data-inbox-data]', inbox).textContent);
    const msgs = $('[data-msgs]', inbox);
    const unreadBadge = $('[data-unread-count]', inbox);
    const filter = $('[data-inbox-filter]', inbox);
    const unreadOnly = $('[data-inbox-unread]', inbox);
    const composer = $('[data-composer]', inbox);
    let current = $('[data-convo][aria-pressed="true"]', inbox)?.dataset.convo;
    const demoReplies = {};

    const updateUnread = () => {
      unreadBadge.textContent = `${$$('.convo.is-unread', inbox).length} unread`;
    };
    const renderThread = (id) => {
      const c = data[id];
      $('[data-th-name]', inbox).textContent = c.name;
      $('[data-th-initials]', inbox).textContent = c.initials;
      $('[data-th-channel]', inbox).textContent = c.channel;
      $('[data-th-post]', inbox).textContent = c.post;
      msgs.textContent = '';
      c.messages.forEach((m) => {
        const p = document.createElement('p');
        p.className = `msg msg--${m.from}`;
        p.textContent = m.text;
        msgs.appendChild(p);
      });
      (demoReplies[id] || []).forEach((text) => appendDemoReply(text));
      msgs.scrollTop = msgs.scrollHeight;
    };
    const appendDemoReply = (text) => {
      const p = document.createElement('p');
      p.className = 'msg msg--demo';
      p.textContent = text;
      const note = document.createElement('span');
      note.className = 'msg-note';
      note.textContent = 'Demo reply. Not sent.';
      msgs.append(p, note);
    };

    $$('[data-convo]', inbox).forEach((btn) => {
      btn.addEventListener('click', () => {
        $$('[data-convo]', inbox).forEach((b) => b.setAttribute('aria-pressed', String(b === btn)));
        btn.classList.remove('is-unread');
        $('.unread-dot', btn)?.remove();
        current = btn.dataset.convo;
        renderThread(current);
        updateUnread();
        applyFilter();
      });
    });

    const applyFilter = () => {
      $$('[data-convo-list] > li', inbox).forEach((li) => {
        const btn = $('[data-convo]', li);
        const channelOk = filter.value === 'all' || li.dataset.channel === filter.value;
        const unreadOk = !unreadOnly.checked || btn.classList.contains('is-unread') || btn.dataset.convo === current;
        li.hidden = !(channelOk && unreadOk);
      });
    };
    filter.addEventListener('change', applyFilter);
    unreadOnly.addEventListener('change', applyFilter);

    composer.addEventListener('submit', (e) => {
      e.preventDefault();
      const ta = $('textarea', composer);
      const text = ta.value.trim();
      if (!text || !current) { ta.focus(); return; }
      (demoReplies[current] ||= []).push(text);
      appendDemoReply(text);
      msgs.scrollTop = msgs.scrollHeight;
      ta.value = '';
    });
  });

  /* Analytics range --------------------------------------------------- */
  $$('[data-analytics]').forEach((an) => {
    $$('[data-range-btn]', an).forEach((btn) => {
      btn.addEventListener('click', () => {
        $$('[data-range-btn]', an).forEach((b) => b.setAttribute('aria-pressed', String(b === btn)));
        $$('[data-range-panel]', an).forEach((p) => { p.hidden = p.dataset.rangePanel !== btn.dataset.rangeBtn; });
      });
    });
  });

  /* AI scripted conversation ------------------------------------------ */
  $$('[data-ai]').forEach((ai) => {
    const flows = JSON.parse($('[data-ai-data]', ai).textContent);
    const log = $('[data-ai-log]', ai);
    const buttons = $$('[data-ai-flow]', ai);
    const wait = (ms) => new Promise((r) => setTimeout(r, ms));

    const bubble = (role) => {
      const wrap = document.createElement('div');
      wrap.className = `ai-msg ai-msg--${role}`;
      if (role === 'bot') {
        const av = document.createElement('span');
        av.className = 'ai-avatar';
        av.setAttribute('aria-hidden', 'true');
        av.innerHTML = '<svg class="icon" viewBox="0 0 256 256" fill="currentColor"><path d="M197.58,129.06l-51.61-19-19-51.65a15.92,15.92,0,0,0-29.88,0L78.07,110l-51.65,19a15.92,15.92,0,0,0,0,29.88L78,178l19,51.62a15.92,15.92,0,0,0,29.88,0l19-51.61,51.65-19a15.92,15.92,0,0,0,0-29.88Z"/></svg>';
        wrap.appendChild(av);
      }
      const b = document.createElement('div');
      b.className = 'ai-bubble';
      wrap.appendChild(b);
      log.appendChild(wrap);
      return b;
    };

    const typeInto = async (el, text) => {
      if (reduceMotion.matches) { el.textContent = text; return; }
      const caret = document.createElement('span');
      caret.className = 'ai-caret';
      for (let i = 0; i < text.length; i += 3) {
        el.textContent = text.slice(0, i + 3);
        el.appendChild(caret);
        log.scrollTop = log.scrollHeight;
        await wait(14);
      }
      caret.remove();
    };

    buttons.forEach((btn) => {
      btn.addEventListener('click', async () => {
        const flow = flows[btn.dataset.aiFlow];
        buttons.forEach((b) => { b.disabled = true; b.setAttribute('aria-pressed', String(b === btn)); });
        log.setAttribute('aria-busy', 'true');
        const u = bubble('user');
        const up = document.createElement('p');
        up.textContent = flow.prompt;
        u.appendChild(up);
        log.scrollTop = log.scrollHeight;
        if (!reduceMotion.matches) await wait(450);
        const b = bubble('bot');
        const tag = document.createElement('span');
        tag.className = 'ai-tag';
        tag.textContent = 'Scripted example';
        b.appendChild(tag);
        for (const line of flow.reply) {
          const p = document.createElement('p');
          b.appendChild(p);
          await typeInto(p, line);
        }
        log.scrollTop = log.scrollHeight;
        log.removeAttribute('aria-busy');
        buttons.forEach((x) => { x.disabled = false; });
      });
    });
  });

  /* Pricing: mirrors app/Pricing.php ---------------------------------- */
  $$('[data-pricing]').forEach((root) => {
    const cfg = JSON.parse($('[data-pricing-config]', root).textContent);
    const input = $('[data-channels]', root);
    const live = $('[data-pricing-live]', root);
    const stepBtns = $$('[data-step]', root);
    let liveTimer;

    const money = (n) => {
      const v = Math.round(n * 100) / 100;
      const whole = Number.isInteger(v);
      return cfg.currency + v.toLocaleString('en-US', { minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: whole ? 0 : 2 });
    };
    const monthlyTotal = (plan, n) => {
      const standard = Math.min(n, cfg.volumeFrom - 1);
      const extra = Math.max(0, n - (cfg.volumeFrom - 1));
      return standard * cfg.rates[plan] + extra * cfg.volumeRate;
    };
    const clamp = (v) => Math.max(1, Math.min(cfg.max, Number.parseInt(v, 10) || 1));
    const billing = () => ($('[data-billing]:checked', root) || {}).value || 'yearly';

    const update = (announce = true) => {
      const n = clamp(input.value);
      const cycle = billing();
      const label = `${n} ${n === 1 ? 'channel' : 'channels'}`;
      const summary = [];
      Object.keys(cfg.rates).forEach((plan) => {
        if (!cfg.rates[plan]) return;
        const monthly = monthlyTotal(plan, n);
        const price = cycle === 'monthly' ? monthly : (monthly * cfg.yearlyMonths) / 12;
        const note = cycle === 'monthly'
          ? `${label} · Billed monthly`
          : `${label} · ${money(monthly * cfg.yearlyMonths)} billed yearly (save ${12 - cfg.yearlyMonths} months)`;
        $$(`[data-plan-price="${plan}"]`, root).forEach((el) => { el.textContent = money(price); });
        $$(`[data-plan-note="${plan}"]`, root).forEach((el) => { el.textContent = note; });
        summary.push(`${cfg.names[plan]} ${money(price)} per month`);
      });
      stepBtns.forEach((b) => { b.disabled = Number(b.dataset.step) < 0 ? n <= 1 : n >= cfg.max; });
      $$('[data-plan-cta]', root).forEach((a) => {
        const url = new URL(a.href, location.href);
        url.searchParams.set('channels', n);
        url.searchParams.set('billing', cycle);
        a.href = url.pathname + url.search + url.hash;
      });
      $$('[data-plan-billing]').forEach((el) => { el.value = cycle; });
      $$('[data-plan-channels]').forEach((el) => { el.value = n; });
      if (announce) {
        clearTimeout(liveTimer);
        liveTimer = setTimeout(() => { live.textContent = `${label}, billed ${cycle}: ${summary.join(', ')}.`; }, 400);
      }
    };

    stepBtns.forEach((btn) => {
      btn.addEventListener('click', () => {
        input.value = clamp(clamp(input.value) + Number(btn.dataset.step));
        update();
        if (btn.disabled) input.focus();
      });
    });
    input.addEventListener('input', () => { if (input.value !== '') update(); });
    input.addEventListener('change', () => { input.value = clamp(input.value); update(); });
    $$('[data-billing]', root).forEach((r) => r.addEventListener('change', () => update()));
    $('[data-pricing-controls]', root).addEventListener('submit', (e) => { e.preventDefault(); input.value = clamp(input.value); update(); });

    $$('[data-plan-cta]', root).forEach((a) => {
      a.addEventListener('click', (e) => {
        const select = $('#join [data-plan-select]');
        if (!select) return;
        e.preventDefault();
        select.value = a.dataset.planCta;
        const join = document.getElementById('join');
        join.scrollIntoView({ behavior: reduceMotion.matches ? 'auto' : 'smooth', block: 'center' });
        $('input[type="email"]', join).focus({ preventScroll: true });
      });
    });

    const mobile = window.matchMedia('(max-width: 760px)');
    const toggles = $$('[data-cmp-toggle]', root);
    const setGroup = (btn, open) => {
      btn.setAttribute('aria-expanded', String(open));
      document.getElementById(btn.getAttribute('aria-controls')).hidden = !open;
    };
    toggles.forEach((btn) => btn.addEventListener('click', () => setGroup(btn, btn.getAttribute('aria-expanded') !== 'true')));
    if (mobile.matches) toggles.forEach((btn, i) => setGroup(btn, i === 0));

    update(false);
  });

  /* Info tooltips ------------------------------------------------------ */
  const tips = $$('.tip');
  tips.forEach((tip) => {
    const btn = $('.tip-btn', tip);
    btn.addEventListener('click', () => { tip.classList.remove('is-dismissed'); tip.classList.toggle('is-open'); });
    btn.addEventListener('focus', () => tip.classList.remove('is-dismissed'));
    btn.addEventListener('blur', () => tip.classList.remove('is-open'));
    tip.addEventListener('mouseleave', () => tip.classList.remove('is-dismissed', 'is-open'));
  });
  if (tips.length) {
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape') return;
      tips.forEach((tip) => { tip.classList.remove('is-open'); tip.classList.add('is-dismissed'); });
    });
  }

  /* Account: password field ------------------------------------------- */
  $$('[data-password-toggle]').forEach((btn) => {
    const input = document.getElementById(btn.getAttribute('aria-controls'));
    const label = $('.sr-only', btn);
    btn.addEventListener('click', () => {
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', String(show));
      label.textContent = show ? 'Hide password' : 'Show password';
    });
  });

  $$('[data-copy]').forEach((btn) => {
    const live = $('.sr-only', btn);
    btn.addEventListener('click', async () => {
      const source = document.getElementById(btn.getAttribute('data-copy'));
      const text = (source?.getAttribute('data-copy-text') || source?.textContent || '').trim();
      if (!text) return;
      try {
        if (navigator.clipboard?.writeText) {
          await navigator.clipboard.writeText(text);
        } else {
          const area = document.createElement('textarea');
          area.value = text;
          area.setAttribute('readonly', '');
          area.style.position = 'fixed';
          area.style.left = '-9999px';
          document.body.appendChild(area);
          area.select();
          document.execCommand('copy');
          area.remove();
        }
        if (live) {
          const prev = live.dataset.prev || live.textContent;
          live.dataset.prev = prev;
          live.textContent = ' Copied';
          setTimeout(() => { live.textContent = prev; }, 1600);
        }
      } catch (err) { /* the text stays selectable */ }
    });
  });

  $$('[data-password-rules]').forEach((input) => {
    const box = $('[data-rules]', input.form);
    const status = $('[data-rules-status]', box);
    const rules = {
      length: (v) => v.length >= 8,
      upper: (v) => /\p{Lu}/u.test(v),
      symbol: (v) => /[\p{N}\p{P}\p{S}]/u.test(v),
    };
    let timer = 0;
    const check = () => {
      let met = 0;
      Object.entries(rules).forEach(([name, test]) => {
        const ok = test(input.value);
        if (ok) met += 1;
        const row = $(`[data-rule="${name}"]`, box);
        row.classList.toggle('is-met', ok);
        $('[data-rule-state]', row).textContent = ok ? ', met' : ', not met';
      });
      clearTimeout(timer);
      timer = setTimeout(() => { status.textContent = `${met} of 3 password requirements met`; }, 600);
      return met === 3;
    };
    input.addEventListener('input', check);
    input.form.addEventListener('submit', (e) => {
      if (check()) return;
      e.preventDefault();
      showFieldError(input.form, input, 'Your password needs to meet all 3 requirements.');
      input.focus();
    });
  });

  $$('form[data-auth-form]').forEach((form) => {
    const email = form.elements.email;
    form.addEventListener('submit', (e) => {
      const hp = form.elements.awt_hp;
      if (hp) hp.value = '';
      const msg = validateField(email);
      showFieldError(form, email, msg);
      if (msg) { e.preventDefault(); email.focus(); return; }
      const ts = form.elements._ts;
      if (!ts || !ts.value || form.dataset.ready) return;
      const started = parseInt(ts.value.split('.')[0], 10) * 1000;
      const wait = 2100 - (Date.now() - started);
      if (wait <= 0) return;
      e.preventDefault();
      form.dataset.ready = '1';
      const btn = form.querySelector('button[type="submit"]');
      if (btn) btn.disabled = true;
      setTimeout(() => {
        if (btn) btn.disabled = false;
        form.requestSubmit();
      }, wait);
    });
  });

  /* Account: onboarding questions ------------------------------------- */
  $$('form[data-onboarding]').forEach((form) => {
    const next = $('[data-onboarding-continue]', form);
    const inputs = $$('input[name^="answer"]', form);
    const sync = () => { next.disabled = !inputs.some((i) => i.checked); };
    inputs.forEach((i) => i.addEventListener('change', sync));
    window.addEventListener('pageshow', sync);
    sync();
  });

  /* Account: theme toggle --------------------------------------------- */
  $$('form[data-theme-toggle]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      const theme = e.submitter && e.submitter.value;
      if (!theme) return;
      e.preventDefault();
      document.documentElement.dataset.theme = theme;
      $$('form[data-theme-toggle] button[name="theme"]').forEach((b) => b.setAttribute('aria-pressed', String(b.value === theme)));
      const body = new FormData(form);
      body.set('theme', theme);
      fetch(form.getAttribute('action'), { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' }).catch(() => {});
    });
  });

  /* Account: small forms that save without leaving the page ------------ */
  $$('form[data-quiet-form]').forEach((form) => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const body = new FormData(form);
      if (e.submitter && e.submitter.name) body.set(e.submitter.name, e.submitter.value);
      const panel = form.closest('[data-dismissible]');
      try {
        const res = await fetch(form.getAttribute('action'), { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const data = await res.json();
        if (data.status === 'success') {
          if (panel) panel.remove();
          return;
        }
        const err = $('[data-survey-error]', form);
        if (err) { err.textContent = data.message; err.hidden = false; }
      } catch (err) {
        form.submit();
      }
    });
  });

  $$('form[data-survey]').forEach((form) => {
    const submit = $('[data-survey-submit]', form);
    const other = $('[data-survey-other]', form);
    const sync = () => {
      const choice = form.elements.referral.value;
      other.disabled = choice !== 'other';
      submit.disabled = !choice || (choice === 'other' && other.value.trim().length < 2);
    };
    form.addEventListener('change', (e) => {
      sync();
      if (e.target.name === 'referral' && e.target.value === 'other') other.focus();
    });
    other.addEventListener('input', sync);
    sync();
  });

  /* Workspace: greeting in the visitor's local time -------------------- */
  const greet = $('[data-greeting]');
  if (greet) {
    const now = new Date();
    const h = now.getHours();
    const [text, emoji] = h < 12 ? ['Good morning', '☀️'] : h < 18 ? ['Good afternoon', '🌤️'] : ['Good evening', '🌙'];
    $('[data-greeting-text]', greet).textContent = text;
    $('[data-greeting-emoji]', greet).textContent = emoji;
    $('[data-greeting-date]', greet).textContent = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }).replace(/,(?=[^,]*$)/, '');
  }

  /* Workspace: sidebar, collapse and account menu ---------------------- */
  const ws = $('[data-workspace]');
  if (ws) {
    const side = $('#ws-side', ws);
    const scrim = $('[data-ws-scrim]', ws);
    const menuBtn = $('[data-ws-menu]', ws);
    const collapse = $('[data-ws-collapse]', ws);
    const setCollapsed = (on) => {
      ws.classList.toggle('is-collapsed', on);
      collapse.setAttribute('aria-pressed', String(on));
      $('.sr-only', collapse).textContent = on ? 'Expand sidebar' : 'Collapse sidebar';
    };
    try { setCollapsed(localStorage.getItem('awt-sidebar') === 'collapsed'); } catch (e) { /* storage unavailable */ }
    collapse.addEventListener('click', () => {
      const on = !ws.classList.contains('is-collapsed');
      setCollapsed(on);
      try { localStorage.setItem('awt-sidebar', on ? 'collapsed' : 'open'); } catch (e) { /* storage unavailable */ }
    });

    const setDrawer = (open) => {
      ws.classList.toggle('is-drawer-open', open);
      menuBtn.setAttribute('aria-expanded', String(open));
      scrim.hidden = !open;
      document.body.classList.toggle('menu-open', open);
      if (open) $('[data-ws-close]', side).focus(); else menuBtn.focus();
    };
    menuBtn.addEventListener('click', () => setDrawer(true));
    $('[data-ws-close]', ws).addEventListener('click', () => setDrawer(false));
    scrim.addEventListener('click', () => setDrawer(false));

    const acctBtn = $('[data-account-toggle]', ws);
    const acctMenu = $('#ws-account-menu', ws);
    const setAccount = (open) => { acctMenu.hidden = !open; acctBtn.setAttribute('aria-expanded', String(open)); };
    acctBtn.addEventListener('click', () => setAccount(acctMenu.hidden));
    document.addEventListener('click', (e) => { if (!acctMenu.hidden && !e.target.closest('.ws-account')) setAccount(false); });
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape') return;
      if (!acctMenu.hidden) { setAccount(false); acctBtn.focus(); }
      if (ws.classList.contains('is-drawer-open')) setDrawer(false);
    });
  }

  /* Workspace: dropdown menus ------------------------------------------ */
  const menus = $$('[data-ws-dd]');
  const closeMenu = (menu, focus = false) => {
    const btn = $('[data-menu-toggle]', menu);
    $('[data-menu-panel]', menu).hidden = true;
    btn.setAttribute('aria-expanded', 'false');
    if (focus) btn.focus();
  };
  menus.forEach((menu) => {
    const btn = $('[data-menu-toggle]', menu);
    const panel = $('[data-menu-panel]', menu);
    btn.addEventListener('click', () => {
      const open = panel.hidden;
      menus.forEach((m) => { if (m !== menu && !m.contains(menu)) closeMenu(m); });
      panel.hidden = !open;
      btn.setAttribute('aria-expanded', String(open));
      if (open) {
        const field = $('input:not([type="hidden"]), select', panel);
        if (field) field.focus();
      }
    });
  });
  if (menus.length) {
    document.addEventListener('click', (e) => {
      menus.forEach((m) => { if (!m.contains(e.target) || e.target.closest('[data-compose], [data-idea-open]')) closeMenu(m); });
    });
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape') return;
      menus.forEach((m) => { if (!$('[data-menu-panel]', m).hidden) closeMenu(m, m.contains(document.activeElement)); });
    });
  }

  /* Workspace: confirmations and notices -------------------------------- */
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-confirm]');
    if (btn && !window.confirm(btn.dataset.confirm)) e.preventDefault();
  });
  const toastEl = $('[data-toast]');
  let toastTimer;
  const toast = (message) => {
    if (!toastEl || !message) return;
    toastEl.textContent = message;
    toastEl.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toastEl.hidden = true; }, 5000);
  };
  if (toastEl) {
    try {
      const stored = sessionStorage.getItem('awt-toast');
      if (stored) { sessionStorage.removeItem('awt-toast'); toast(stored); }
    } catch (e) { /* storage unavailable */ }
    if (!toastEl.hidden) toastTimer = setTimeout(() => { toastEl.hidden = true; }, 5000);
  }
  const reloadWith = (message) => {
    try { sessionStorage.setItem('awt-toast', message); } catch (e) { /* storage unavailable */ }
    window.location.reload();
  };

  /* Workspace: post composer ------------------------------------------- */
  const MEDIA_TYPES = { 'image/jpeg': 'image', 'image/png': 'image', 'image/webp': 'image', 'image/gif': 'image', 'video/mp4': 'video', 'video/quicktime': 'video', 'video/webm': 'video' };
  const MEDIA_MAX = { image: 10 * 1024 * 1024, video: 200 * 1024 * 1024 };
  const wsToken = () => { const ws = $('[data-workspace]'); return ws ? ws.dataset.token : ''; };
  const mb = (n) => (n < 1048576 ? `${Math.max(1, Math.round(n / 1024))} KB` : `${(n / 1048576).toFixed(1)} MB`);

  const initComposer = (form) => {
    const f = form.elements;
    const area = f.body;
    const targets = () => $$('input[name="targets[]"]', form);
    const picked = () => targets().filter((t) => t.checked);
    const side = $('[data-cp-side]', form);
    const dialog = form.closest('dialog');
    const schedule = $('[data-cp-schedule]', form);
    let media = null;
    let uploading = false;
    let pendingImage = false;
    const emptyFrame = $('[data-pv-media]', form).innerHTML;

    const setPanel = (name) => {
      const current = side.hidden ? null : side.dataset.open;
      const next = current === name ? null : name;
      side.hidden = !next;
      side.dataset.open = next || '';
      form.classList.toggle('has-side', Boolean(next));
      $$('[data-cp-panel]', form).forEach((p) => { p.hidden = p.dataset.cpPanel !== next; });
      $$('[data-cp-tab]', form).forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.cpTab === next)));
    };
    $('[data-cp-panels]', form).hidden = false;
    $('[data-cp-another-wrap]', form).hidden = !dialog;

    const preview = () => {
      const list = picked();
      const yt = list.find((t) => t.dataset.provider === 'youtube');
      const first = yt || list[0];
      $('[data-pv-yt]', form).hidden = !yt;
      $('[data-pv-post]', form).hidden = !first || Boolean(yt);
      $('[data-pv-post]', form).classList.toggle('is-li', Boolean(first) && !yt && first.dataset.provider === 'linkedin');
      $('[data-pv-blank]', form).hidden = Boolean(first);
      $('[data-pv-title]', form).textContent = first ? `${yt ? 'YouTube' : first.dataset.name} Preview` : 'Preview';
      if (!first) return;
      const thumb = $('[data-cp-thumb]', form).firstElementChild;
      if (yt) {
        $('[data-pv-name]', form).textContent = yt.dataset.name;
        $('[data-pv-heading]', form).textContent = f.title.value.trim() || 'Your video title';
        $('[data-pv-text]', form).textContent = area.value;
        const frame = $('[data-pv-media]', form);
        const src = thumb ? thumb.getAttribute('src') : '';
        if (frame.dataset.src !== src) {
          frame.dataset.src = src;
          frame.innerHTML = '';
          if (thumb) {
            const el = document.createElement(thumb.tagName === 'VIDEO' ? 'video' : 'img');
            el.src = src;
            if (el.tagName === 'VIDEO') { el.controls = true; el.muted = true; el.playsInline = true; el.preload = 'metadata'; } else { el.alt = ''; }
            frame.append(el);
          } else {
            frame.innerHTML = emptyFrame;
          }
        }
      } else {
        $('[data-pv-name2]', form).textContent = first.dataset.name;
        $('[data-pv-meta]', form).textContent = first.dataset.provider === 'linkedin' ? 'Just now · LinkedIn' : 'Just now';
        $('[data-pv-text2]', form).textContent = area.value || 'Your post text shows here.';
        $('[data-pv-react]', form).hidden = first.dataset.provider !== 'linkedin';
        const face = $('[data-pv-face]', form);
        const label = first.closest('label');
        const photo = label ? $('img', label) : null;
        const initial = label ? $('.ch-av-initial', label) : null;
        face.replaceChildren();
        if (photo) {
          const img = document.createElement('img');
          img.src = photo.getAttribute('src');
          img.alt = '';
          face.append(img);
        } else {
          face.textContent = (initial ? initial.textContent : (first.dataset.name || '•')).trim().charAt(0);
        }
        const box = $('[data-pv-media2]', form);
        box.innerHTML = '';
        if (thumb && thumb.tagName === 'IMG') { const img = document.createElement('img'); img.src = thumb.getAttribute('src'); img.alt = ''; box.append(img); }
      }
    };

    const sync = () => {
      const list = picked();
      const providers = list.map((t) => t.dataset.provider);
      const limit = list.length ? Math.min(...list.map((t) => Number(t.dataset.limit))) : 5000;
      const left = limit - area.value.length;
      const count = $('[data-count]', form);
      count.textContent = left >= 0 ? left.toLocaleString('en-US') : `${(-left).toLocaleString('en-US')} over`;
      count.title = `${limit.toLocaleString('en-US')} character limit`;
      count.classList.toggle('is-over', left < 0);
      $('[data-cp-for]', form).textContent = list.length ? list.map((t) => t.dataset.name).join(', ') : 'No channel selected';
      const yt = providers.includes('youtube');
      const li = providers.includes('linkedin');
      $$('[data-yt-only]', form).forEach((el) => { el.hidden = !yt; });
      $$('[data-li-only]', form).forEach((el) => { el.hidden = !li; });
      $('[data-cp-plan-hint]', form).hidden = !list.some((t) => t.value.startsWith('n:'));
      targets().forEach((t) => { const label = $(`label[for="${t.id}"]`, form); if (label) label.classList.toggle('is-on', t.checked); });
      const tags = f.tags.value.split(',').map((s) => s.trim()).filter(Boolean);
      $('[data-cp-tags-label]', form).textContent = tags.length ? tags.join(', ') : 'Tags';
      const publishYt = list.some((t) => t.value.startsWith('c:') && t.dataset.provider === 'youtube');
      const publishLi = list.some((t) => t.value.startsWith('c:') && t.dataset.provider === 'linkedin');
      const angle = yt && /[<>]/.test(f.title.value + area.value);
      const angleErr = $('[data-error-for="title"]', form);
      if (angle) { angleErr.textContent = 'YouTube titles and descriptions can’t contain < or >.'; angleErr.hidden = false; angleErr.dataset.live = '1'; } else if (angleErr.dataset.live) { angleErr.hidden = true; delete angleErr.dataset.live; }
      const conflict = publishYt && publishLi;
      const ready = list.length > 0 && left >= 0 && !uploading && !angle && !conflict
        && (area.value.trim() !== '' || media)
        && (!publishYt || (f.title.value.trim() !== '' && media && media.kind === 'video'))
        && (!publishLi || (area.value.trim() !== '' && (!media || media.kind === 'image')))
        && f.date.value !== '' && f.time.value !== '';
      schedule.disabled = !ready;
      schedule.title = ready ? '' : (conflict ? 'YouTube and LinkedIn need separate posts' : publishYt ? 'Add a title and a video, and pick a time' : publishLi ? 'Write the post and pick a time. Image optional, no video.' : 'Write something and pick a time');
      $$('button[value="draft"]', form).forEach((b) => { b.disabled = uploading; });
      preview();
    };

    const insert = (text) => {
      const start = area.selectionStart ?? area.value.length;
      const end = area.selectionEnd ?? start;
      area.setRangeText(text, start, end, 'end');
      area.focus();
      sync();
    };

    const showMedia = (m) => {
      media = m;
      f.media_id.value = m ? m.id : 0;
      $('[data-cp-media]', form).hidden = !m && !uploading;
      $('[data-cp-drop]', form).hidden = Boolean(m) || uploading;
      const thumb = $('[data-cp-thumb]', form);
      if (!m) { thumb.innerHTML = ''; sync(); return; }
      const el = document.createElement(m.kind === 'video' ? 'video' : 'img');
      el.src = m.url;
      if (m.kind === 'video') { el.muted = true; el.preload = 'metadata'; } else { el.alt = ''; }
      thumb.innerHTML = '';
      thumb.append(el);
      $('[data-cp-file-name]', form).textContent = m.name;
      $('[data-cp-file-meta]', form).textContent = `${m.kind.toUpperCase()} · ${mb(m.size)}`;
      sync();
    };

    const mediaError = (msg) => {
      const el = $('[data-error-for="media"]', form);
      el.textContent = msg || '';
      el.hidden = !msg;
    };

    const upload = async (file) => {
      mediaError('');
      const kind = MEDIA_TYPES[file.type];
      if (!kind) { mediaError('Upload a JPG, PNG, WebP or GIF image, or an MP4, MOV or WebM video.'); return; }
      if (file.size > MEDIA_MAX[kind]) { mediaError(kind === 'image' ? 'Images can be up to 10 MB.' : 'Videos can be up to 200 MB.'); return; }
      uploading = true;
      const bar = $('[data-cp-progress]', form);
      $('[data-cp-media]', form).hidden = false;
      $('[data-cp-drop]', form).hidden = true;
      $('[data-cp-thumb]', form).innerHTML = '';
      $('[data-cp-file-name]', form).textContent = file.name;
      $('[data-cp-file-meta]', form).textContent = `Uploading… 0 of ${mb(file.size)}`;
      bar.hidden = false;
      bar.firstElementChild.style.width = '0%';
      sync();
      const json = async (res) => { const data = await res.json().catch(() => ({})); if (!res.ok || data.status !== 'ok') throw new Error(data.message || 'Upload failed. Please try again.'); return data; };
      try {
        const start = new FormData();
        start.set('_token', wsToken());
        start.set('name', file.name);
        start.set('size', String(file.size));
        start.set('type', file.type);
        const opened = await json(await fetch('/app/media', { method: 'POST', body: start, headers: { Accept: 'application/json' }, credentials: 'same-origin' }));
        let offset = 0;
        while (offset < file.size) {
          const part = file.slice(offset, offset + opened.chunk);
          const sent = await json(await fetch(`/app/media/${opened.id}/chunk`, {
            method: 'POST', body: part, credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/octet-stream', 'X-CSRF-Token': wsToken(), 'X-Upload-Offset': String(offset) },
          }));
          offset = sent.offset;
          bar.firstElementChild.style.width = `${Math.round((offset / file.size) * 100)}%`;
          $('[data-cp-file-meta]', form).textContent = `Uploading… ${mb(offset)} of ${mb(file.size)}`;
        }
        const done = new FormData();
        done.set('_token', wsToken());
        const result = await json(await fetch(`/app/media/${opened.id}/complete`, { method: 'POST', body: done, headers: { Accept: 'application/json' }, credentials: 'same-origin' }));
        uploading = false;
        bar.hidden = true;
        showMedia(result.media);
      } catch (err) {
        uploading = false;
        bar.hidden = true;
        showMedia(null);
        mediaError(err.message);
      }
    };

    const fileInput = $('[data-cp-file]', form);
    const drop = $('[data-cp-drop]', form);
    fileInput.addEventListener('change', () => { if (fileInput.files[0]) upload(fileInput.files[0]); fileInput.value = ''; });
    ['dragenter', 'dragover'].forEach((t) => drop.addEventListener(t, (e) => { e.preventDefault(); drop.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach((t) => drop.addEventListener(t, () => drop.classList.remove('is-over')));
    drop.addEventListener('drop', (e) => { e.preventDefault(); const file = e.dataTransfer.files[0]; if (file) upload(file); });
    $('[data-cp-pick-file]', form).addEventListener('click', () => { if (!uploading && !media) fileInput.click(); });
    $('[data-cp-media-remove]', form).addEventListener('click', () => { if (!uploading) { showMedia(null); mediaError(''); } });

    const pops = $$('[data-cp-pop]', form);
    pops.forEach((d) => d.addEventListener('toggle', () => {
      if (!d.open) return;
      pops.forEach((o) => { if (o !== d) o.open = false; });
      const field = $('input[type="search"], input:not([type="checkbox"])', d);
      if (field) field.focus();
    }));
    document.addEventListener('pointerdown', (e) => pops.forEach((d) => { if (d.open && !d.contains(e.target)) d.open = false; }));
    form.addEventListener('keydown', (e) => {
      const open = pops.find((d) => d.open);
      if (e.key === 'Escape' && open) { e.preventDefault(); e.stopPropagation(); open.open = false; $('summary', open).focus(); }
    });
    const interest = $('[data-cp-interest]', form);
    const network = $('[data-cp-network]', form);
    const applyTemplates = () => {
      if (!interest && !network) return;
      const topic = interest ? interest.value : '';
      const net = network ? network.value : '';
      let shown = 0;
      $$('[data-cp-topic]', form).forEach((li) => {
        const liTopic = li.dataset.cpTopic || '';
        const liNet = li.dataset.cpNetwork || '';
        const show = net === '' && topic === '' ? liNet === '' && liTopic === ''
          : net !== '' && topic === '' ? liNet === net
            : net === '' ? liNet === '' && liTopic === topic
              : liNet === net && liTopic === topic;
        li.hidden = !show;
        if (show) shown += 1;
      });
      const empty = $('[data-cp-tpl-empty]', form);
      if (empty) empty.hidden = shown > 0;
      const lede = $('[data-cp-tpl-lede]', form);
      if (lede) {
        lede.textContent = net === 'youtube' || net === 'tiktok'
          ? 'These are video scripts. Record your own video and attach that file. Use does not add a photo.'
          : 'Choose a network or an interest. Use puts the text and photo into your post so you can rewrite them.';
      }
    };
    if (interest) interest.addEventListener('change', applyTemplates);
    if (network) network.addEventListener('change', applyTemplates);
    if (network && network.value !== '') applyTemplates();
    const search = $('[data-cp-search]', form);
    search.addEventListener('input', () => {
      const q = search.value.trim().toLowerCase();
      $$('[data-cp-name]', form).forEach((li) => { li.hidden = q !== '' && !li.dataset.cpName.includes(q); });
    });
    $('[data-cp-none]', form).addEventListener('click', () => { targets().forEach((t) => { t.checked = false; }); sync(); });

    const attachImage = (src) => {
      if (!src || uploading || pendingImage) return;
      pendingImage = true;
      fetch(src, { credentials: 'same-origin' })
        .then((res) => { if (!res.ok) throw new Error('That image could not be added.'); return res.blob(); })
        .then((blob) => {
          pendingImage = false;
          if (uploading) return;
          const name = src.split('/').pop() || 'template.webp';
          upload(new File([blob], name, { type: blob.type || 'image/webp' }));
        })
        .catch((err) => {
          pendingImage = false;
          mediaError(err.message || 'That image could not be added.');
        });
    };

    form.addEventListener('click', (e) => {
      const ins = e.target.closest('[data-cp-insert]');
      if (ins) { insert(ins.dataset.cpInsert); const pop = ins.closest('details'); if (pop) pop.open = false; return; }
      const use = e.target.closest('[data-cp-use]');
      if (use) {
        area.value = area.value.trim() ? `${area.value.trimEnd()}\n\n${use.dataset.cpUse}` : use.dataset.cpUse;
        if (use.dataset.cpTitle) f.title.value = use.dataset.cpTitle;
        area.focus();
        sync();
        attachImage(use.dataset.cpImage);
        return;
      }
      const tab = e.target.closest('[data-cp-tab]');
      if (tab) { setPanel(tab.dataset.cpTab); return; }
      const open = e.target.closest('[data-cp-open]');
      if (open) { if (side.dataset.open !== open.dataset.cpOpen) setPanel(open.dataset.cpOpen); const p = $('[data-ai-prompt]', form); if (p) p.focus(); }
    });
    const expand = $('[data-cp-expand]', form);
    if (expand && dialog) expand.addEventListener('click', () => {
      const on = !dialog.classList.contains('is-full');
      dialog.classList.toggle('is-full', on);
      expand.setAttribute('aria-pressed', String(on));
    });

    const aiGo = $('[data-ai-go]', form);
    if (aiGo) {
      const out = $('[data-ai-out]', form);
      const aiErr = $('[data-ai-error]', form);
      aiGo.addEventListener('click', async () => {
        const prompt = $('[data-ai-prompt]', form).value.trim();
        aiErr.hidden = true;
        if (!prompt) { aiErr.textContent = 'Tell the assistant what the post is about.'; aiErr.hidden = false; return; }
        const body = new FormData();
        body.set('_token', wsToken());
        body.set('prompt', prompt);
        body.set('network', picked().length ? picked()[0].dataset.provider : '');
        aiGo.disabled = true;
        aiGo.lastChild.textContent = 'Generating…';
        try {
          const res = await fetch('/app/ai/generate', { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
          const data = await res.json().catch(() => ({}));
          if (data.status !== 'ok') throw new Error(data.message || 'The AI Assistant couldn’t answer just now.');
          $('[data-ai-text]', form).textContent = data.text;
          out.hidden = false;
        } catch (err) {
          aiErr.textContent = err.message;
          aiErr.hidden = false;
        } finally {
          aiGo.disabled = false;
          aiGo.lastChild.textContent = 'Generate';
        }
      });
      $$('[data-ai-insert]', form).forEach((b) => b.addEventListener('click', () => {
        const text = $('[data-ai-text]', form).textContent;
        area.value = b.dataset.aiInsert === 'append' && area.value.trim() ? `${area.value.trimEnd()}\n\n${text}` : text;
        area.focus();
        sync();
      }));
    }

    form.addEventListener('input', sync);
    form.addEventListener('change', sync);
    form.addEventListener('submit', (e) => { if (uploading) e.preventDefault(); });

    const initialMedia = Number(f.media_id.value) > 0 ? { id: Number(f.media_id.value), kind: $('[data-cp-thumb] video', form) ? 'video' : 'image', name: $('[data-cp-file-name]', form).textContent, size: 0, url: `/app/media/${f.media_id.value}` } : null;
    media = initialMedia;
    if (window.matchMedia('(min-width: 1100px)').matches) setPanel('preview');
    sync();
    if (form.dataset.cpStartImage && !media) attachImage(form.dataset.cpStartImage);

    return {
      sync,
      fill(data) {
        form.reset();
        uploading = false;
        f.id.value = data.id || 0;
        f.idea.value = data.idea || 0;
        const wanted = Array.isArray(data.targets) ? data.targets.filter(Boolean) : (data.channel ? [`n:${data.channel}`] : []);
        if (wanted.length) targets().forEach((t) => { t.checked = wanted.includes(t.value); });
        area.value = data.body || '';
        f.title.value = data.title || '';
        f.tags.value = (data.tags || '').split(',').filter(Boolean).join(', ');
        const meta = data.meta || {};
        if (meta.category) f.yt_category.value = meta.category;
        if (meta.visibility) f.yt_visibility.value = meta.visibility;
        if (meta.license) f.yt_license.value = meta.license;
        ['notify', 'embeddable', 'kids', 'synthetic'].forEach((k) => { if (k in meta) f[`yt_${k}`].checked = Boolean(meta[k]); });
        if (data.date) f.date.value = data.date;
        if (data.time) f.time.value = data.time;
        mediaError('');
        $('[data-cp-progress]', form).hidden = true;
        showMedia(data.media || null);
        if (!data.media && data.image) attachImage(data.image);
        $('[data-compose-title]', form).textContent = data.id ? 'Edit Post' : 'Create Post';
        $('[data-compose-delete]', form).hidden = !data.id;
        $('[data-cp-another-wrap]', form).hidden = !dialog || Boolean(data.id);
        sync();
      },
      keepGoing() {
        area.value = '';
        f.title.value = '';
        f.id.value = 0;
        showMedia(null);
        area.focus();
      },
    };
  };
  const composers = new Map();
  $$('form[data-cp]').forEach((form) => composers.set(form, initComposer(form)));

  const showErrors = (form, data) => {
    const box = $('[data-form-error]', form);
    box.textContent = data.message || 'Something went wrong. Please try again.';
    box.hidden = false;
    $$('[data-error-for]', form).forEach((el) => {
      const msg = data.errors && data.errors[el.dataset.errorFor];
      el.textContent = msg || '';
      el.hidden = !msg;
      const field = form.elements[el.dataset.errorFor];
      if (field && field.setAttribute && !(field instanceof RadioNodeList)) field.toggleAttribute('aria-invalid', Boolean(msg));
    });
  };
  const clearErrors = (form) => {
    $('[data-form-error]', form).hidden = true;
    $$('[data-error-for]', form).forEach((el) => { el.hidden = true; });
    $$('[aria-invalid]', form).forEach((el) => el.removeAttribute('aria-invalid'));
  };
  const ajaxDialogForm = (dialog) => {
    const form = $('form', dialog);
    form.addEventListener('submit', async (e) => {
      if (e.defaultPrevented) return;
      e.preventDefault();
      const another = $('[data-cp-another]', form);
      const body = new FormData(form);
      if (e.submitter && e.submitter.name) body.set(e.submitter.name, e.submitter.value);
      const buttons = $$('button[type="submit"]', form);
      buttons.forEach((b) => { b.disabled = true; });
      try {
        const res = await fetch(form.getAttribute('action'), { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const data = await res.json();
        if (data.status === 'success') {
          const action = e.submitter ? e.submitter.value : '';
          if (another && another.checked && action !== 'delete' && composers.has(form)) {
            clearErrors(form);
            toast(data.message);
            composers.get(form).keepGoing();
            dialog.dataset.changed = '1';
            return;
          }
          dialog.close();
          reloadWith(data.message);
          return;
        }
        showErrors(form, data);
      } catch (err) {
        showErrors(form, { message: "We couldn't save that. Check your connection and try again." });
      } finally {
        buttons.forEach((b) => { b.disabled = false; });
        if (composers.has(form)) composers.get(form).sync();
      }
    });
    dialog.addEventListener('close', () => { if (dialog.dataset.changed) { delete dialog.dataset.changed; window.location.reload(); } });
    dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });
    $$('[data-dialog-close]', dialog).forEach((b) => b.addEventListener('click', () => dialog.close()));
    return form;
  };
  const parse = (raw) => { try { return JSON.parse(raw || '{}'); } catch (e) { return {}; } };

  const composeDialog = $('[data-dialog="compose"]');
  const ideaDialog = $('[data-dialog="idea"]');
  let opener = null;
  [composeDialog, ideaDialog].forEach((d) => d && d.addEventListener('close', () => { if (opener && document.contains(opener)) opener.focus(); opener = null; }));

  const openCompose = (data) => {
    const form = $('form', composeDialog);
    clearErrors(form);
    composers.get(form).fill(data);
    composeDialog.showModal();
    form.elements.body.focus();
  };
  const openIdea = (data) => {
    const form = $('form', ideaDialog);
    form.reset();
    clearErrors(form);
    form.elements.id.value = data.id || 0;
    form.elements.title.value = data.title || '';
    form.elements.body.value = data.body || '';
    form.elements.tags.value = data.tags || '';
    form.elements.group.value = String(data.group || 0);
    $('[data-idea-heading]', form).textContent = data.id ? 'Edit idea' : 'New idea';
    $('[data-idea-existing]', form).hidden = !data.id;
    $('[data-idea-to-post]', form).href = `/app/publish/new?idea=${data.id || 0}`;
    ideaDialog.showModal();
    form.elements.title.focus();
  };

  if (composeDialog && ideaDialog && typeof composeDialog.showModal === 'function') {
    ajaxDialogForm(composeDialog);
    const ideaForm = ajaxDialogForm(ideaDialog);
    document.addEventListener('click', (e) => {
      const compose = e.target.closest('[data-compose]');
      const idea = e.target.closest('[data-idea-open]');
      if (!compose && !idea) return;
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
      e.preventDefault();
      opener = compose || idea;
      if (compose) openCompose(parse(compose.dataset.compose)); else openIdea(parse(idea.dataset.ideaOpen));
    });
    $('[data-idea-to-post]', ideaForm).addEventListener('click', (e) => {
      e.preventDefault();
      const f = ideaForm.elements;
      const text = [f.title.value.trim(), f.body.value.trim()].filter(Boolean).join('\n\n');
      ideaDialog.close();
      openCompose({ body: text, idea: Number(f.id.value) });
    });
  }

  /* Workspace: connect a channel ---------------------------------------- */
  const connectDialog = $('[data-dialog="connect"]');
  if (connectDialog && typeof connectDialog.showModal === 'function') {
    document.addEventListener('click', (e) => {
      const link = e.target.closest('[data-connect-open]');
      if (!link || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
      e.preventDefault();
      menus.forEach((m) => closeMenu(m));
      opener = link;
      connectDialog.showModal();
      const target = link.hash && link.hash.startsWith('#connect-') ? $(`${link.hash} .cg-card`, connectDialog) : null;
      (target || $('.cg-card', connectDialog)).focus();
    });
    connectDialog.addEventListener('close', () => { if (opener && document.contains(opener)) opener.focus(); opener = null; });
    connectDialog.addEventListener('click', (e) => { if (e.target === connectDialog) connectDialog.close(); });
    $$('[data-dialog-close]', connectDialog).forEach((b) => b.addEventListener('click', () => connectDialog.close()));
    $$('form', connectDialog).forEach((f) => f.addEventListener('submit', () => { $('button', f).disabled = true; }));
  }

  const setupModal = $('[data-ch-modal]');
  if (setupModal) {
    const close = $('.ch-modal-close', setupModal);
    const first = $('input:checked', setupModal) || $('input:not([type="hidden"]), a.ws-btn', setupModal);
    if (first) first.focus();
    document.body.classList.add('menu-open');
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') window.location.href = close.href; });
    setupModal.addEventListener('click', (e) => { if (e.target === setupModal) window.location.href = close.href; });
    const custom = $('[data-goal-custom]', setupModal);
    if (custom) {
      setupModal.addEventListener('change', (e) => {
        if (e.target === custom) $('input[name="custom"]', setupModal).focus();
      });
    }
  }

  const introForm = $('[data-intro-form]');
  if (introForm) {
    introForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      $('[data-intro]').remove();
      try {
        await fetch(introForm.getAttribute('action'), { method: 'POST', body: new FormData(introForm), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      } catch (err) { /* it shows again next visit */ }
    });
  }

  /* Workspace: endless queue ------------------------------------------- */
  const queueBox = $('[data-queue]');
  if (queueBox && 'IntersectionObserver' in window) {
    let loading = false;
    const load = async (sentinel) => {
      if (loading) return;
      loading = true;
      observer.unobserve(sentinel);
      try {
        const res = await fetch(sentinel.dataset.queueMore, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error(String(res.status));
        sentinel.insertAdjacentHTML('beforebegin', await res.text());
        sentinel.remove();
        const next = $('[data-queue-more]', queueBox);
        if (next) observer.observe(next);
      } catch (err) {
        observer.unobserve(sentinel);
      } finally {
        loading = false;
      }
    };
    const observer = new IntersectionObserver((entries) => entries.forEach((en) => { if (en.isIntersecting) load(en.target); }), { rootMargin: '600px 0px' });
    const first = $('[data-queue-more]', queueBox);
    if (first) {
      observer.observe(first);
      $('a', first).addEventListener('click', (e) => { e.preventDefault(); load(first); });
    }
  }

  /* Workspace: drag a queued post onto another time or day ------------ */
  let movingPost = null;
  let skipComposeClick = false;
  const scheduleTarget = (node) => {
    if (!(node instanceof Element)) return null;
    return node.closest('[data-drop], [data-drop-day]');
  };
  document.addEventListener('dragstart', (e) => {
    const card = e.target.closest('[data-move]');
    if (!card) return;
    movingPost = card;
    skipComposeClick = true;
    card.classList.add('is-dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', card.dataset.move);
  });
  document.addEventListener('dragend', () => {
    movingPost?.classList.remove('is-dragging');
    $$('.is-over').forEach((el) => el.classList.remove('is-over'));
    movingPost = null;
  });
  document.addEventListener('dragover', (e) => {
    if (!movingPost) return;
    const target = scheduleTarget(e.target);
    $$('[data-drop].is-over, [data-drop-day].is-over').forEach((el) => { if (el !== target) el.classList.remove('is-over'); });
    if (!target || target.contains(movingPost)) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    target.classList.add('is-over');
  });
  document.addEventListener('drop', async (e) => {
    const target = scheduleTarget(e.target);
    const card = movingPost;
    if (!card || !target || target.contains(card)) return;
    e.preventDefault();
    const from = card.dataset.at || '';
    const when = target.dataset.drop || `${target.dataset.dropDay} ${from.slice(11, 16)}`;
    if (when === from || !/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/.test(when)) return;
    const body = new FormData();
    body.set('_token', ($('[data-workspace]') || {}).dataset?.token || '');
    body.set('id', card.dataset.move);
    body.set('date', when.slice(0, 10));
    body.set('time', when.slice(11, 16));
    try {
      const res = await fetch('/app/posts/move', { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || data.status === 'error') throw new Error(data.message || 'That move wasn’t saved.');
      reloadWith(data.message || 'Post moved.');
    } catch (err) {
      toast(err.message || 'That move wasn’t saved.');
    }
  });
  document.addEventListener('click', (e) => {
    if (!skipComposeClick) return;
    skipComposeClick = false;
    if (e.target.closest('[data-move]')) { e.preventDefault(); e.stopPropagation(); }
  }, true);

  /* Workspace: ideas board drag and drop ------------------------------- */
  $$('[data-idea-board]').forEach((board) => {
    const live = $('[data-board-live]', board);
    let dragging = null;
    const after = (list, y) => $$('[data-idea]:not(.is-dragging)', list).find((card) => {
      const box = card.getBoundingClientRect();
      return y < box.top + box.height / 2;
    });
    const recount = () => $$('[data-col]', board).forEach((col) => { $('[data-count]', col).textContent = $$('[data-idea]', col).length; });
    const save = async (col) => {
      const body = new FormData();
      body.set('_token', board.dataset.token);
      body.set('group', col.dataset.col);
      $$('[data-idea]', col).forEach((card) => body.append('order[]', card.dataset.idea));
      try {
        const res = await fetch('/app/ideas/move', { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error(String(res.status));
      } catch (e) {
        reloadWith("That move wasn't saved. The board has been refreshed.");
      }
    };
    board.addEventListener('dragstart', (e) => {
      const card = e.target.closest('[data-idea]');
      if (!card) return;
      dragging = { card, from: card.closest('[data-col]') };
      card.classList.add('is-dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', card.dataset.idea);
    });
    board.addEventListener('dragover', (e) => {
      const col = e.target.closest('[data-col]');
      if (!dragging || !col) return;
      e.preventDefault();
      $$('[data-col]', board).forEach((c) => c.classList.toggle('is-over', c === col));
      const list = $('[data-list]', col);
      const next = after(list, e.clientY);
      if (next) list.insertBefore(dragging.card, next); else list.appendChild(dragging.card);
    });
    board.addEventListener('drop', (e) => { if (dragging) e.preventDefault(); });
    board.addEventListener('dragend', () => {
      if (!dragging) return;
      const { card, from } = dragging;
      const to = card.closest('[data-col]');
      card.classList.remove('is-dragging');
      $$('[data-col]', board).forEach((c) => c.classList.remove('is-over'));
      dragging = null;
      recount();
      save(to);
      if (from !== to) save(from);
      live.textContent = `Moved "${$('.bd-card-title', card).textContent}" to ${to.dataset.label}.`;
    });
  });

  /* Workspace: set the time zone from the browser on first visit ------- */
  if (ws && 'tzUnset' in ws.dataset && window.Intl) {
    const zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    if (zone) {
      const body = new FormData();
      body.set('_token', ws.dataset.token);
      body.set('timezone', zone);
      fetch('/account/timezone', { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((res) => res.json())
        .then((data) => { if (data.status === 'success' && window.location.pathname.startsWith('/app/publish')) window.location.reload(); })
        .catch(() => {});
    }
  }

  $$('[data-auto-submit]').forEach((el) => {
    el.addEventListener('change', () => el.form && el.form.requestSubmit());
  });

  const slotsForm = $('[data-slots]');
  if (slotsForm) {
    const dayCol = (d) => $(`[data-day="${d}"]`, slotsForm);
    const timesBox = (d) => $(`[data-day-times="${d}"]`, slotsForm);
    const addTime = (d, time) => {
      const col = dayCol(d);
      const box = timesBox(d);
      const on = $(`[data-day-on="${d}"]`, slotsForm);
      if (!col || !box || !on) return;
      on.checked = true;
      col.classList.add('is-on');
      $$('input[type="time"]', box).forEach((i) => { i.disabled = false; });
      const blank = $$('input[type="time"]', box).find((i) => !i.value);
      if (blank) { blank.value = time; return; }
      const input = document.createElement('input');
      input.type = 'time';
      input.name = `times[${d}][]`;
      input.value = time;
      input.setAttribute('aria-label', 'Posting time');
      box.append(input);
    };
    const save = () => slotsForm.requestSubmit();
    slotsForm.addEventListener('change', (e) => {
      const on = e.target.closest('[data-day-on]');
      if (!on) { if (e.target.matches('input[type="time"]')) save(); return; }
      const d = on.dataset.dayOn;
      const col = dayCol(d);
      const box = timesBox(d);
      col.classList.toggle('is-on', on.checked);
      $$('input[type="time"]', box).forEach((i) => {
        i.disabled = !on.checked;
        if (!on.checked) i.value = '';
        else if (!i.value) i.value = '18:00';
      });
      save();
    });
    $('[data-slot-add]', slotsForm).addEventListener('click', () => {
      const time = $('[data-slot-time]', slotsForm).value;
      const day = $('[data-slot-day]', slotsForm).value;
      if (!time) return;
      (day === '0' ? ['1', '2', '3', '4', '5', '6', '7'] : [day]).forEach((d) => addTime(d, time));
      save();
    });
    $('[data-slot-clear]', slotsForm).addEventListener('click', () => {
      $$('[data-day-on]', slotsForm).forEach((on) => { on.checked = false; });
      $$('.set-day', slotsForm).forEach((col) => col.classList.remove('is-on'));
      $$('input[type="time"]', slotsForm).forEach((i) => { if (i.name) { i.value = ''; i.disabled = true; } });
    });
  }

  /* Scroll reveal for content that starts below the fold --------------- */
  if ('IntersectionObserver' in window && !reduceMotion.matches) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    $$('.reveal').forEach((el) => {
      if (el.getBoundingClientRect().top > window.innerHeight) {
        el.classList.add('reveal-armed');
        io.observe(el);
      }
    });
  }
})();
