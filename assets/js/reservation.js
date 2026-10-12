
/* =========================================================
   MODULE 2: RESERVATION
   - Space filters
   - Reservation dialog
   - Time-slot selection
   - Seat-map selection
   - Booking submission
   - Hero count-up animation
   - Card reveal animation
   - Live hero clock
   ========================================================= */


/* =========================================================
   1. HELPER FUNCTIONS
   ========================================================= */

// Select the first matching element.
const $ = s => document.querySelector(s);

// Select all matching elements and return them as an array.
const $$ = s => [...document.querySelectorAll(s)];


/* =========================================================
   2. SPACE FILTERS
   ========================================================= */

function filter() {
    // Collect the selected values from all filters.
    const f = {};

    $$('[data-f]').forEach(s => {
        f[s.dataset.f] = s.value;
    });

    let n = 0;

    // Check every space card against the selected filters.
    $$('.card').forEach(c => {
        let ok = true;

        for (const k in f) {
            const v = f[k];

            // Ignore filters that have no selected value.
            if (!v) continue;

            // Filter by capacity range.
            if (k === 'cap') {
                const [a, b] = v.split('-').map(Number);

                ok &&= c.dataset.cap >= a && c.dataset.cap <= b;
            } else {
                // Match the selected filter with the card's data attribute.
                ok &&= c.dataset[k] === v;
            }
        }

        // Hide cards that do not match the filters.
        c.hidden = !ok;

        if (ok) n++;
    });

    // Update the number of matching spaces.
    $('#count').textContent =
        n + (n === 1 ? ' space found' : ' spaces found');

    // Show the empty-state message when no cards match.
    $('#none').hidden = n > 0;
}

// Apply the filters whenever a filter value changes.
$$('[data-f]').forEach(s => {
    s.onchange = filter;
});

// Clear all filters and show all spaces again.
$('#clear').onclick = () => {
    $$('[data-f]').forEach(s => {
        s.value = '';
    });

    filter();
};

// Apply the initial filter state.
filter();


/* =========================================================
   3. RESERVATION DIALOG AND TIME-SLOT VARIABLES
   ========================================================= */

// Main reservation dialog and booking form.
const dlg = $('#dlg');
const bf = $('#bf');

// Convert a 24-hour number into a readable time.
const H = h =>
    (h % 12 || 12) + ':00 ' + (h < 12 ? 'AM' : 'PM');

// Convert a Date object into YYYY-MM-DD using local time.
const iso = d =>
    new Date(d - d.getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 10);

// Store the currently selected space ID.
let sid;

// Store the selected time slots.
let sel = [];

// Store time slots that are already reserved.
let taken = [];


/* =========================================================
   4. LOAD RESERVED TIME SLOTS
   ========================================================= */

async function load() {
    // Request the reserved slots for this space and date.
    taken = await (
        await fetch(
            `reservation_api.php?a=slots&space=${sid}&date=${bf.date.value}`
        )
    ).json();

    // Clear the current time-slot selection.
    sel = [];

    // Redraw the available time slots.
    draw();
}


/* =========================================================
   5. START-TIME AND END-TIME SELECTION
   ========================================================= */

// Drop-down lists where the student picks the start and end time.
const stSel = $('#t-start');
const enSel = $('#t-end');

// Build the start-time list (9:00 AM to 4:00 PM).
function draw() {
    const n = new Date();

    // Check whether the selected date is today.
    const today = bf.date.value === iso(n);

    let free = 0;
    let opts = '';

    for (let h = 9; h < 17; h++) {
        // Determine whether this hour can be chosen.
        const off = taken.includes(h);
        const past = today && h <= n.getHours();

        if (!off && !past) free++;

        // Reserved or passed hours are shown but cannot be picked.
        opts += `
            <option value="${h}" ${off || past ? 'disabled' : ''}>
                ${H(h)}${off ? ' (Reserved)' : past ? ' (Passed)' : ''}
            </option>
        `;
    }

    stSel.innerHTML =
        `<option value="">${free ? 'Select start time' : 'No times available'}</option>` +
        opts;

    // Rebuild the end-time list and clear the seat map.
    fillEnd();
    seatsRefresh();
}

// Build the end-time list based on the chosen start time.
function fillEnd() {
    const s = +stSel.value;

    enSel.innerHTML = '<option value="">Select end time</option>';

    // The end time stays locked until a start time is chosen.
    enSel.disabled = !stSel.value;

    if (!stSel.value) return;

    // The end time can be 1 to 4 hours after the start time,
    // and cannot be later than 5:00 PM.
    for (let e = s + 1; e <= Math.min(s + 4, 17); e++) {
        const o = document.createElement('option');

        o.value = e;
        o.textContent =
            H(e) + ' (' + (e - s) + (e - s === 1 ? ' hour)' : ' hours)');

        enSel.append(o);

        // A reserved hour blocks any later end time.
        if (taken.includes(e)) break;
    }
}

