document.addEventListener('DOMContentLoaded', () => {
  initResponsiveTables();
  initLiveSearchAndCategoryFilter();
  initQuantitySteppers();
  initCartSelection();
  initFlashDismissal();
  initInteractiveDetailZoom();
  initConfirmForms();
  initFormSubmitFeedback();
  initAdminTableFilter();
  initHeaderScroll();
  initRippleEffect();
  initPageEntrance();
  initAuthControls();
  initPaymentCards();
  initTourPlayer();
  initScrollNavs();
});

/**
 * 1. Live Instant Product Search & Smooth Category Filtering (index.php)
 */
function initLiveSearchAndCategoryFilter() {
  const searchInput = document.querySelector('.search input[type="search"]');
  const grid = document.querySelector('.grid');
  const cards = grid ? Array.from(grid.querySelectorAll('.card')) : [];
  const chips = Array.from(document.querySelectorAll('.chips a'));
  const hero = document.querySelector('.hero');
  const countLabel = document.getElementById('jumlah-produk');

  if (!cards.length) return;

  // Search Clear button
  if (searchInput) {
    const searchWrap = searchInput.parentElement;
    if (searchWrap) {
      searchWrap.style.position = 'relative';
      const clearBtn = document.createElement('button');
      clearBtn.type = 'button';
      clearBtn.className = 'search-clear';
      clearBtn.innerHTML = '✕';
      clearBtn.setAttribute('aria-label', 'Hapus pencarian');
      clearBtn.style.display = searchInput.value ? 'flex' : 'none';
      searchWrap.appendChild(clearBtn);

      clearBtn.addEventListener('click', () => {
        searchInput.value = '';
        clearBtn.style.display = 'none';
        applyFilters();
        searchInput.focus();
      });

      searchInput.addEventListener('input', () => {
        clearBtn.style.display = searchInput.value ? 'flex' : 'none';
        applyFilters();
      });
    }
  }

  // Active category tracker
  let activeCategoryId = 0;
  const initialActiveChip = chips.find(c => c.classList.contains('on'));
  if (initialActiveChip && initialActiveChip.dataset.id) {
    activeCategoryId = parseInt(initialActiveChip.dataset.id, 10) || 0;
  }

  // Handle category chip clicks smoothly on client-side
  chips.forEach(chip => {
    chip.addEventListener('click', (e) => {
      // If clicking inside the current index page, handle reactively
      if (/(index\.php|\/)$/.test(window.location.pathname)) {
        const id = parseInt(chip.dataset.id, 10);
        if (!isNaN(id)) {
          e.preventDefault();
          activeCategoryId = id;
          chips.forEach(c => c.classList.remove('on'));
          chip.classList.add('on');
          applyFilters();
        }
      }
    });
  });

  // Dynamic live filtering function
  let emptyMsg = null;
  function applyFilters() {
    const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
    let matchCount = 0;

    // Toggle hero visibility on search
    if (hero) {
      if (query !== '' || activeCategoryId !== 0) {
        hero.style.display = 'none';
      } else {
        hero.style.display = 'grid';
      }
    }

    cards.forEach(card => {
      const cardCategory = parseInt(card.dataset.kategori, 10);
      const name = (card.querySelector('.nm')?.textContent || '').toLowerCase();
      const spec = (card.querySelector('.sp')?.textContent || '').toLowerCase();
      const price = (card.querySelector('.pr')?.textContent || '').toLowerCase();

      const matchesSearch = query === '' || name.includes(query) || spec.includes(query) || price.includes(query);
      const matchesCategory = activeCategoryId === 0 || cardCategory === activeCategoryId;

      if (matchesSearch && matchesCategory) {
        card.style.display = 'flex';
        matchCount++;
      } else {
        card.style.display = 'none';
      }
    });

    if (countLabel) countLabel.textContent = matchCount + ' produk';

    // Handle Empty State dynamically
    if (matchCount === 0) {
      if (!emptyMsg) {
        emptyMsg = document.createElement('div');
        emptyMsg.className = 'empty';
        emptyMsg.innerHTML = 'Tidak ada iPhone yang cocok dengan pencarianmu. <a href="javascript:void(0)" class="reset-filter">Tampilkan semua</a>';
        grid.parentNode.insertBefore(emptyMsg, grid.nextSibling);
        emptyMsg.querySelector('.reset-filter')?.addEventListener('click', () => {
          if (searchInput) searchInput.value = '';
          const clear = document.querySelector('.search-clear');
          if (clear) clear.style.display = 'none';
          activeCategoryId = 0;
          chips.forEach(c => c.classList.remove('on'));
          chips[0]?.classList.add('on');
          applyFilters();
        });
      }
      grid.style.display = 'none';
      emptyMsg.style.display = 'block';
    } else {
      grid.style.display = 'grid';
      if (emptyMsg) emptyMsg.style.display = 'none';
    }
  }
}

