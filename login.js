// MODULE 1: Login - sliding tabs, live validation, show/hide password, strength meter,
// Caps Lock warning, remember-email, demo fill, loading button, rotating tips
const $=(s,r=document)=>r.querySelector(s),$$=(s,r=document)=>[...r.querySelectorAll(s)];
const f=$('#f'),card=$('#card'),mode=$('#mode'),go=$('#go'),tabsEl=$('.tabs'),tabs=$$('.tabs button'),rem=$('#rem');
const order=['student','register','admin'];
const T={
 student:{h:'Welcome back',s:'Sign in to reserve seats and manage your bookings.',b:'Sign in',w:'Signing in'},
 register:{h:'Create your account',s:'It takes under a minute. Use your student ID and email.',b:'Create account',w:'Creating account'},
 admin:{h:'Librarian console',s:'Staff sign-in to manage spaces, bookings and students.',b:'Sign in as librarian',w:'Signing in'}};
const F={no:$('#no'),name:$('#name'),email:$('#email'),password:$('#password'),password2:$('#password2')};
let touched=new Set(),emailAuto=false,busy=false;

// localStorage can be blocked, so every call is wrapped
const store={get:k=>{try{return localStorage.getItem(k)||''}catch(e){return ''}},set:(k,v)=>{try{v?localStorage.setItem(k,v):localStorage.removeItem(k)}catch(e){}}};

// ---------- validation ----------
const V={
 no:v=>v.trim()?'':'Enter your student ID.',
 name:v=>v.trim().length<2?'Enter your full name.':'',
 email:v=>!v.trim()?'Enter your email.':/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim())?'':'That email doesn\u2019t look right.',
 password:v=>!v?'Enter your password.':(mode.value=='register'&&v.length<6)?'Use at least 6 characters.':'',
 password2:v=>v!==F.password.value?'Passwords don\u2019t match yet.':''
};
const active=()=>Object.keys(F).filter(k=>F[k].offsetParent!==null);
function check(k,force){
 const fld=F[k].closest('.fld'),m=V[k](F[k].value),show=force||touched.has(k);
 fld.classList.toggle('bad',show&&!!m);fld.classList.toggle('good',show&&!m&&!!F[k].value);
 $('.fm',fld).textContent=show?(m||(k=='password2'&&F[k].value?'Passwords match.':'')):'';
}
Object.keys(F).forEach(k=>{
 F[k].addEventListener('input',()=>{
  if(k=='email')emailAuto=false;
  if(k=='password2')touched.add(k);
  if(touched.has(k))check(k);
  if(k=='password'){meter();if(F.password2.value)check('password2',true)}
 });
 F[k].addEventListener('blur',()=>{if(F[k].offsetParent!==null&&(F[k].value||touched.has(k))){touched.add(k);check(k)}});
});

// ---------- password strength ----------
function meter(){
 const v=F.password.value,r={len:v.length>=8,case:/[a-z]/.test(v)&&/[A-Z]/.test(v),num:/\d/.test(v),sym:/[^A-Za-z0-9]/.test(v)};
 $$('#rules li').forEach(li=>li.classList.toggle('ok',!!r[li.dataset.r]));
 let s=Object.values(r).filter(Boolean).length;
 if(v.length<6)s=Math.min(s,1);if(v)s=Math.max(s,1);else s=0;
 $('#meter').dataset.s=s;
 $('#sl').textContent=v?'Strength: '+['','Weak','Fair','Good','Strong'][s]:'Choose a password with at least 6 characters.';
}

// ---------- show / hide password ----------
$$('.eye').forEach(b=>b.onclick=()=>{
 const i=$('input',b.parentNode),show=i.type=='password';
 i.type=show?'text':'password';b.classList.toggle('on',show);b.setAttribute('aria-label',show?'Hide password':'Show password');i.focus();
});

