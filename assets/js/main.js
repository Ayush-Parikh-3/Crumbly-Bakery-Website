/* ============================================================
   CRUMBLY - Main JavaScript
   ============================================================ */

const SITE_URL = document.querySelector('meta[name="site-url"]')?.content || window.location.origin;

// ============================================================
// NAVBAR SCROLL EFFECT
// ============================================================
const navbar = document.getElementById('navbar');
if (navbar) {
  window.addEventListener('scroll', () => {
    navbar.classList.toggle('scrolled', window.scrollY > 20);
  }, { passive: true });
}

// ============================================================
// CART DRAWER
// ============================================================
const cartDrawer = document.getElementById('cartDrawer');
const cartOverlay = document.getElementById('cartOverlay');
const cartToggle = document.getElementById('cartToggle');
const cartClose = document.getElementById('cartClose');

function openCart() {
  cartDrawer?.classList.add('open');
  cartOverlay?.classList.add('open');
  document.body.style.overflow = 'hidden';
  loadCart();
}

function closeCart() {
  cartDrawer?.classList.remove('open');
  cartOverlay?.classList.remove('open');
  document.body.style.overflow = '';
}

cartToggle?.addEventListener('click', openCart);
cartClose?.addEventListener('click', closeCart);
cartOverlay?.addEventListener('click', closeCart);

// Keyboard close
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeCart();
});

async function loadCart() {
  try {
    const res = await fetch('/crumbly/api/cart.php?action=get');
    const data = await res.json();
    renderCart(data);
  } catch (e) {
    console.error('Cart load failed', e);
  }
}

function renderCart(data) {
  const itemsEl = document.getElementById('cartItems');
  const emptyEl = document.getElementById('cartEmpty');
  const footerEl = document.getElementById('cartFooter');
  const summaryEl = document.getElementById('cartSummary');
  const badgeEl = document.getElementById('cartBadge');

  if (!itemsEl) return;

  const items = data.items || [];
  const totals = data.totals || {};
  const totalQty = items.reduce((s, i) => s + i.quantity, 0);

  // Update badge
  if (badgeEl) {
    badgeEl.textContent = totalQty;
    badgeEl.style.display = totalQty > 0 ? 'grid' : 'none';
  }

  if (items.length === 0) {
    if (emptyEl) emptyEl.style.display = 'block';
    if (footerEl) footerEl.style.display = 'none';
    itemsEl.innerHTML = '';
    itemsEl.appendChild(emptyEl || createEmptyCart());
    return;
  }

  if (emptyEl) emptyEl.style.display = 'none';
  if (footerEl) footerEl.style.display = 'block';

  itemsEl.innerHTML = items.map(item => `
    <div class="cart-item" data-cart-id="${item.id}">
      <div class="cart-item-img">
        <img src="${item.image || '/crumbly/assets/images/placeholder.svg'}" alt="${escHtml(item.name)}">
      </div>
      <div class="cart-item-info">
        <p class="cart-item-name">${escHtml(item.name)}</p>
        <p class="cart-item-shop">${escHtml(item.shop_name)}</p>
        ${item.variant ? `<p style="font-size:0.75rem;color:var(--text-muted)">${escHtml(item.variant)}</p>` : ''}
        <div class="d-flex align-center justify-between mt-2">
          <div class="cart-qty">
            <button class="qty-btn" onclick="updateCartQty(${item.id}, ${item.quantity - 1})">−</button>
            <span class="qty-num">${item.quantity}</span>
            <button class="qty-btn" onclick="updateCartQty(${item.id}, ${item.quantity + 1})">+</button>
          </div>
          <span class="cart-item-price">${item.price_formatted}</span>
        </div>
      </div>
      <button class="cart-item-remove" onclick="removeCartItem(${item.id})" title="Remove">✕</button>
    </div>
  `).join('');

  if (summaryEl) {
    summaryEl.innerHTML = `
      <div class="cart-summary-row"><span>Subtotal</span><span>${totals.subtotal_fmt || '₹0'}</span></div>
      ${totals.delivery_fee > 0 ? `<div class="cart-summary-row"><span>Delivery</span><span>${totals.delivery_fmt}</span></div>` : ''}
      ${totals.discount > 0 ? `<div class="cart-summary-row" style="color:var(--sage)"><span>Discount</span><span>−${totals.discount_fmt}</span></div>` : ''}
      <div class="cart-summary-row total"><span>Total</span><span>${totals.total_fmt || '₹0'}</span></div>
    `;
  }
}