/**
 * 2. Tactile Quantity Steppers (+ / -)
 */
function initQuantitySteppers() {
  const qtyInputs = document.querySelectorAll('input[type="number"][name="jumlah"], input[type="number"].qty');

  qtyInputs.forEach(input => {
    // Avoid double wrapping
    if (input.parentElement.classList.contains('qty-stepper')) return;

    const min = parseInt(input.getAttribute('min'), 10) || 0;
    const max = parseInt(input.getAttribute('max'), 10) || 9999;

    const stepper = document.createElement('div');
    stepper.className = 'qty-stepper';

    const btnDec = document.createElement('button');
    btnDec.type = 'button';
    btnDec.className = 'qty-btn dec';
    btnDec.innerHTML = '−';
    btnDec.setAttribute('aria-label', 'Kurangi');

    const btnInc = document.createElement('button');
    btnInc.type = 'button';
    btnInc.className = 'qty-btn inc';
    btnInc.innerHTML = '+';
    btnInc.setAttribute('aria-label', 'Tambah');

    input.parentNode.insertBefore(stepper, input);
    stepper.appendChild(btnDec);
    stepper.appendChild(input);
    stepper.appendChild(btnInc);

    function updateBtnState() {
      const val = parseInt(input.value, 10) || 0;
      btnDec.disabled = val <= min;
      btnInc.disabled = val >= max;
    }

    btnDec.addEventListener('click', () => {
      let val = parseInt(input.value, 10) || 1;
      if (val > min) {
        input.value = val - 1;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        updateBtnState();
      }
    });

    btnInc.addEventListener('click', () => {
      let val = parseInt(input.value, 10) || 0;
      if (val < max) {
        input.value = val + 1;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        updateBtnState();
      }
    });

    input.addEventListener('input', updateBtnState);
    input.addEventListener('change', () => {
      updateBtnState();
      // If inside keranjang table form, highlight the "Ubah" button
      const ubahBtn = input.closest('form')?.querySelector('button.btn');
      if (ubahBtn) {
        ubahBtn.style.background = '#000000';
        ubahBtn.style.color = '#ffffff';
        ubahBtn.style.transform = 'scale(1.05)';
        setTimeout(() => { ubahBtn.style.transform = 'none'; }, 200);
      }
    });

    updateBtnState();
  });
}

/**
 * 3. Dismissable Flash Alerts with Auto-Dismiss
 */
function initFlashDismissal() {
  const flashes = document.querySelectorAll('.flash');
  flashes.forEach(flash => {
    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'flash-close';
    closeBtn.innerHTML = '✕';
    closeBtn.setAttribute('aria-label', 'Tutup');
    flash.appendChild(closeBtn);

    function closeFlash() {
      flash.style.opacity = '0';
      flash.style.transform = 'translateY(-8px)';
      setTimeout(() => flash.remove(), 250);
    }

    closeBtn.addEventListener('click', closeFlash);

    // Auto-dismiss ok messages after 5 seconds if not hovered
    if (flash.classList.contains('ok')) {
      let timer = setTimeout(closeFlash, 5000);
      flash.addEventListener('mouseenter', () => clearTimeout(timer));
      flash.addEventListener('mouseleave', () => {
        timer = setTimeout(closeFlash, 2500);
      });
    }
  });
}

