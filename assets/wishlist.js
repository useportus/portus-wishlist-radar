/* global PortusWishlist */
(() => {
 'use strict';
 let nonce;
 let saved = new Set();
 let parents = new Set();
 let session;
 let undoToken = '';
 let listRequest = 0;
 const sideSelector = '[data-msc-favorite], [data-pwr-favorite]';
 let sideTimer;
 const config = PortusWishlist;
 const section = () => document.querySelector('.pwr-list');
 function listContext(){try{return JSON.parse(section()?.dataset.pwrContext||'{}');}catch{return {};}}
 const selected = () => [...document.querySelectorAll('.pwr-select:checked')].map(el => el.value).join(',');
 document.addEventListener('pointerdown',event=>{event.target.closest('.pwr-list')?.classList.add('pwr-pointer-focus');},true);
 document.addEventListener('keydown',event=>{
  if(['Tab','ArrowUp','ArrowDown','ArrowLeft','ArrowRight','Home','End'].includes(event.key))document.querySelectorAll('.pwr-list.pwr-pointer-focus').forEach(root=>root.classList.remove('pwr-pointer-focus'));
 },true);
 async function request(action, values = {}, retried = false) {
  const response = await fetch(config.ajax, {method:'POST',credentials:'same-origin',cache:'no-store',body:new URLSearchParams({action:`portus_wr_${action}`,nonce:nonce||'',...values})});
  const result = await response.json();
  if(!retried&&action!=='session'&&response.status===403&&result.data?.message?.includes('sessão expirou')){session=null;await ready();return request(action,values,true);}
  if (!result.success) throw new Error(result.data?.message || 'Não foi possível concluir. Tente novamente.');
  return result.data;
 }
 function ready() {
  if (!session) session = request('session').then(data => {nonce=data.nonce;}).catch(error=>{session=null;throw error;});
  return session;
 }
 function selectedProduct(button) {
  if (button.dataset.variable!=='1') return button.dataset.product;
  const unavailable=button.closest('.pwr-product-actions')?.querySelector('.pwr-unavailable')?.value;
  if (unavailable) return unavailable;
  const form=[...document.querySelectorAll('form.variations_form')].find(el=>String(el.dataset.product_id)===button.dataset.product);
  return form?.querySelector('input[name="variation_id"]')?.value || '';
 }
 function paintButtons() {
  document.querySelectorAll('.pwr-save').forEach(button=>{
   const id=selectedProduct(button);
   const active=id?saved.has(String(id)):parents.has(button.dataset.product);
   button.classList.toggle('is-saved',active);button.setAttribute('aria-pressed',String(active));
   const label=button.querySelector('.pwr-save-label');const text=active?'Salvo':'Salvar na lista';
   if(label&&label.textContent!==text) label.textContent=text;
   button.title=active?'Remover dos favoritos':'Salvar na lista';
  });
  document.querySelectorAll(sideSelector).forEach(button=>{
   if(!button.dataset.pwrSideProduct)return;
   const active=saved.has(button.dataset.pwrSideProduct);
   button.classList.toggle('msc-is-favorite',active);
   if(button.getAttribute('aria-pressed')!==String(active))button.setAttribute('aria-pressed',String(active));
   button.setAttribute('aria-label',active?'Salvo — remover da lista':'Salvar na lista');
   button.title=active?'Salvo — remover da lista':'Salvar na lista';
   const icon=button.querySelector('i');if(icon){icon.classList.toggle('fa-solid',active);icon.classList.toggle('fa-regular',!active);}
   const label=button.querySelector('.pwr-save-label');if(label)label.textContent=active?'Salvo':'Salvar na lista';
  });
 }
 function sideItem(button){
  const key=button.closest('[data-msc-cart-item]')?.dataset.cartKey;
  return key?{cart_key:key}:{id:Number(button.dataset.productId||button.dataset.pwrProductId||0)};
 }
 async function resolveSide(buttons){
  const data=await request('resolve',{items:JSON.stringify(buttons.map(sideItem))});
  const products=new Map(data.products.map(product=>[Number(product.key),product]));
  buttons.forEach((button,index)=>{const product=products.get(index);button.dataset.pwrSideProduct=product&&!product.variable?String(product.id):'';});
  return products;
 }
 function scheduleSide(){
  clearTimeout(sideTimer);sideTimer=setTimeout(async()=>{
   const buttons=[...document.querySelectorAll(sideSelector)];if(!buttons.length)return;
   try{await ready();for(let offset=0;offset<buttons.length;offset+=100)await resolveSide(buttons.slice(offset,offset+100));await syncState();}catch{/* A click retries and displays any failure. */}
  },80);
 }
 // Capture recognized third-party hearts before their separate local-storage handler runs.
 document.addEventListener('click',async event=>{
  const button=event.target.closest(sideSelector);if(!button)return;
  event.preventDefault();event.stopImmediatePropagation();if(button.disabled||button.getAttribute('aria-busy')==='true')return;
  button.disabled=true;button.setAttribute('aria-busy','true');
  try{
   await ready();const product=(await resolveSide([button])).get(0);
   if(!product)throw new Error('Este item saiu do carrinho. Atualize o carrinho e tente novamente.');
   if(product.variable){announce('Abra o produto e escolha uma variação para salvar.');window.location.assign(product.url);return;}
   const data=await mutate('toggle',{product_id:product.id});await syncState();await loadList();announce(data.message,data.undo);
  }catch(error){announce(error.message);}finally{button.disabled=false;button.removeAttribute('aria-busy');}
 },true);
 async function syncState(){await ready();const data=await request('state');saved=new Set(data.ids.map(String));parents=new Set(data.parents.map(String));paintButtons();}
 function announce(message,undo='',cartUrl='') {
  let box=document.querySelector('.pwr-notice');
  if(!box){box=document.createElement('div');box.className='pwr-notice';box.setAttribute('role','status');document.body.append(box);}
  box.replaceChildren(document.createTextNode(message));
  if(undo){undoToken=undo;const button=document.createElement('button');button.type='button';button.className='pwr-undo';button.textContent='Desfazer';box.append(button);}
  if(cartUrl){const a=document.createElement('a');a.href=cartUrl;a.textContent='Ver carrinho';box.append(a);}
  clearTimeout(announce.timer);announce.timer=setTimeout(()=>box.remove(),undo?15000:7000);
 }
 async function loadList(focusProduct=null){
  const root=section();const items=root?.querySelector('.pwr-items');if(!items)return;
  const focusedInItems=!!focusProduct||items.contains(document.activeElement);const focusedProduct=focusProduct||document.activeElement.closest?.('.pwr-card')?.dataset.product;
  const ticket=++listRequest;items.setAttribute('aria-busy','true');
  try{
   await ready();
   const data=await request('list',{...listContext(),sort:document.querySelector('.pwr-sort')?.value||'newest'});
   if(ticket!==listRequest)return;
   items.innerHTML=data.html;if(focusedInItems){const target=(focusedProduct&&items.querySelector(`.pwr-card[data-product="${focusedProduct}"] button`))||items.querySelector('button,a');target?.focus({preventScroll:true});}root.classList.toggle('is-empty',data.count===0);
   root.classList.toggle('is-collection',!!data.contextual);root.classList.toggle('is-readonly',!!data.readonly);
   const count=root.querySelector('.pwr-count');if(count)count.textContent=`${data.count} ${data.count===1?'favorito':'favoritos'}`;
   if(data.readonly){root.querySelector('h2').textContent=data.name;if(data.description)root.querySelector('.pwr-list-header p').textContent=data.description;}
   const all=document.querySelector('.pwr-select-all');if(all)all.checked=false;
  }catch(error){if(ticket===listRequest){items.textContent=error.message;root.classList.remove('is-empty');}}
  finally{if(ticket===listRequest)items.setAttribute('aria-busy','false');}
 }
 function signalChange(){try{localStorage.setItem('portus_wr_changed',`${Date.now()}-${Math.random()}`);}catch{/* optional */}}
 async function mutate(action,values={}){await ready();const data=await request(action,values);signalChange();return data;}
 document.addEventListener('click',async event=>{
  const button=event.target.closest('.pwr-save,.pwr-remove,.pwr-undo,.pwr-bulk-remove,.pwr-cart');
  if(!button)return;event.preventDefault();button.disabled=true;
  try{
   const card=button.closest('.pwr-card');
   let data;
   if(button.matches('.pwr-save,.pwr-remove')){
    const removing=button.classList.contains('pwr-remove');const product=removing?card.dataset.product:selectedProduct(button);
    if(!product){announce('Escolha uma variação para salvar ou remover.');if(!document.querySelector('form.variations_form')&&!button.closest('.pwr-product-actions').querySelector('.pwr-unavailable'))window.location.assign(button.dataset.url);return;}
    data=await mutate(removing?'remove':'toggle',{product_id:product});await syncState();await loadList(product);announce(data.message,data.undo);
   }else if(button.matches('.pwr-undo')){
    data=await mutate('restore',{undo:undoToken});undoToken='';await syncState();await loadList();announce(data.message);
   }else if(button.matches('.pwr-bulk-remove')){
    if(!selected()){announce('Selecione pelo menos um produto.');return;}
    data=await mutate('bulk_remove',{ids:selected()});await syncState();await loadList('0');announce(data.message,data.undo);
   }else if(button.matches('.pwr-cart')){
    data=await mutate('cart',{product_id:button.dataset.product});announce(data.message,'',data.cart_url);
    if(window.jQuery)window.jQuery(document.body).trigger('wc_fragment_refresh');
    document.body.dispatchEvent(new CustomEvent('wc-blocks_added_to_cart',{bubbles:true}));
   }
  }catch(error){announce(error.message);}finally{button.disabled=false;}
 });
 document.addEventListener('change',event=>{
  if(event.target.closest('.variations_form,.pwr-unavailable'))setTimeout(paintButtons,0);
  if(event.target.matches('.pwr-sort'))loadList();
  if(event.target.matches('.pwr-select-all'))document.querySelectorAll('.pwr-select').forEach(input=>{input.checked=event.target.checked;});
 });
 async function init(){
  document.querySelectorAll('.pwr-no-js').forEach(notice=>notice.remove());
  if(document.querySelector('.pwr-save,.pwr-list')){syncState().catch(()=>{});loadList();}
  if(window.jQuery)window.jQuery(document).on('found_variation reset_data','.variations_form',()=>setTimeout(paintButtons,0));
  new MutationObserver(records=>{if(records.some(record=>[...record.addedNodes].some(node=>node.nodeType===1&&(node.matches('.pwr-save')||node.querySelector('.pwr-save')))))syncState().catch(()=>{});}).observe(document.body,{childList:true,subtree:true});
  scheduleSide();
  new MutationObserver(records=>{
   if(records.some(record=>record.type==='childList'&&[...record.addedNodes].some(node=>node.nodeType===1&&(node.matches(sideSelector)||node.querySelector(sideSelector)))))scheduleSide();
   if(records.some(record=>record.type==='attributes'&&record.target.matches(sideSelector)&&record.target.dataset.pwrSideProduct&&record.target.getAttribute('aria-pressed')!==String(saved.has(record.target.dataset.pwrSideProduct))))paintButtons();
  }).observe(document.body,{childList:true,subtree:true,attributes:true,attributeFilter:['aria-pressed']});
 }
 window.PortusWishlistAPI=Object.freeze({request:async(action,values)=>{await ready();return request(action,values);},sync:syncState,ready,mutate,announce,refreshList:loadList});
 window.addEventListener('storage',event=>{if(event.key==='portus_wr_changed'){syncState().catch(()=>{});loadList();}});
 window.addEventListener('pageshow',event=>{if(event.persisted){session=null;syncState().catch(()=>{});loadList();}});
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