// When the start time changes, rebuild the end times.
stSel.onchange = () => {
    fillEnd();
    sel = [];
    seatsRefresh();
};

// When the end time changes, list the booked hours and show the seats.
enSel.onchange = () => {
    sel = [];

    if (enSel.value) {
        for (let h = +stSel.value; h < +enSel.value; h++) {
            sel.push(h);
        }
    }

    seatsRefresh();
};


/* =========================================================
   6. OPEN THE RESERVATION DIALOG
   ========================================================= */

$$('.reserve').forEach(b => {
    b.onclick = () => {
        // Redirect users who are not logged in.
        if (!STU) {
            return location = 'login.php?next=reserve';
        }

        // Read the selected space's information from the button.
        const d = b.dataset;

        sid = d.id;

        // Display the space information in the dialog.
        $('#dt').textContent = d.name;
        $('#d-floor').textContent = d.floor;
        $('#d-cap').textContent = d.cap + ' people';
        $('#d-sound').textContent = d.sound;
        $('#d-ut').textContent = d.ut;
        $('#d-fac').textContent = d.fac || 'Not listed';

        // Display the image if the space has one.
        const im = $('#mi');

        im.hidden = !d.img;

        if (d.img) {
            im.src = d.img;
        }

        // Show the placeholder when there is no image.
        $('#mph').hidden = !!d.img;

        // Reset the form and previous booking messages.
        bf.purpose.value = '';
        bf.querySelector('[type=checkbox]').checked = false;

        $('#bm').hidden = true;
        $('#ok').hidden = false;

        // Allow reservations from today up to seven days ahead.
        const t = new Date();

        bf.date.min = iso(t);
        bf.date.max = iso(new Date(+t + 7 * 864e5));
        bf.date.value = iso(t);

        // Load the slots and display the dialog.
        load();

        dlg.showModal();
    };
});


/* =========================================================
   7. HANDLE DATE CHANGES AND DIALOG CLOSING
   ========================================================= */

// Reload the time slots whenever the date changes.
bf.date.onchange = load;

// Close the dialog using the close and cancel buttons.
[$('#x'), $('#c')].forEach(el => {
    el.onclick = () => {
        dlg.close();

        // Reload the page if the successful-booking state is active.
        if ($('#ok').hidden) {
            location.reload();
        }
    };
});


/* =========================================================
   7.5 BOOKING CONFIRMATION POP-UP
   ========================================================= */

// Pop-up shown after a reservation is saved successfully.
const okdlg = $('#okdlg');