/**
 * 4. Interactive Product Detail Image Zoom
 */
function initInteractiveDetailZoom() {
  const detailThumb = document.querySelector('.detail .thumb');
  if (!detailThumb) return;

  const img = detailThumb.querySelector('img');
  if (!img) return;

  detailThumb.addEventListener('mousemove', (e) => {
    const rect = detailThumb.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width - 0.5) * 16;
    const y = ((e.clientY - rect.top) / rect.height - 0.5) * 16;
    img.style.transform = `scale(1.06) translate(${x}px, ${y}px)`;
  });

  detailThumb.addEventListener('mouseleave', () => {
    img.style.transform = 'scale(1) translate(0, 0)';
    img.style.transition = 'transform 0.4s ease';
  });

  detailThumb.addEventListener('mouseenter', () => {
    img.style.transition = 'transform 0.1s ease-out';
  });
}

/**
 * 5. Responsive Form Submit Feedback (Prevents duplicate requests)
 */
/**
 * Form dengan atribut data-konfirmasi meminta persetujuan dulu; bila dibatalkan, form tidak dikirim
 * dan efek "Memproses…" tidak dijalankan (listener ini harus terdaftar lebih dulu).
 */
function initConfirmForms() {
  document.querySelectorAll('form[data-konfirmasi]').forEach(form => {
    form.addEventListener('submit', (e) => {
      if (!window.confirm(form.dataset.konfirmasi)) {
        e.preventDefault();
        e.stopImmediatePropagation();
      }
    });
  });
}

function initFormSubmitFeedback() {
  const forms = document.querySelectorAll('form:not([role="search"])');
  forms.forEach(form => {
    form.addEventListener('submit', (e) => {
      const btn = form.querySelector('button[type="submit"], button:not([type="button"])');
      if (btn && !btn.hasAttribute('data-no-loading')) {
        // Let normal submission happen, but give tactile feedback
        btn.classList.add('is-submitting');
        btn.style.pointerEvents = 'none';
        const originalText = btn.innerHTML;
        // Don't change text if button is small
        if (!btn.classList.contains('sm') && btn.offsetWidth > 100) {
          btn.innerHTML = 'Memproses…';
        }
        // Safety re-enable after 6 seconds in case of client-side validation abort
        setTimeout(() => {
          btn.classList.remove('is-submitting');
          btn.style.pointerEvents = 'auto';
          btn.innerHTML = originalText;
        }, 6000);
      }
    });
  });
}


/**
 * Authentication helpers: accessible password reveal and lightweight strength hint.
 */
