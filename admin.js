// MODULE 4: Admin - live search in the bookings table
const q=document.getElementById('q');if(q)q.oninput=()=>{const v=q.value.toLowerCase();document.querySelectorAll('#bt tr:not(:first-child)').forEach(r=>r.hidden=!r.textContent.toLowerCase().includes(v))};