// Turn YYYY-MM-DD into a readable date, e.g. "Sat, Oct 11, 2026".
const niceDate = v => {
    const [y, mo, da] = v.split('-').map(Number);

    return new Date(y, mo - 1, da).toLocaleDateString('en-US', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

// Close the pop-up with the close (x) and Done buttons.
[$('#ok-x'), $('#ok-done')].forEach(el => {
    el.onclick = () => okdlg.close();
});

// Reload the page whenever the pop-up closes (including the Esc key)
// so the availability on the page is refreshed.
okdlg.onclose = () => location.reload();


/* =========================================================
   8. SUBMIT A RESERVATION
   ========================================================= */

bf.onsubmit = async e => {
    // Stop the browser from submitting the form normally.
    e.preventDefault();

    const m = $('#bm');

    // Make the booking message area visible.
    m.hidden = false;

    // Validate that at least one time slot was selected.
    if (!sel.length) {
        m.className = 'msg bad';
        m.textContent = 'Select a start time and an end time.';
        return;
    }

    // Validate that at least one seat was selected.
    if (!pick.length) {
        m.className = 'msg bad';
        m.textContent = 'Choose at least one seat on the map.';
        return;
    }

    // Collect the booking form data.
    const d = new FormData(bf);

    // Add the action and reservation details.
    d.set('a', 'book');
    d.set('seats', pick.join(','));
    d.set('space', sid);
    d.set('start', sel[0]);
    d.set('end', sel.at(-1) + 1);

    // Save the chosen start and end hours for the confirmation message.
    const startH = sel[0];
    const endH = sel.at(-1) + 1;

    // Send the booking request to the PHP API.
    const r = await fetch('reservation_api.php', {
        method: 'POST',
        body: d
    });

    const j = await r.json();

    if (r.ok) {
        // Fill the confirmation pop-up with the booking details.
        $('#ok-id').textContent = j.id;
        $('#ok-space').textContent = j.space;
        $('#ok-date').textContent = niceDate(bf.date.value);
        $('#ok-start').textContent = H(startH);
        $('#ok-end').textContent = H(endH);
        $('#ok-seats').textContent = j.seats;

        // Close the reservation pop-up and open the confirmation pop-up.
        m.hidden = true;
        dlg.close();
        okdlg.showModal();
    } else {
        // Display the error returned by the server.
        m.className = 'msg bad';
        m.textContent = j.error;

        // Reload availability if another user took the selected slots.
        if (r.status === 409) {
            load();
        }
    }
};


/* =========================================================
   9. HERO COUNT-UP ANIMATION
   ========================================================= */

document.querySelectorAll('[data-n]').forEach(el => {
    // Read the target number from the data-n attribute.
    const n = +el.dataset.n;

    // Respect the user's reduced-motion preference.
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) {
        el.textContent = n;
        return;
    }

    // Record the animation's starting time.
    const t0 = performance.now();

    // Animate the number over 1.2 seconds.
    const f = t => {
        const p = Math.min((t - t0) / 1200, 1);

        // Ease out so the animation slows near the end.
        el.textContent = Math.round(
            n * (1 - Math.pow(1 - p, 3))
        );

        // Continue until the animation finishes.
        if (p < 1) {
            requestAnimationFrame(f);
        }
    };

    requestAnimationFrame(f);
});


/* =========================================================
   10. REVEAL SPACE CARDS WHEN THEY ENTER THE VIEW
   ========================================================= */

if (
    'IntersectionObserver' in window &&
    !matchMedia('(prefers-reduced-motion: reduce)').matches
) {
    // Observe cards and reveal each one when it becomes visible.
    const io = new IntersectionObserver(
        es => es.forEach(e => {
            if (e.isIntersecting) {
                e.target.classList.add('in');

                // Stop observing a card after revealing it.
                io.unobserve(e.target);
            }
        }),
        { threshold: 0.1 }
    );

    document.querySelectorAll('.card').forEach((c, i) => {
        // Add the initial reveal class.
        c.classList.add('rv');

        // Stagger the animation slightly between cards.
        c.style.transitionDelay = Math.min(i % 6, 5) * 60 + 'ms';

        io.observe(c);
    });
}


/* =========================================================
   11. LIVE HOME HERO CLOCK
   - Greeting
   - Analog clock
   - Full date
   - Digital time
   - Library open/closed status
   ========================================================= */

(() => {
    const g = $('#hcg');

    // Stop if this page does not contain the hero clock.
    if (!g) return;

    const ns = 'http://www.w3.org/2000/svg';
    const t = $('#hct');

    // Create 60 marks around the analog clock.
    for (let i = 0; i < 60; i++) {
        const l = document.createElementNS(ns, 'line');
        const M = i % 5 === 0;

        // Set the mark's starting and ending coordinates.
        l.setAttribute('x1', 100);
        l.setAttribute('x2', 100);
        l.setAttribute('y1', 8);
        l.setAttribute('y2', M ? 17 : 12);

        // Rotate each mark around the clock's center.
        l.setAttribute(
            'transform',
            `rotate(${i * 6} 100 100)`
        );

        // Apply a different class to major hour marks.
        l.setAttribute('class', M ? 'maj' : 'min');

        t.append(l);
    }

    // Rotate a clock hand to the given angle.
    const rot = (id, a) => {
        $(id).setAttribute(
            'transform',
            `rotate(${a} 100 100)`
        );
    };

    // Update all clock information.
    const tick = () => {
        const d = new Date();

        const h = d.getHours();
        const m = d.getMinutes();
        const s = d.getSeconds();

        // Display a greeting based on the current hour.
        g.textContent =
            (h < 12
                ? 'Good morning'
                : h < 17
                    ? 'Good afternoon'
                    : 'Good evening') + '!';

        // Update the analog clock hands.
        rot('#hch', (h % 12 + m / 60) * 30);
        rot('#hcm', (m + s / 60) * 6);
        rot('#hcs', s * 6);

        // Display the full current date.
        $('#hcd').textContent = d.toLocaleDateString(
            'en-US',
            {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }
        );

        // Display the digital time.
        const p = d.toLocaleTimeString(
            'en-US',
            {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            }
        ).split(/\s/);

        $('#hcc').innerHTML =
            `${p[0]} <small>${p[1] || ''}</small>`;

        // The library is considered open from 9 AM to 5 PM.
        const o = h >= 9 && h < 17;
        const c = $('#hco');

        c.dataset.open = o;
        c.textContent = o
            ? 'Library is open now'
            : 'Library is closed now (opens 9:00 AM)';
    };

    // Update immediately and then every second.
    tick();
    setInterval(tick, 1000);
})();


/* =========================================================
   12. SEAT-MAP VARIABLES AND SELECTION DISPLAY
   ========================================================= */

// Store the selected seat IDs.
let pick = [];

// Maximum number of seats a user can select.
let mx = 10;

// Update the text showing the selected seats.
function sync() {
    $('#sel').textContent = pick.length
        ? pick.join(', ') + ' (' + pick.length + ')'
        : 'None yet, up to ' + mx;
}


/* =========================================================
   13. GENERATE THE SEAT MAP
   ========================================================= */

function drawMap(j) {
    // Read the layout type supplied by the server.
    const k = j.kind;

    // Generate a button for an individual seat.
    const S = s => {
        const x = j.taken.includes(s);

        return `
            <button
                type="button"
                class="seat k-${k}${x ? ' x' : ''}"
                data-s="${s}"
                ${x ? 'disabled' : ''}
                title="Seat ${s}${x ? ' (taken)' : ''}"
            >${s.slice(1)}</button>
        `;
    };

    // Add the front desk or front label when provided.
    let h = j.front
        ? `<div class="desk">${j.front}</div>`
        : '';

    // Layout for tables.
    if (k === 'table' || k === 'tables') {
        h += `
            <div class="tgrid">
                ${j.blocks.map(b => {
                    const m = Math.ceil(b.length / 2);

                    return `
                        <div class="tbl2">
                            <div class="tside">
                                ${b.slice(0, m).map(S).join('')}
                            </div>

                            <div class="tname">
                                ${k === 'table'
                                    ? 'TABLE'
                                    : 'Table ' + b[0][0]}
                            </div>

                            <div class="tside">
                                ${b.slice(m).map(S).join('')}
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;

    // Layout for sofas.
    } else if (k === 'sofa') {
        h += `
            <div class="tgrid">
                ${j.blocks.map(b => `
                    <div class="sofa">
                        <div>${b.map(S).join('')}</div>
                        <small>Sofa ${b[0][0]}</small>
                    </div>
                `).join('')}
            </div>
        `;

    // Default layout for rows of seats.
    } else {
        h += j.blocks.map(row => {
            const m = Math.ceil(row.length / 2);

            return `
                <div class="srow">
                    <b>${row[0][0]}</b>

                    <div>
                        ${row.map((s, i) =>
                            (i === m && row.length >= 6
                                ? '<span class="aisle"></span>'
                                : '') + S(s)
                        ).join('')}
                    </div>
                </div>
            `;
        }).join('');
    }

    // Add the seat-availability legend.
    return h + `
        <div class="leg">
            <span><i class="seat"></i>Available</span>
            <span><i class="seat on"></i>Selected</span>
            <span><i class="seat x"></i>Taken</span>
        </div>
    `;
}


/* =========================================================
   14. REFRESH SEAT AVAILABILITY
   ========================================================= */

async function seatsRefresh() {
    const box = $('#smap');

    // Clear the current selection when refreshing availability.
    pick = [];
    sync();

    // Ask the user to select a time slot before showing seats.
    if (!sel.length) {
        box.innerHTML = `
            <p class="note">
                Choose your start and end time first to see which seats are free.
            </p>
        `;

        return;
    }

    // Request seats available for the selected space, date and time.
    const r = await fetch(
        `reservation_api.php?a=seats&space=${sid}` +
        `&date=${bf.date.value}` +
        `&start=${sel[0]}` +
        `&end=${sel.at(-1) + 1}`
    );

    const j = await r.json();

    // Update the maximum number of selectable seats.
    mx = j.max;
    sync();

    // Draw the seat map using the server's layout data.
    box.innerHTML = drawMap(j);

    // Attach selection handlers to each available seat.
    box.querySelectorAll('.seat[data-s]').forEach(b => {
        b.onclick = () => {
            const s = b.dataset.s;

            if (pick.includes(s)) {
                // Remove a seat if it is already selected.
                pick = pick.filter(x => x !== s);
            } else if (pick.length < mx) {
                // Add a seat only if the selection limit allows it.
                pick.push(s);
            } else {
                // Stop if the maximum number of seats is reached.
                return;
            }

            // Update the seat's visual state.
            b.classList.toggle('on');

            // Update the selected-seat summary.
            sync();
        };
    });
}