function initAuthControls() {
  document.querySelectorAll('[data-password-toggle]').forEach(toggle => {
    const inputId = toggle.getAttribute('data-password-toggle');
    const input = inputId ? document.getElementById(inputId) : null;
    if (!input) return;

    toggle.addEventListener('click', () => {
      const reveal = input.type === 'password';
      input.type = reveal ? 'text' : 'password';
      toggle.textContent = reveal ? 'Sembunyi' : 'Lihat';
      toggle.setAttribute('aria-label', reveal ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
      toggle.setAttribute('aria-pressed', reveal ? 'true' : 'false');
      input.focus({ preventScroll: true });
    });
  });

  const password = document.querySelector('[data-password-strength]');
  const meter = document.querySelector('.auth-strength-track i');
  const label = document.querySelector('[data-strength-label]');
  if (!password || !meter || !label) return;

  password.addEventListener('input', () => {
    const value = password.value;
    if (!value) {
      meter.style.width = '0%';
      meter.style.background = '#aaa';
      label.textContent = 'Gunakan minimal 6 karakter.';
      return;
    }

    let score = 0;
    if (value.length >= 6) score++;
    if (value.length >= 10) score++;
    if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score++;
    if (/[0-9]/.test(value) || /[^A-Za-z0-9]/.test(value)) score++;

    const percent = [22, 48, 72, 100][Math.max(0, score - 1)] || 22;
    const color = score <= 1 ? '#b7791f' : score <= 2 ? '#85858d' : '#39835a';
    const message = score <= 1 ? 'Kata sandi masih sederhana.' : score <= 2 ? 'Kekuatan kata sandi cukup.' : 'Kata sandi lebih kuat.';
    meter.style.width = percent + '%';
    meter.style.background = color;
    label.textContent = message;
  });
}

/**
 * Kartu metode pembayaran (checkout.php): tandai kartu yang dipilih.
 */
function initPaymentCards() {
  const cards = document.querySelectorAll('.pay-card');
  cards.forEach(card => {
    const radio = card.querySelector('input[type="radio"]');
    if (!radio) return;
    radio.addEventListener('change', () => {
      cards.forEach(c => c.classList.remove('selected'));
      if (radio.checked) card.classList.add('selected');
    });
  });
}

/**
 * 6. Admin Table Instant Live Filter (Quick-Search in Tables)
 */
function initAdminTableFilter() {
  const tblWrap = document.querySelector('.admin-main .tbl-wrap');
  if (!tblWrap) return;

  const tbl = tblWrap.querySelector('.tbl');
  if (!tbl) return;

  const rows = Array.from(tbl.querySelectorAll('tbody tr'));
  if (rows.length < 2) return; // Only add filter if there's data to filter

  // Create a clean filter bar above the table
  const filterWrap = document.createElement('div');
  filterWrap.className = 'table-search-wrap';
  filterWrap.innerHTML = `
    <input type="text" class="table-search-input" placeholder="Cari di tabel…" aria-label="Cari data tabel" autocomplete="off">
  `;

  tblWrap.parentNode.insertBefore(filterWrap, tblWrap);

  const filterInput = filterWrap.querySelector('.table-search-input');
  filterInput.addEventListener('input', () => {
    const q = filterInput.value.trim().toLowerCase();
    rows.forEach(row => {
      const text = row.textContent.toLowerCase();
      row.style.display = text.includes(q) ? '' : 'none';
    });
  });
}

/**
 * 7. Header Elevation on Scroll
 */
function initHeaderScroll() {
  const top = document.querySelector('.top');
  if (!top) return;

  window.addEventListener('scroll', () => {
    if (window.scrollY > 15) {
      top.classList.add('scrolled');
    } else {
      top.classList.remove('scrolled');
    }
  }, { passive: true });
}

/**
 * 8. Ripple Effect on Buttons — fluid ink wave
 */
function initRippleEffect() {
  document.addEventListener('pointerdown', (e) => {
    const btn = e.target.closest('.btn');
    if (!btn || btn.disabled || btn.hasAttribute('disabled')) return;

    // Remove any existing ripple immediately
    const old = btn.querySelector('.ripple');
    if (old) old.remove();

    const rect = btn.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height) * 2;
    const x = e.clientX - rect.left - size / 2;
    const y = e.clientY - rect.top  - size / 2;

    const ripple = document.createElement('span');
    ripple.className = 'ripple';
    ripple.style.cssText = `
      position:absolute;
      width:${size}px;
      height:${size}px;
      left:${x}px;
      top:${y}px;
      border-radius:50%;
      background:rgba(255,255,255,0.22);
      pointer-events:none;
      transform:scale(0);
      animation:ripple-anim 0.72s cubic-bezier(.22,1,.36,1) forwards;
    `;

    // For ghost/danger buttons, use dark ripple
    if (btn.classList.contains('ghost') || btn.classList.contains('danger')) {
      ripple.style.background = 'rgba(0,0,0,0.07)';
    }

    btn.appendChild(ripple);
    ripple.addEventListener('animationend', () => ripple.remove(), { once: true });
  });

  // Inject keyframes once
  if (!document.getElementById('ripple-style')) {
    const style = document.createElement('style');
    style.id = 'ripple-style';
    style.textContent = `
      @keyframes ripple-anim {
        to { transform: scale(1); opacity: 0; }
      }
    `;
    document.head.appendChild(style);
  }
}

/**
 * 9. Soft page entrance for cards & stat blocks.
 * The temporary reveal class is removed after the animation so hover opacity
 * transitions remain fully controllable by CSS.
 */
