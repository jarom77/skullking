const apiUrl = 'api.php';
let USERID = '';
const statusEl = document.getElementById('status');
document.getElementById('set-id').addEventListener('click', ()=>{
  const v = document.getElementById('userid').value.trim();
  if(!v) return alert('Enter a user id');
  USERID = v;
  localStorage.setItem('sk_userid', USERID);
  statusEl.textContent = 'id set: ' + USERID;
  refresh();
});

const stored = localStorage.getItem('sk_userid');
if(stored){ USERID = stored; document.getElementById('userid').value = stored; statusEl.textContent = 'id set: ' + USERID; }

document.getElementById('submit-bid').addEventListener('click', async ()=>{
  if(!USERID) return alert('Set your userid first');
  const bid = parseInt(document.getElementById('bid-val').value,10);
  if(isNaN(bid)) return alert('Enter a bid number');
  const fd = new URLSearchParams();
  fd.append('action','submit_bid');
  fd.append('userid', USERID);
  fd.append('bid', bid);
  try{
    const res = await fetch(apiUrl, { method:'POST', body:fd });
    const j = await res.json();
    if(j.ok) alert('Bid saved'); else alert('Error: '+(j.error||j.message||JSON.stringify(j)));
  }catch(e){ alert('Network error'); }
});

async function playCard(number,color){
  if(!USERID) return alert('Set your userid first');
  if(!confirm('Play card ' + number + ' / ' + color + '?')) return;
  const fd = new URLSearchParams();
  fd.append('action','play_card');
  fd.append('userid', USERID);
  fd.append('number', number);
  fd.append('color', color);
  try{
    const res = await fetch(apiUrl, { method:'POST', body:fd });
    const j = await res.json();
    if(j.ok) { refresh(); } else alert('Play failed: ' + (j.message || j.error || JSON.stringify(j)));
  }catch(e){ alert('Network error'); }
}

function renderCards(listEl, cards, cls){
  listEl.innerHTML = '';
  cards.forEach(c=>{
    const d = document.createElement('div'); d.className = 'card ' + cls;
    d.textContent = c.number + ' • ' + c.color + (c.userid?(' ('+c.userid+')'):'');
    if(cls==='hand') d.addEventListener('click', ()=> playCard(c.number, c.color));
    listEl.appendChild(d);
  });
}

async function refresh(){
  const qs = new URLSearchParams();
  qs.append('action','get_state');
  if(USERID) qs.append('userid', USERID);
  try{
    const res = await fetch(apiUrl + '?' + qs.toString());
    const j = await res.json();
    renderCards(document.getElementById('in-play-list'), j.in_play || [], 'in-play');
    renderCards(document.getElementById('hand-list'), j.in_hand || [], 'hand');
    document.getElementById('tricks').textContent = (j.tricks || 0);
  }catch(e){ console.error('refresh failed', e); }
}

// Poll for live updates every 1500ms
setInterval(refresh, 1500);
// Initial
refresh();
