/* global PortusWishlistAPI */
(() => {
 'use strict';
 // Only recognized product cards are scanned. Product IDs/URLs are validated by WooCommerce on the server.
 const selector = '.product, .wc-block-grid__product, .wc-block-product, .e-loop-item, [data-pwr-product-id]';
 const cache = new Map();
 const pending = new WeakSet();
 let running = false;
 let rerun = false;
 let timer;
 function identity(card) {
  let id = card.dataset.pwrProductId || card.dataset.productId || card.dataset.product_id;
  if (!id) id = card.className.match(/(?:^|\s)post-(\d+)(?:\s|$)/)?.[1];
  const cart = card.querySelector('[data-product_id], [data-product-id], a[href*="add-to-cart="]');
  if (!id && cart) {
   id = cart.dataset.product_id || cart.dataset.productId;
   if (!id && cart.href) id = new URL(cart.href, location.href).searchParams.get('add-to-cart');
  }
  const link = card.querySelector('.woocommerce-loop-product__link, .woocommerce-LoopProduct-link, .eael-product-list-title a, .eael-product-title a, .wc-block-components-product-name, h2 a, h3 a, a:has(img)');
  const url = link?.href || '';
  if (!/^\d+$/.test(String(id || '')) && !url) return null;
  return { id: /^\d+$/.test(String(id || '')) ? Number(id) : 0, url };
 }
 function mount(card, product) {
  if (!product || !card.isConnected || card.querySelector('.pwr-save')) return;
  const template = document.getElementById('pwr-button-template');
  if (!template) return;
  const content = template.content.cloneNode(true);
  const button = content.querySelector('.pwr-save');
  button.dataset.product = product.id;
  button.dataset.variable = product.variable ? '1' : '0';
  button.dataset.url = product.url;
  const target = card.querySelector('.eael-product-list-content-wrap, .eael-product-carousel, .eael-product-wrap .product-details-wrap, .summary') || card;
  if(window.PortusWishlistPosition==='before'){target.prepend(content);}else{target.append(content);}
 }
 async function scan() {
  if (running) { rerun = true; return; }
  running = true;
  try {
   const cards = [];
   document.querySelectorAll(selector).forEach(card => {
    if (card.closest('.pwr-list, .pwr-admin, .cart, .woocommerce-mini-cart') || pending.has(card) || card.querySelector('.pwr-save')) return;
    // Nested wrappers in some builders repeat the same product. Use the innermost identifiable card.
    if (card.querySelector(selector)) return;
    const item = identity(card);
    if (!item) return;
    const key = item.id ? `id:${item.id}` : `url:${item.url}`;
    if (cache.has(key)) mount(card, cache.get(key));
    else { cards.push({ card, item, key }); pending.add(card); }
   });
   for (let start = 0; start < cards.length; start += 100) {
    const batch = cards.slice(start, start + 100);
    try {
     const data = await PortusWishlistAPI.request('resolve', { items: JSON.stringify(batch.map(entry => entry.item)) });
     const products = new Map(data.products.map(product => [Number(product.key), product]));
     batch.forEach((entry, index) => { const product = products.get(index) || null; cache.set(entry.key, product); mount(entry.card, product); });
    } finally { batch.forEach(entry => pending.delete(entry.card)); }
   }
  } catch { /* Retried on the next builder render or page load; never block the storefront. */ }
  finally { running = false; if (rerun) { rerun = false; schedule(); } }
 }
 function schedule() { clearTimeout(timer); timer = setTimeout(scan, 120); }
 function init() {
  schedule();
  new MutationObserver(records => {
   if (records.some(record => [...record.addedNodes].some(node => node.nodeType === 1 && (node.matches(selector) || node.querySelector(selector) || (record.target.closest?.(selector) && !node.matches('.pwr-auto-actions')))) || [...record.removedNodes].some(node => node.nodeType === 1 && node.matches('.pwr-auto-actions')))) schedule();
  }).observe(document.body, { childList: true, subtree: true });
  if (window.jQuery) {
   window.jQuery(document).on('updated_wc_div wc_fragments_refreshed ajaxComplete', schedule);
   window.jQuery(window).on('elementor/frontend/init', () => {
    if (window.elementorFrontend?.hooks) window.elementorFrontend.hooks.addAction('frontend/element_ready/global', schedule);
   });
  }
 }
 if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