function initPageEntrance() {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const targets = document.querySelectorAll('.card, .stat, .pay-card, .supplier-stat');
  if (!targets.length || typeof IntersectionObserver === 'undefined') return;

  if (!document.getElementById('page-reveal-style')) {
    const style = document.createElement('style');
    style.id = 'page-reveal-style';
    style.textContent = `
      @keyframes pageRevealSoft {
        from {
          opacity: 0;
          translate: 0 10px;
          filter: blur(1.5px);
        }
        to {
          opacity: var(--reveal-opacity, 1);
          translate: 0 0;
          filter: blur(0);
        }
      }
      .page-reveal {
        animation: pageRevealSoft 780ms cubic-bezier(.22, 1, .36, 1)
          var(--reveal-delay, 0ms) both;
      }
    `;
    document.head.appendChild(style);
  }

  const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;

      const element = entry.target;
      const siblings = Array.from(element.parentElement?.children || []);
      const index = Math.max(0, siblings.indexOf(element));
      const fadeTarget = finePointer && element.matches('.card, .stat, .pay-card, .supplier-stat')
        ? '.94'
        : '1';

      element.style.setProperty('--reveal-opacity', fadeTarget);
      element.style.setProperty('--reveal-delay', `${Math.min(index * 38, 190)}ms`);
      element.classList.add('page-reveal');

      const cleanup = (event) => {
        if (event.animationName !== 'pageRevealSoft') return;
        element.classList.remove('page-reveal');
        element.style.removeProperty('--reveal-opacity');
        element.style.removeProperty('--reveal-delay');
        element.removeEventListener('animationend', cleanup);
      };

      element.addEventListener('animationend', cleanup);
      observer.unobserve(element);
    });
  }, { threshold: 0.08, rootMargin: '0px 0px -20px 0px' });

  targets.forEach((element) => observer.observe(element));
}


/**
 * Pemutar video tur beranda (index.php)
 */