async function updateCartQty(cartId, qty) {
  if (qty < 1) { removeCartItem(cartId); return; }
  try {
    const fd = new FormData();
    fd.append('action', 'update');
    fd.append('cart_id', cartId);
    fd.append('quantity', qty);
    const res = await fetch('/crumbly/api/cart.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) renderCart(data);
    else showToast(data.message || 'Failed to update cart', 'error');
  } catch (e) { showToast('Network error', 'error'); }
}

async function removeCartItem(cartId) {
  try {
    const fd = new FormData();
    fd.append('action', 'remove');
    fd.append('cart_id', cartId);
    const res = await fetch('/crumbly/api/cart.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) { renderCart(data); showToast('Removed from cart', 'success'); }
  } catch (e) { showToast('Network error', 'error'); }
}

async function addToCart(productId, qty = 1, variantId = null, customization = '') {
  try {
    const fd = new FormData();
    fd.append('action', 'add');
    fd.append('product_id', productId);
    fd.append('quantity', qty);
    if (variantId) fd.append('variant_id', variantId);
    if (customization) fd.append('customization', customization);
    const res = await fetch('/crumbly/api/cart.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      showToast('Added to cart! 🛒', 'success');
      if (data.cart) renderCart(data.cart);
      // Badge update
      const badge = document.getElementById('cartBadge');
      if (badge && data.cart_count) {
        badge.textContent = data.cart_count;
        badge.style.display = 'grid';
      }
    } else {
      showToast(data.message || 'Could not add to cart', 'error');
    }
    return data;
  } catch (e) {
    showToast('Network error', 'error');
    return { success: false };
  }
}

// ============================================================
// WISHLIST
// ============================================================
async function toggleWishlist(productId, btn) {
  try {
    const fd = new FormData();
    fd.append('product_id', productId);
    const res = await fetch('/crumbly/api/wishlist.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      btn.classList.toggle('active', data.added);
      btn.textContent = data.added ? '❤️' : '🤍';
      showToast(data.added ? 'Added to wishlist ❤️' : 'Removed from wishlist', data.added ? 'success' : 'info');
    } else if (data.redirect) {
      window.location.href = data.redirect;
    }
  } catch (e) { showToast('Network error', 'error'); }
}

// ============================================================
// TOAST NOTIFICATIONS
// ============================================================
function showToast(message, type = 'info', duration = 3500) {
  const container = document.getElementById('toastContainer');
  if (!container) return;

  const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `
    <span class="toast-icon">${icons[type] || 'ℹ️'}</span>
    <span>${escHtml(message)}</span>
    <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
  `;
  container.appendChild(toast);

  setTimeout(() => {
    toast.classList.add('toast-exit');
    setTimeout(() => toast.remove(), 400);
  }, duration);
}

// ============================================================
// QUANTITY SELECTOR
// ============================================================
document.querySelectorAll('[data-qty-dec]').forEach(btn => {
  btn.addEventListener('click', () => {
    const target = document.getElementById(btn.dataset.qtyDec);
    if (target && +target.value > 1) target.value = +target.value - 1;
  });
});

document.querySelectorAll('[data-qty-inc]').forEach(btn => {
  btn.addEventListener('click', () => {
    const target = document.getElementById(btn.dataset.qtyInc);
    if (target) {
      const max = +(target.dataset.max || 99);
      if (+target.value < max) target.value = +target.value + 1;
    }
  });
});

// ============================================================
// IMAGE GALLERY
// ============================================================
const galleryMain = document.querySelector('.gallery-main img');
document.querySelectorAll('.gallery-thumb').forEach(thumb => {
  thumb.addEventListener('click', function() {
    if (galleryMain) galleryMain.src = this.dataset.src || this.querySelector('img')?.src;
    document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
    this.classList.add('active');
  });
});

// ============================================================
// TABS
// ============================================================
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    const tabId = this.dataset.tab;
    const container = this.closest('.product-tabs') || document;
    container.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    container.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    this.classList.add('active');
    document.getElementById(tabId)?.classList.add('active');
  });
});

