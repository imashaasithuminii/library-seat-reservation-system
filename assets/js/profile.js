// MODULE 3: Profile - tabs, count-up stats, edit mode with live validation, completeness ring,
// phone formatting, password strength, password generator, toast messages
const $=(s,r=document)=>r.querySelector(s),$$=(s,r=document)=>[...r.querySelectorAll(s)];
const reduce=matchMedia('(prefers-reduced-motion: reduce)').matches;

// ---------- toast ----------
const tt=$('#ptoast');let tmr;
function toast(m,t='ok'){tt.textContent=m;tt.className='ptoast '+t+' show';clearTimeout(tmr);tmr=setTimeout(()=>tt.classList.remove('show'),4000)}
if(tt.dataset.ok)toast(tt.dataset.ok);
const shake=el=>{el.classList.remove('shake');void el.offsetWidth;el.classList.add('shake')};
$$('.msg.bad').forEach(m=>shake(m.closest('.pcard')));

// ---------- tabs (Personal details / Security), remembered in the URL hash ----------
const tabs=$$('.dtabs button'),panels=$$('.tp');
function show(){
 const h=location.hash.slice(1),t=panels.some(p=>p.id==h)?h:(document.body.dataset.tab||'details');
 panels.forEach(p=>p.classList.toggle('on',p.id==t));
 tabs.forEach(b=>{const on=b.dataset.t==t;b.classList.toggle('on',on);b.setAttribute('aria-selected',on)});
}
tabs.forEach(b=>b.onclick=()=>{history.replaceState(null,'','#'+b.dataset.t);show()});
addEventListener('hashchange',show);show();

// ---------- count-up stats ----------
$$('[data-count]').forEach(el=>{
 const n=+el.dataset.count;if(reduce||!n)return;
 const t0=performance.now(),d=900;el.textContent='0';
 (function step(t){const k=Math.min(1,(t-t0)/d);el.textContent=Math.round(n*(1-Math.pow(1-k,3)));if(k<1)requestAnimationFrame(step)})(t0);
});

// ---------- password strength helper (shared with the Security tab) ----------
function strength(v){
 const r={len:v.length>=8,case:/[a-z]/.test(v)&&/[A-Z]/.test(v),num:/\d/.test(v),sym:/[^A-Za-z0-9]/.test(v)};
 let s=Object.values(r).filter(Boolean).length;if(v.length<6)s=Math.min(s,1);s=v?Math.max(s,1):0;return{r,s};
}

// ---------- show / hide password buttons ----------
$$('.eye').forEach(b=>b.onclick=()=>{
 const i=$('input',b.parentNode),on=i.type=='password';
 i.type=on?'text':'password';b.classList.toggle('on',on);b.setAttribute('aria-label',on?'Hide password':'Show password');i.focus();
});

/* =============== PERSONAL DETAILS =============== */
const form=$('#info'),orig=JSON.parse(form.dataset.orig),keys=['name','email','phone','programme'];
const I={name:$('#i-name'),email:$('#i-email'),phone:$('#i-phone'),programme:$('#i-prog')};
const labels={name:'full name',email:'email address',phone:'phone number',programme:'degree programme'};
const saveB=$('#save'),cancelB=$('#cancel'),editB=$('#edit'),dirtyEl=$('#dirty');
let editing=false,submitting=false;
const touched=new Set();

const RULE={
 name:v=>v.trim().length<2?'Enter your full name.':'',
 email:v=>!v.trim()?'Enter your email.':/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim())?'':'That email doesn\u2019t look right.',
 phone:v=>!v.trim()||/^[0-9+\-\s()]{7,20}$/.test(v.trim())?'':'Use digits, spaces, + or - (7\u201320 characters).',
 programme:()=>''
};
function check(k,force){
 const fld=I[k].closest('.fld'),m=RULE[k](I[k].value),sh=editing&&(force||touched.has(k));
 fld.classList.toggle('bad',sh&&!!m);fld.classList.toggle('good',sh&&!m&&!!I[k].value.trim()&&I[k].value!==orig[k]);
 $('.fm',fld).textContent=sh?m:'';
}
const isDirty=()=>keys.some(k=>I[k].value.trim()!==orig[k]);
const isValid=()=>keys.every(k=>!RULE[k](I[k].value));

// live avatar, name and completeness ring (updates as you type)
function live(){
 const nm=I.name.value.trim()||orig.name;
 $('#pname').textContent=nm;$('#av').textContent=(nm[0]||'?').toUpperCase();
 const filled=keys.filter(k=>I[k].value.trim()).length,pct=Math.round(filled/4*100);
 $('#ring').style.setProperty('--p',pct);$('#pct').textContent=pct+'%';
 const miss=keys.find(k=>!I[k].value.trim()),n=$('#nudge');
 if(miss){n.textContent='Add your '+labels[miss]+' to complete your profile \u2192';n.classList.remove('done');n.dataset.k=miss}
 else{n.textContent='\u2713 Your profile is complete';n.classList.add('done');delete n.dataset.k}
 const d=editing&&isDirty();dirtyEl.hidden=!d;saveB.disabled=!(d&&isValid());
}
function setEdit(on,focusKey){
 editing=on;keys.forEach(k=>I[k].readOnly=!on);
 saveB.hidden=cancelB.hidden=!on;editB.hidden=on;
 if(!on){touched.clear();$$('.fld',form).forEach(f=>f.classList.remove('bad','good'));$$('.fm',form).forEach((m,i)=>{if(!m.closest('.fld').querySelector('#i-no'))m.textContent=''})}
 live();
 if(on)I[focusKey||'name'].focus();
}
editB.onclick=()=>setEdit(true);
$('#nudge').onclick=e=>{const k=e.currentTarget.dataset.k;if(!k)return;history.replaceState(null,'','#details');show();setEdit(true,k)};
cancelB.onclick=()=>{keys.forEach(k=>I[k].value=orig[k]);setEdit(false)};
addEventListener('keydown',e=>{if(e.key=='Escape'&&editing&&$('#details').classList.contains('on'))cancelB.click()});