function initTourPlayer() {
  const root = document.querySelector('[data-tour]');
  if (!root) return;

  const video = root.querySelector('video');
  const big = root.querySelector('.tour-big');
  const toggle = root.querySelector('.tour-toggle');
  const mute = root.querySelector('.tour-mute');
  const full = root.querySelector('.tour-full');
  const seek = root.querySelector('.tour-seek');
  const now = root.querySelector('.tour-now');
  const total = root.querySelector('.tour-total');
  const chapters = Array.from(document.querySelectorAll('.tour-chapters button'));
  const starts = chapters.map((b) => parseFloat(b.dataset.start) || 0);
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const fmt = (sec) => {
    const s = Math.max(0, Math.floor(sec || 0));
    return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
  };
  const duration = () => (isFinite(video.duration) && video.duration > 0 ? video.duration : 38);

  root.classList.add('is-idle');

  function start() {
    root.classList.remove('is-idle', 'is-ended');
    const p = video.play();
    if (p && p.catch) p.catch(() => {});
  }

  function render() {
    const d = duration();
    const t = video.currentTime || 0;
    const pct = Math.min(100, (t / d) * 100);
    seek.value = String(Math.round((t / d) * 1000));
    seek.style.setProperty('--p', pct + '%');
    now.textContent = fmt(t);

    let active = 0;
    starts.forEach((s, i) => { if (t >= s - 0.05) active = i; });
    chapters.forEach((btn, i) => {
      const end = i + 1 < starts.length ? starts[i + 1] : d;
      const c = i < active ? 100 : i > active ? 0 : Math.min(100, ((t - starts[i]) / (end - starts[i])) * 100);
      btn.style.setProperty('--c', Math.max(0, c) + '%');
      if (i === active) btn.setAttribute('aria-current', 'true');
      else btn.removeAttribute('aria-current');
    });
  }

  video.addEventListener('loadedmetadata', () => {
    total.textContent = fmt(duration());
    render();
  });
  video.addEventListener('timeupdate', render);
  video.addEventListener('play', () => {
    root.classList.add('is-playing');
    root.classList.remove('is-idle', 'is-ended');
    toggle.setAttribute('aria-label', 'Jeda');
  });
  video.addEventListener('pause', () => {
    root.classList.remove('is-playing');
    toggle.setAttribute('aria-label', 'Putar');
  });
  video.addEventListener('ended', () => {
    root.classList.remove('is-playing');
    root.classList.add('is-ended');
    toggle.setAttribute('aria-label', 'Putar ulang');
  });

  const togglePlay = () => {
    if (video.ended) video.currentTime = 0;
    if (video.paused) start();
    else video.pause();
  };

  big.addEventListener('click', start);
  toggle.addEventListener('click', togglePlay);
  video.addEventListener('click', togglePlay);

  seek.addEventListener('input', () => {
    video.currentTime = (parseFloat(seek.value) / 1000) * duration();
    render();
  });

  mute.addEventListener('click', () => {
    video.muted = !video.muted;
    root.classList.toggle('is-muted', video.muted);
    mute.setAttribute('aria-label', video.muted ? 'Nyalakan suara' : 'Matikan suara');
  });

  const canFullscreen = root.requestFullscreen || root.webkitRequestFullscreen || video.webkitEnterFullscreen;
  if (!canFullscreen) {
    full.hidden = true;
  } else {
    full.addEventListener('click', () => {
      const current = document.fullscreenElement || document.webkitFullscreenElement;
      if (current) {
        (document.exitFullscreen || document.webkitExitFullscreen).call(document);
      } else if (root.requestFullscreen) {
        root.requestFullscreen();
      } else if (root.webkitRequestFullscreen) {
        root.webkitRequestFullscreen();
      } else if (video.webkitEnterFullscreen) {
        video.webkitEnterFullscreen();
      }
    });
  }

  chapters.forEach((btn, i) => {
    btn.addEventListener('click', () => {
      video.currentTime = starts[i];
      start();
      render();
      const rect = root.getBoundingClientRect();
      if (rect.top < 0 || rect.bottom > window.innerHeight) {
        root.scrollIntoView({ block: 'center', behavior: reduceMotion ? 'auto' : 'smooth' });
      }
    });
  });

  render();
}


/**
 * 15. Tabel jadi kartu di layar HP
 * Setiap sel diberi label dari judul kolomnya (data-label) dan isinya dibungkus .td-v,
 * supaya CSS (@media max-width: 720px) bisa menampilkan baris sebagai kartu tanpa geser samping.
 * Tabel kecil (kurang dari 4 kolom) dibiarkan sebagai tabel biasa.
 */
function initResponsiveTables() {
  document.querySelectorAll('table.tbl').forEach(table => {
    const heads = table.querySelectorAll('thead th');
    if (heads.length < 4) return;

    const labels = [];
    heads.forEach(th => {
      const span = parseInt(th.getAttribute('colspan') || '1', 10) || 1;
      for (let i = 0; i < span; i++) labels.push(i === 0 ? th.textContent.trim() : '');
    });

    table.querySelectorAll('tbody tr').forEach(tr => {
      let col = 0;
      Array.from(tr.children).forEach(td => {
        if (td.tagName !== 'TD') return;
        const span = parseInt(td.getAttribute('colspan') || '1', 10) || 1;
        const label = span === 1 ? (labels[col] || '') : '';
        col += span;
        if (!label || td.hasAttribute('data-label')) return;

        td.setAttribute('data-label', label);
        const wrap = document.createElement('span');
        wrap.className = 'td-v';
        while (td.firstChild) wrap.appendChild(td.firstChild);
        td.appendChild(wrap);
      });
    });

    table.classList.add('tbl-stack');
    const holder = table.closest('.tbl-wrap, .supplier-table-wrap');
    if (holder) holder.classList.add('is-stacked');
  });
}

/**
 * 16. Menu yang bisa digeser (admin, supplier, kategori): tampilkan item aktif di tengah
 * agar pengguna HP tidak perlu mencarinya. Hanya menggeser menu itu sendiri, bukan halaman.
 */
