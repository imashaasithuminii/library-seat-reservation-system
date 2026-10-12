// SHARED: live clock, back-to-top, header shadow, confirm dialogs (form[data-confirm])
const ck=document.getElementById('clock');
if(ck){const t=()=>{const d=new Date();ck.dataset.open=d.getHours()>=9&&d.getHours()<17;ck.textContent=d.toLocaleDateString('en-GB',{weekday:'short',day:'numeric',month:'short',year:'numeric'})+', '+d.toLocaleTimeString('en-US')+(ck.dataset.open=='true'?' (open)':' (closed)')};t();setInterval(t,1000)}
const tp=document.getElementById('top');if(tp){addEventListener('scroll',()=>tp.classList.toggle('show',scrollY>400));tp.onclick=()=>scrollTo({top:0,behavior:'smooth'})}
const nv=document.querySelector('.nav');if(nv)addEventListener('scroll',()=>nv.classList.toggle('sc',scrollY>10));
document.querySelectorAll('form[data-confirm]').forEach(f=>f.addEventListener('submit',e=>{if(!confirm(f.dataset.confirm))e.preventDefault()}));
addEventListener('pageshow',e=>{if(e.persisted)location.reload()});   // Back button never shows a stale signed-in page
const ts=document.getElementById('toast');if(ts){history.replaceState(null,'',location.pathname);setTimeout(()=>ts.classList.add('hide'),3500)}
