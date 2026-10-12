
// ======================================================
// MODULE 3: DASHBOARD
// Features:
// 1. Dashboard tab navigation
// 2. Show / hide passwords
// 3. Calendar with booking indicators
// 4. Display upcoming events
// 5. Motivation quotes with a pop-up every 5 minutes
// ======================================================


// ======================================================
// STEP 1: HELPER FUNCTIONS AND DASHBOARD ELEMENTS
// ======================================================

// Select the first HTML element that matches the CSS selector.
const $ = selector => document.querySelector(selector);

// Escape special HTML characters to prevent HTML injection.
const esc = text =>
    String(text).replace(/[&<>"']/g, char =>
        '&#' + char.charCodeAt(0) + ';'
    );

// Get all dashboard panels.
const panels = [...document.querySelectorAll('.tp')];

// Get all dashboard tab buttons.
const tabs = [...document.querySelectorAll('.dtabs button')];


// ======================================================
// STEP 2: DASHBOARD TAB NAVIGATION
// ======================================================

// Display the selected tab and hide the other panels.
function show() {

    // Get the selected tab from the URL hash.
    const selectedTab = location.hash.slice(1);

    // Use the selected tab if it exists; otherwise, show bookings.
    const activeTab = panels.some(panel => panel.id === selectedTab)
        ? selectedTab
        : 'bookings';

    // Activate the selected panel and deactivate the others.
    panels.forEach(panel => {
        panel.classList.toggle('on', panel.id === activeTab);
    });

    // Highlight the button belonging to the active tab.
    tabs.forEach(tab => {
        tab.classList.toggle('on', tab.dataset.t === activeTab);
    });
}

// Change the active tab when a tab button is clicked.
tabs.forEach(tab => {
    tab.onclick = () => {

        // Update the URL hash without reloading the page.
        history.replaceState(null, '', '#' + tab.dataset.t);

        // Refresh the displayed tab.
        show();
    };
});

// Update the active tab when the URL hash changes.
addEventListener('hashchange', show);

// Display the correct tab when the page loads.
show();


// ======================================================
// STEP 3: SHOW OR HIDE PASSWORDS
// ======================================================

// Get the checkbox used to toggle password visibility.
const showPasswordCheckbox = document.getElementById('showpw');

// Run this code only if the checkbox exists.
if (showPasswordCheckbox) {

    // Change password fields to text or password when checked.
    showPasswordCheckbox.onchange = () => {

        // Select the current and new password fields.
        document.querySelectorAll(
            'input[name="cur"], ' +
            'input[name="new"], ' +
            'input[name="new2"]'
        ).forEach(input => {

            // Show the password if checked; otherwise, hide it.
            input.type = showPasswordCheckbox.checked
                ? 'text'
                : 'password';
        });
    };
}


// ======================================================
// STEP 4: CALENDAR VARIABLES AND DATE HELPERS
// ======================================================

// Get today's date.
const today = new Date();

// Convert a date into YYYY-MM-DD format.
const iso = date =>
    date.getFullYear() + '-' +
    String(date.getMonth() + 1).padStart(2, '0') + '-' +
    String(date.getDate()).padStart(2, '0');

// Format a date for display, such as 10 Oct.
const fmt = (dateString, options) =>
    new Date(dateString + 'T00:00').toLocaleDateString(
        'en-GB',
        options
    );

// Start the calendar at the first day of the current month.
let cur = new Date(
    today.getFullYear(),
    today.getMonth(),
    1
);

// Store the date selected by the user.
let sel = null;


// ======================================================
// STEP 5: GENERATE THE CALENDAR
// ======================================================

function cal() {

    // Get the current calendar year and month.
    const year = cur.getFullYear();
    const month = cur.getMonth();

    // Calculate the weekday of the month's first day.
    // Monday = 0, Tuesday = 1, ..., Sunday = 6.
    const firstDay = (
        new Date(year, month, 1).getDay() + 6
    ) % 7;

    // Get the total number of days in the current month.
    const totalDays = new Date(year, month + 1, 0).getDate();

    // Add empty spaces before the first day of the month.
    let calendarHTML = '<span></span>'.repeat(firstDay);

    // Generate a button for each day of the month.
    for (let day = 1; day <= totalDays; day++) {

        // Convert the current day into YYYY-MM-DD format.
        const dateKey = iso(new Date(year, month, day));

        // Check whether this day is today.
        const isToday = dateKey === iso(today);

        // Check whether this day is selected.
        const isSelected = dateKey === sel;

        // Check whether this day contains a booking or event.
        const hasEvent = EV.some(event => event.d === dateKey);

        // Assign the appropriate CSS classes to the day.
        const classes =
            (isToday ? 'today ' : '') +
            (isSelected ? 'sel ' : '') +
            (hasEvent ? 'has' : '');

        // Create the calendar day button.
        calendarHTML += `
            <button
                type="button"
                data-d="${dateKey}"
                class="${classes}"
            >
                ${day}
            </button>
        `;
    }

    // Display the current month and year above the calendar.
    $('#cal-t').textContent = cur.toLocaleDateString(
        'en-GB',
        {
            month: 'long',
            year: 'numeric'
        }
    );

    // Insert the generated calendar into the HTML page.
    $('#cal-g').innerHTML = calendarHTML;

    // Add a click event to each calendar day.
    document.querySelectorAll('#cal-g button').forEach(button => {

        button.onclick = () => {

            // Select the clicked day.
            // Clicking it again removes the selection.
            sel = sel === button.dataset.d
                ? null
                : button.dataset.d;

            // Regenerate the calendar to update the selection.
            cal();
        };
    });

    // Refresh the event list whenever the calendar changes.
    list();
}


// ======================================================
// STEP 6: DISPLAY UPCOMING EVENTS
// ======================================================

function list() {

    // Get today's date in YYYY-MM-DD format.
    const todayKey = iso(today);

    // Choose which events to display.
    const events = (
        sel

            // If a date is selected, show events on that date.
            ? EV.filter(event => event.d === sel)

            // Otherwise, show today's and future events.
            : EV.filter(event => event.d >= todayKey)

    // Sort events by date and then by start time.
    ).sort((a, b) =>
        a.d.localeCompare(b.d) || a.s - b.s

    // Limit the list to 20 events for a selected date
    // or 5 events for the upcoming events list.
    ).slice(0, sel ? 20 : 5);

    // Update the event section heading.
    $('#ev-h').textContent = sel
        ? 'Events on ' + fmt(sel, {
            day: 'numeric',
            month: 'long'
        })
        : 'Upcoming events';

    // Display the events or an empty-state message.
    $('#ev').innerHTML = events.length

        // Generate an HTML list item for each event.
        ? events.map(event => `
            <li>
                <b>${fmt(event.d, {
                    day: 'numeric',
                    month: 'short'
                })}</b>

                <span>
                    ${esc(event.t)}
                    <small>${event.h}</small>
                </span>
            </li>
        `).join('')

        // Display this message if no events are available.
        : `
            <li class="none">
                Nothing booked.
                <a href="reserve.php">Reserve a space</a>
            </li>
        `;
}


// ======================================================
// STEP 7: PREVIOUS AND NEXT MONTH BUTTONS
// ======================================================

// Move the calendar to the previous month.
$('#cp').onclick = () => {

    // Move back one month.
    cur.setMonth(cur.getMonth() - 1);

    // Refresh the calendar.
    cal();
};

// Move the calendar to the next month.
$('#cn').onclick = () => {

    // Move forward one month.
    cur.setMonth(cur.getMonth() + 1);

    // Refresh the calendar.
    cal();
};

// Display the calendar when the page loads.
cal();


// ======================================================
// STEP 8: MOTIVATIONAL QUOTES
// ======================================================

// Store the motivational quotes in an array.
const Q = [
    "Small steps every day add up to big results.",
    "Focus on progress, not perfection.",
    "Your future self will thank you for the hour you study now.",
    "Start before you feel ready.",
    "One chapter at a time. One hour at a time.",
    "Consistency beats intensity.",
    "Difficult is not the same as impossible.",
    "Every expert was once a beginner.",
    "Discipline is choosing what you want most over what you want now.",
    "Make today's session count.",
    "Mistakes are proof that you are trying.",
    "Rest when you need to, but never quit the goal.",
    "A quiet hour of focus can change your whole week.",
    "Turn the page and keep going.",
    "Learn something today that tomorrow's you will use."
];

// Store the timeout ID used to hide the pop-up.
let tm;

// Select a quote based on the current five-minute time slot.
const q = () =>
    Q[Math.floor(Date.now() / 300000) % Q.length];

// Hide the motivational pop-up.
const hide = () => {
    $('#qpop').hidden = true;
};


// ======================================================
// STEP 9: DISPLAY MOTIVATIONAL QUOTES
// ======================================================

function push(showPopup) {

    // Update the motivational quote displayed inside the card.
    $('#qtext').textContent = q();

    // Display the pop-up only when requested.
    if (showPopup) {

        // Update the quote inside the pop-up.
        $('#qp').textContent = q();

        // Make the pop-up visible.
        $('#qpop').hidden = false;

        // Cancel any previously scheduled hide operation.
        clearTimeout(tm);

        // Hide the pop-up after 15 seconds.
        tm = setTimeout(hide, 15000);
    }
}

// Close the pop-up when the close button is clicked.
$('#qx').onclick = hide;

// Display a motivational quote in the card immediately.
push(false);

// Show the first motivational pop-up after 2.5 seconds.
setTimeout(() => {
    push(true);
}, 2500);

// Calculate the time remaining until the next five-minute slot.
const nextQuoteDelay = 300000 - (Date.now() % 300000);

// Wait until the next five-minute slot before starting the cycle.
setTimeout(() => {

    // Display a pop-up every five minutes.
    setInterval(() => {
        push(true);
    }, 300000);

}, nextQuoteDelay);