function initScrollNavs() {
  document.querySelectorAll('.side-nav, .supplier-nav, .chips').forEach(nav => {
    const on = nav.querySelector('.on, [aria-current="page"]');
    if (!on || nav.scrollWidth <= nav.clientWidth) return;
    const kiri = on.getBoundingClientRect().left - nav.getBoundingClientRect().left + nav.scrollLeft;
    nav.scrollLeft = Math.max(0, kiri - (nav.clientWidth - on.offsetWidth) / 2);
  });
}


/**
 * Keranjang (keranjang.php): centang produk yang ingin di-checkout.
 * Total dan tombol mengikuti centang. Pilihan diingat selama tab terbuka (sessionStorage),
 * jadi tetap utuh setelah mengubah jumlah atau menghapus produk. Server tetap memvalidasi
 * pilihan saat checkout. Mengubah jumlah langsung tersimpan (tombol Ubah hanya tampil tanpa JS).
 */
function initCartSelection() {
  const form = document.getElementById('form-checkout');
  if (!form) return;

  // Jumlah: simpan otomatis sebentar setelah berubah.
  document.querySelectorAll('.cart-qty-form').forEach(qf => {
    const input = qf.querySelector('input.qty');
    if (!input) return;
    qf.classList.add('auto');
    let timer = null;
    input.addEventListener('change', () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        if (typeof qf.requestSubmit === 'function') qf.requestSubmit(); else qf.submit();
      }, 450);
    });
  });

  const boxes = Array.from(document.querySelectorAll('input.cart-check[name="pilih[]"]'));
  const all = document.getElementById('cart-pilih-semua');
  const countEl = document.getElementById('cart-dipilih');
  const totalEl = document.getElementById('cart-total');
  const submit = document.getElementById('cart-submit');
  const submitTxt = document.getElementById('cart-submit-txt');
  if (!boxes.length || !submit) return;

  const KEY = 'apel_keranjang_tidak_dipilih';
  const rp = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');

  // Simpan yang TIDAK dicentang, supaya produk baru otomatis ikut tercentang.
  const baca = () => {
    try { return JSON.parse(sessionStorage.getItem(KEY) || '[]'); } catch (e) { return []; }
  };
  const simpan = () => {
    try {
      sessionStorage.setItem(KEY, JSON.stringify(boxes.filter(b => !b.checked).map(b => b.value)));
    } catch (e) { /* penyimpanan tidak tersedia: abaikan */ }
  };

  function segarkan() {
    const dipilih = boxes.filter(b => b.checked);
    const total = dipilih.reduce((t, b) => t + (parseFloat(b.dataset.subtotal) || 0), 0);
    const adaTanpaHarga = dipilih.some(b => b.dataset.belum === '1');

    boxes.forEach(b => {
      const item = b.closest('.cart-item');
      if (item) item.classList.toggle('is-selected', b.checked);
    });
    if (all) {
      all.checked = dipilih.length === boxes.length;
      all.indeterminate = dipilih.length > 0 && dipilih.length < boxes.length;
    }
    if (countEl) countEl.textContent = dipilih.length + ' dari ' + boxes.length;
    if (totalEl) totalEl.textContent = rp(total);
    submit.disabled = dipilih.length === 0 || adaTanpaHarga;
    if (submitTxt) {
      submitTxt.textContent = dipilih.length === 0 ? 'Pilih produk dulu'
        : adaTanpaHarga ? 'Ada produk tanpa harga'
        : 'Lanjut ke checkout';
    }
  }

  const tidakDipilih = baca();
  boxes.forEach(b => { if (tidakDipilih.includes(b.value)) b.checked = false; });

  boxes.forEach(b => b.addEventListener('change', () => { simpan(); segarkan(); }));
  if (all) {
    all.addEventListener('change', () => {
      boxes.forEach(b => { b.checked = all.checked; });
      simpan();
      segarkan();
    });
  }
  form.addEventListener('submit', e => {
    if (submit.disabled) {
      e.preventDefault();
      e.stopImmediatePropagation();
    }
  });

  segarkan();
}