// ---------- Caps Lock warning ----------
[F.password,F.password2].forEach(i=>{
 const n=$('.caps',i.closest('.fld'));
 ['keydown','keyup'].forEach(t=>i.addEventListener(t,e=>{n.hidden=!(e.getModifierState&&e.getModifierState('CapsLock'))}));
 i.addEventListener('blur',()=>n.hidden=true);
});

// ---------- switching between Student / Create account / Librarian ----------
function set(v,focus,init){
 if(!T[v])v='student';
 mode.value=v;document.body.dataset.m=v;tabsEl.style.setProperty('--i',order.indexOf(v));
 tabs.forEach(b=>{const on=b.dataset.m==v;b.classList.toggle('on',on);b.setAttribute('aria-selected',on);b.tabIndex=on?0:-1});
 $('#h2').textContent=T[v].h;$('#sub').textContent=T[v].s;go.textContent=T[v].b;
 const hd=$('.head');hd.classList.remove('swap');void hd.offsetWidth;hd.classList.add('swap');
 F.password.autocomplete=v=='register'?'new-password':'current-password';
 if(!init){
  $$('.srv').forEach(x=>x.remove());
  touched.clear();$$('.fld').forEach(x=>x.classList.remove('bad','good'));$$('.fm').forEach(x=>x.textContent='');
  F.password.value=F.password2.value='';
  const u=new URL(location.href);u.searchParams.set('mode',v);history.replaceState(null,'',u);
 }
 // remembered email is stored separately for student and librarian
 if(v!='register'&&(emailAuto||!F.email.value)){const s=store.get('lsrs_email_'+v);F.email.value=s;emailAuto=!!s;rem.checked=!!s}
 else if(v!='register')rem.checked=!!store.get('lsrs_email_'+v);
 meter();
 if(focus)setTimeout(()=>(v=='register'?F.no:F.email).focus(),50);
}
tabs.forEach(b=>b.onclick=()=>set(b.dataset.m,true));
tabs.forEach((b,i)=>b.addEventListener('keydown',e=>{
 const d=e.key=='ArrowRight'?1:e.key=='ArrowLeft'?-1:0;if(!d)return;
 const n=tabs[(i+d+tabs.length)%tabs.length];set(n.dataset.m);n.focus();e.preventDefault();
}));
$$('[data-go]').forEach(b=>b.onclick=()=>set(b.dataset.go,true));

// ---------- demo librarian login ----------
$('#demo').onclick=()=>{F.email.value='admin@library.local';F.password.value='admin123';emailAuto=false;check('email',true);check('password',true);go.focus()};

// ---------- submit: validate, remember email, show loading state ----------
const shake=()=>{card.classList.remove('shake');void card.offsetWidth;card.classList.add('shake')};
f.addEventListener('submit',ev=>{
 if(busy){ev.preventDefault();return}
 const ks=active(),bad=ks.filter(k=>V[k](F[k].value));
 ks.forEach(k=>{touched.add(k);check(k,true)});
 if(bad.length){ev.preventDefault();F[bad[0]].focus();shake();return}
 if(mode.value!='register')store.set('lsrs_email_'+mode.value,rem.checked?F.email.value.trim():'');
 busy=true;go.classList.add('load');go.textContent=T[mode.value].w;go.setAttribute('aria-busy','true');
});
addEventListener('pageshow',()=>{busy=false;go.classList.remove('load');go.removeAttribute('aria-busy');go.textContent=T[mode.value].b});

// ---------- rotating tips on the art panel ----------
const tips=['Tip: pick your exact seat on the seat map when you book.','Quiet pods are best for focused solo work.','Need a group space? Try a discussion room.','Presentation studios are great for rehearsing before the big day.'];
const tip=$('#tip');let ti=0;
if(tip){tip.textContent=tips[0];setInterval(()=>{tip.classList.add('out');setTimeout(()=>{ti=(ti+1)%tips.length;tip.textContent=tips[ti];tip.classList.remove('out')},400)},5500)}

// ---------- go ----------
set(mode.value,false,true);
if($('.msg.bad'))shake();