// phone: format 07X XXX XXXX while typing (leaves + numbers alone)
I.phone.addEventListener('input',()=>{
 const v=I.phone.value;if(!/^[0-9\s]*$/.test(v))return;
 const d=v.replace(/\D/g,'');if(d[0]!='0')return;
 I.phone.value=d.slice(0,10).replace(/^(\d{0,3})(\d{0,3})(\d{0,4}).*/,(m,a,b,c)=>[a,b,c].filter(Boolean).join(' '));
});
keys.forEach(k=>{
 I[k].addEventListener('input',()=>{if(touched.has(k))check(k);live()});
 I[k].addEventListener('blur',()=>{if(editing){touched.add(k);check(k)}});
});

form.addEventListener('submit',e=>{
 if(!editing||submitting){e.preventDefault();return}
 const bad=keys.filter(k=>RULE[k](I[k].value));
 keys.forEach(k=>{touched.add(k);check(k,true)});
 if(bad.length){e.preventDefault();I[bad[0]].focus();shake($('#details'));return}
 if(!isDirty()){e.preventDefault();toast('Nothing to save yet.','err');return}
 submitting=true;saveB.classList.add('load');saveB.textContent='Saving';
});
addEventListener('beforeunload',e=>{if(editing&&isDirty()&&!submitting){e.preventDefault();e.returnValue=''}});
addEventListener('pageshow',()=>{submitting=false;saveB.classList.remove('load');saveB.textContent='Save changes'});

// server error on the details form: reopen in edit mode with what was typed
if(form.dataset.edit=='1'){setEdit(true);keys.forEach(k=>touched.add(k));keys.forEach(k=>check(k,true))}else live();

/* =============== SECURITY =============== */
const pf=$('#pwform'),cur=$('#p-cur'),n1=$('#p-new'),n2=$('#p-new2'),pwgo=$('#pwgo');
const pt=new Set(),pfld=i=>i.closest('.fld'),setMsg=(i,m,ok)=>{const f=pfld(i);f.classList.toggle('bad',!!m&&!ok);f.classList.toggle('good',!!ok);$('.fm',f).textContent=m||''};
function pwCheck(force){
 const e1=n1.value&&n1.value.length<6?'Use at least 6 characters.':(n1.value&&n1.value===cur.value&&cur.value?'New password must be different from the current one.':'');
 if(force||pt.has('n1'))setMsg(n1,e1,n1.value&&!e1);
 if(n2.value||force){const bad=n2.value!==n1.value;setMsg(n2,bad?'Passwords don\u2019t match yet.':'Passwords match.',!bad&&!!n2.value)}
 const {r,s}=strength(n1.value);
 $$('#rules li').forEach(li=>li.classList.toggle('ok',!!r[li.dataset.r]));
 $('#meter').dataset.s=s;
 $('#sl').textContent=n1.value?'Strength: '+['','Weak','Fair','Good','Strong'][s]:'Choose a password with at least 6 characters.';
 pwgo.disabled=!(cur.value&&n1.value.length>=6&&n1.value===n2.value&&n1.value!==cur.value);
}
[cur,n1,n2].forEach(i=>i.addEventListener('input',()=>pwCheck()));
n1.addEventListener('blur',()=>{if(n1.value){pt.add('n1');pwCheck()}});
// Caps Lock warning
[cur,n1,n2].forEach(i=>{const n=$('.caps',pfld(i));if(!n)return;['keydown','keyup'].forEach(t=>i.addEventListener(t,e=>{n.hidden=!(e.getModifierState&&e.getModifierState('CapsLock'))}));i.addEventListener('blur',()=>n.hidden=true)});
// suggest a strong password (browser crypto), shows it so you can note it down before saving
$('#gen').onclick=()=>{
 const sets=['abcdefghijkmnopqrstuvwxyz','ABCDEFGHJKLMNPQRSTUVWXYZ','23456789','!@#$%^&*?'],all=sets.join('');
 const r=n=>{const a=new Uint32Array(1);crypto.getRandomValues(a);return a[0]%n};
 const p=sets.map(s=>s[r(s.length)]);while(p.length<14)p.push(all[r(all.length)]);
 for(let i=p.length-1;i>0;i--){const j=r(i+1);[p[i],p[j]]=[p[j],p[i]]}
 n1.value=n2.value=p.join('');pt.add('n1');
 [n1,n2].forEach(i=>{i.type='text';const b=$('.eye',i.parentNode);b.classList.add('on');b.setAttribute('aria-label','Hide password')});
 pwCheck(true);
 let copied=false;try{navigator.clipboard.writeText(n1.value);copied=true}catch(e){}
 toast(copied?'Strong password filled in and copied. Save it somewhere safe.':'Strong password filled in. Note it down before saving.');
};
pf.addEventListener('submit',e=>{
 pwCheck(true);
 if(pwgo.disabled){e.preventDefault();shake($('#security'));(!cur.value?cur:n1.value.length<6?n1:n2).focus();return}
 pwgo.classList.add('load');pwgo.textContent='Updating';
});
addEventListener('pageshow',()=>{pwgo.classList.remove('load');pwgo.textContent='Update password'});
pwCheck();