// ============================================================
// MODAL
// ============================================================
function openModal(id) {
  document.getElementById(id)?.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeModal(id) {
  document.getElementById(id)?.classList.remove('open');
  document.body.style.overflow = '';
}

document.querySelectorAll('[data-modal-open]').forEach(btn => {
  btn.addEventListener('click', () => openModal(btn.dataset.modalOpen));
});

document.querySelectorAll('[data-modal-close]').forEach(btn => {
  btn.addEventListener('click', () => closeModal(btn.dataset.modalClose));
});

document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => {
    if (e.target === overlay) {
      overlay.classList.remove('open');
      document.body.style.overflow = '';
    }
  });
});

// ============================================================
// FILE UPLOAD DRAG & DROP
// ============================================================
document.querySelectorAll('.file-upload-area').forEach(area => {
  const input = area.querySelector('input[type="file"]');

  area.addEventListener('click', () => input?.click());
  area.addEventListener('dragover', e => { e.preventDefault(); area.classList.add('dragover'); });
  area.addEventListener('dragleave', () => area.classList.remove('dragover'));
  area.addEventListener('drop', e => {
    e.preventDefault();
    area.classList.remove('dragover');
    if (input && e.dataTransfer.files.length) {
      input.files = e.dataTransfer.files;
      input.dispatchEvent(new Event('change'));
    }
  });

  input?.addEventListener('change', function() {
    const names = Array.from(this.files).map(f => f.name).join(', ');
    const textEl = area.querySelector('.file-upload-text');
    if (textEl && names) textEl.innerHTML = `📎 <strong>${escHtml(names)}</strong>`;
  });
});

// ============================================================
// IMAGE PREVIEW
// ============================================================
document.querySelectorAll('input[data-preview]').forEach(input => {
  input.addEventListener('change', function() {
    const preview = document.getElementById(this.dataset.preview);
    if (preview && this.files[0]) {
      const reader = new FileReader();
      reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
      reader.readAsDataURL(this.files[0]);
    }
  });
});

// ============================================================
// ROLE SELECTOR (SIGNUP)
// ============================================================
document.querySelectorAll('.role-card').forEach(card => {
  card.addEventListener('click', function() {
    document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
    this.classList.add('selected');
    const roleInput = document.getElementById('roleInput');
    if (roleInput) roleInput.value = this.dataset.role;
  });
});

// ============================================================
// COUPON CODE
// ============================================================
const couponBtn = document.getElementById('applyCouponBtn');
couponBtn?.addEventListener('click', async () => {
  const code = document.getElementById('couponCode')?.value?.trim();
  if (!code) { showToast('Enter a coupon code', 'warning'); return; }
  try {
    const fd = new FormData();
    fd.append('code', code);
    const res = await fetch('/crumbly/api/coupon.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      showToast(`🎉 Coupon applied! Saved ${data.discount_fmt}`, 'success');
      // Update checkout totals
      document.getElementById('checkoutDiscount')?.innerHTML && location.reload();
    } else {
      showToast(data.message || 'Invalid coupon', 'error');
    }
  } catch (e) { showToast('Network error', 'error'); }
});

// ============================================================
// SEARCH AUTOCOMPLETE (lightweight)
// ============================================================
let searchTimeout;
const searchInput = document.querySelector('.search-input');
const searchDropdown = document.getElementById('searchDropdown');

if (searchInput) {
  searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const q = this.value.trim();
    if (q.length < 2) { if (searchDropdown) searchDropdown.style.display = 'none'; return; }
    searchTimeout = setTimeout(async () => {
      try {
        const res = await fetch(`/crumbly/api/search.php?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (searchDropdown && data.results?.length) {
          searchDropdown.innerHTML = data.results.map(r => `
            <a href="/crumbly/product.php?id=${r.id}" class="search-suggestion">
              <img src="${r.image || '/crumbly/assets/images/placeholder.svg'}" alt="${escHtml(r.name)}" width="32" height="32" style="border-radius:6px;object-fit:cover">
              <div>
                <div style="font-weight:600;font-size:0.875rem">${escHtml(r.name)}</div>
                <div style="font-size:0.75rem;color:var(--text-muted)">${escHtml(r.shop_name)}</div>
              </div>
              <div style="margin-left:auto;font-weight:700;color:var(--amber);font-size:0.875rem">${r.price_fmt}</div>
            </a>
          `).join('');
          searchDropdown.style.display = 'block';
        }
      } catch (e) {}
    }, 300);
  });

  document.addEventListener('click', e => {
    if (!searchInput.contains(e.target)) {
      if (searchDropdown) searchDropdown.style.display = 'none';
    }
  });
}

// ============================================================
// CONFIRM DELETE
// ============================================================
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', function(e) {
    if (!confirm(this.dataset.confirm || 'Are you sure?')) e.preventDefault();
  });
});

// ============================================================
// FORM VALIDATION
// ============================================================
document.querySelectorAll('form[data-validate]').forEach(form => {
  form.addEventListener('submit', function(e) {
    let valid = true;
    this.querySelectorAll('[required]').forEach(field => {
      field.classList.remove('error');
      const errEl = field.nextElementSibling;
      if (!field.value.trim()) {
        field.classList.add('error');
        valid = false;
      }
      if (field.type === 'email' && field.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) {
        field.classList.add('error');
        valid = false;
      }
    });
    if (!valid) {
      e.preventDefault();
      showToast('Please fill all required fields', 'warning');
      this.querySelector('.error')?.focus();
    }
  });
});

// ============================================================
// LOAD MORE / INFINITE SCROLL
// ============================================================
const loadMoreBtn = document.getElementById('loadMoreBtn');
loadMoreBtn?.addEventListener('click', async function() {
  const page = +(this.dataset.page || 1) + 1;
  const url = new URL(window.location);
  url.searchParams.set('page', page);
  url.searchParams.set('ajax', '1');
  this.disabled = true;
  this.textContent = 'Loading...';
  try {
    const res = await fetch(url.toString());
    const html = await res.text();
    const grid = document.querySelector('.products-grid');
    if (grid) grid.insertAdjacentHTML('beforeend', html);
    this.dataset.page = page;
    this.disabled = false;
    this.textContent = 'Load More';
    if (!html.trim()) { this.style.display = 'none'; }
  } catch (e) {
    this.disabled = false;
    this.textContent = 'Load More';
  }
});

// ============================================================
// SELLER: PRODUCT STOCK TOGGLE
// ============================================================
document.querySelectorAll('.toggle-product-status').forEach(toggle => {
  toggle.addEventListener('change', async function() {
    const productId = this.dataset.id;
    const active = this.checked ? 1 : 0;
    try {
      const fd = new FormData();
      fd.append('product_id', productId);
      fd.append('is_active', active);
      const res = await fetch('/crumbly/api/seller.php?action=toggle_product', { method: 'POST', body: fd });
      const data = await res.json();
      if (!data.success) { this.checked = !this.checked; showToast('Failed to update', 'error'); }
      else showToast(active ? 'Product activated' : 'Product hidden', 'success');
    } catch (e) { this.checked = !this.checked; }
  });
});

// ============================================================
// SELLER: ORDER STATUS UPDATE
// ============================================================
document.querySelectorAll('.update-order-status').forEach(sel => {
  sel.addEventListener('change', async function() {
    const orderId = this.dataset.orderId;
    const status = this.value;
    try {
      const fd = new FormData();
      fd.append('order_id', orderId);
      fd.append('status', status);
      const res = await fetch('/crumbly/api/seller.php?action=update_order_status', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) showToast(`Order status updated to ${status}`, 'success');
      else showToast(data.message || 'Update failed', 'error');
    } catch (e) { showToast('Network error', 'error'); }
  });
});

// ============================================================
// UTILITIES
// ============================================================
function escHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function debounce(fn, delay) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
}

// Animate elements on scroll (IntersectionObserver)
const observer = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('fade-in');
      observer.unobserve(entry.target);
    }
  });
}, { threshold: 0.1 });

document.querySelectorAll('.animate-on-scroll').forEach(el => observer.observe(el));

// ============================================================
// PRICE RANGE FILTER
// ============================================================
const priceRange = document.getElementById('priceRange');
const priceDisplay = document.getElementById('priceDisplay');
priceRange?.addEventListener('input', function() {
  if (priceDisplay) priceDisplay.textContent = `₹0 — ₹${this.value}`;
});

// ============================================================
// EXPOSE GLOBALS
// ============================================================
window.crumbly = {
  addToCart, toggleWishlist, openCart, closeCart,
  openModal, closeModal, showToast, loadCart
};
