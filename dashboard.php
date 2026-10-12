
<?php

// ======================================================
// MODULE 3: STUDENT DASHBOARD
// Features:
// 1. Student authentication
// 2. Booking cancellation
// 3. Notifications management
// 4. Study tracker and weekly goal
// 5. Calendar events
// 6. Dashboard tabs
// ======================================================


// ======================================================
// STEP 1: LOAD CONFIGURATION AND VERIFY ACCESS
// ======================================================

// Load database connections and helper functions.
require 'config.php';

// Allow access to students only.
need('student');

// Get the logged-in student's ID.
$id = (int) $_SESSION['id'];


// ======================================================
// STEP 2: FLASH MESSAGE HELPER
// ======================================================

// Store a temporary message and redirect to a dashboard tab.
function back($type, $message, $tab)
{
    $_SESSION['flash'] = [$type, $message];

    header("Location: dashboard.php#$tab");
    exit;
}


// ======================================================
// STEP 3: HANDLE FORM SUBMISSIONS
// ======================================================

// Process actions submitted through POST requests.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get the submitted action.
    $action = $_POST['a'] ?? '';

    // --------------------------------------------------
    // ACTION 1: MARK ALL NOTIFICATIONS AS READ
    // --------------------------------------------------

    if ($action === 'read') {

        // Mark this student's notifications as read.
        sdb()->prepare(
            "UPDATE notifications
             SET is_read = 1
             WHERE student_id = ?"
        )->execute([$id]);

        // Return to the notifications tab.
        header('Location: dashboard.php#notifications');
        exit;
    }

    // --------------------------------------------------
    // ACTION 2: CANCEL A BOOKING
    // --------------------------------------------------

    if ($action === 'cancel') {

        // Find the student's confirmed booking.
        // Only bookings belonging to this student can be selected.
        $bookingQuery = sdb()->prepare(
            "SELECT b.*, s.name
             FROM bookings b
             JOIN lsrs_admin.spaces s ON s.id = b.space_id
             WHERE b.id = ?
             AND b.student_id = ?
             AND b.status = 'Confirmed'"
        );

        // Execute the query using the booking ID and student ID.
        $bookingQuery->execute([
            $_POST['id'] ?? 0,
            $id
        ]);

        // Retrieve the matching booking.
        $booking = $bookingQuery->fetch();

        // Continue only if the booking exists.
        if ($booking) {

            // Update the booking status to Cancelled.
            sdb()->prepare(
                "UPDATE bookings
                 SET status = 'Cancelled'
                 WHERE id = ?"
            )->execute([$booking['id']]);

            // Format the booking ID with leading zeros.
            $bookingCode = 'BK-' . str_pad(
                $booking['id'],
                5,
                '0',
                STR_PAD_LEFT
            );

            // Create a notification about the cancellation.
            $message = "You cancelled booking {$bookingCode}: " .
                "{$booking['name']}, {$booking['booking_date']}, " .
                hr($booking['start_hour']) . ' – ' .
                hr($booking['end_hour']) . '.';

            // Save the notification.
            notify($id, $message);

            // Display a success message and return to bookings.
            back('good', 'Booking cancelled.', 'bookings');
        }

        // Display an error if the booking cannot be cancelled.
        back(
            'bad',
            'That booking could not be cancelled.',
            'bookings'
        );
    }

    // Redirect if the submitted action is not recognized.
    header('Location: dashboard.php');
    exit;
}


// ======================================================
// STEP 4: RETRIEVE FLASH MESSAGES
// ======================================================

// Get any temporary message from the session.
$flash = $_SESSION['flash'] ?? null;

// Remove the message so it appears only once.
unset($_SESSION['flash']);


// ======================================================
// STEP 5: RETRIEVE STUDENT INFORMATION
// ======================================================

// Get the logged-in student's details.
$studentQuery = sdb()->prepare(
    "SELECT *
     FROM students
     WHERE id = ?"
);

$studentQuery->execute([$id]);

$u = $studentQuery->fetch();


// ======================================================
// STEP 6: RETRIEVE THE STUDENT'S BOOKINGS
// ======================================================

// Get all bookings belonging to the logged-in student.
// Include the space name and floor for display.
$bookingQuery = sdb()->prepare(
    "SELECT b.*, s.name, s.floor
     FROM bookings b
     JOIN lsrs_admin.spaces s ON s.id = b.space_id
     WHERE b.student_id = ?
     ORDER BY booking_date DESC, start_hour DESC"
);

// Execute the query for this student.
$bookingQuery->execute([$id]);

// Store all bookings in an array.
$mine = $bookingQuery->fetchAll();


// ======================================================
// STEP 7: RETRIEVE NOTIFICATIONS
// ======================================================

try {

    // Retrieve the latest 10 notifications.
    $notificationQuery = sdb()->prepare(
        "SELECT *
         FROM notifications
         WHERE student_id = ?
         ORDER BY id DESC
         LIMIT 10"
    );

    $notificationQuery->execute([$id]);

    // Store the notifications.
    $nt = $notificationQuery->fetchAll();

} catch (Exception $exception) {

    // Use an empty array if retrieving notifications fails.
    $nt = [];
}


// ======================================================
// STEP 8: CALCULATE BOOKING STATISTICS
// ======================================================

// Get today's date.
$today = date('Y-m-d');

// Keep only confirmed bookings.
$ok = array_filter(
    $mine,
    fn($booking) => $booking['status'] === 'Confirmed'
);

// Find confirmed bookings scheduled for today or later.
$up = array_filter(
    $ok,
    fn($booking) => $booking['booking_date'] >= $today
);

// Get the next upcoming booking.
// Note: The original ordering is descending, so this
// preserves the original end($up) behaviour.
$next = end($up) ?: null;

// Count unread notifications.
$unread = count(
    array_filter(
        $nt,
        fn($notification) => !$notification['is_read']
    )
);


// ======================================================
// STEP 9: CALCULATE THE WEEKLY STUDY TRACKER
// ======================================================

// Set the weekly booking-hours goal.
$goal = 10;

// Get the timestamp for Monday of the current week.
$mon = strtotime('monday this week');

// Initialize the total weekly booked hours.
$wk = 0;

// Add the duration of confirmed bookings within this week.
foreach ($ok as $booking) {

    if (
        $booking['booking_date'] >= date('Y-m-d', $mon) &&
        $booking['booking_date'] <= date(
            'Y-m-d',
            strtotime('sunday this week', $mon)
        )
    ) {
        // Calculate the duration using the start and end hours.
        $wk += $booking['end_hour'] - $booking['start_hour'];
    }
}

// Calculate the percentage of the weekly goal completed.
// Limit the result to a maximum of 100%.
$pct = min(
    100,
    round(($wk / $goal) * 100)
);

// Count confirmed bookings from previous dates.
$done = count(
    array_filter(
        $ok,
        fn($booking) => $booking['booking_date'] < $today
    )
);

// Get confirmed bookings scheduled for today.
$td = array_values(
    array_filter(
        $ok,
        fn($booking) => $booking['booking_date'] === $today
    )
);


// ======================================================
// STEP 10: PREPARE CALENDAR EVENTS FOR JAVASCRIPT
// ======================================================

// Convert confirmed bookings into the event format
// expected by the dashboard calendar.
$ev = array_map(
    fn($booking) => [
        'd' => $booking['booking_date'],
        't' => $booking['name'],
        's' => (int) $booking['start_hour'],
        'h' => hr($booking['start_hour']) .
            ' – ' .
            hr($booking['end_hour'])
    ],
    array_values($ok)
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <!-- Define the page's character encoding. -->
    <meta charset="utf-8">

    <!-- Make the dashboard responsive on different screens. -->
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Set the browser tab title. -->
    <title>My dashboard · Reading Room</title>

    <!-- Load the shared website styles. -->
    <link rel="stylesheet" href="assets/css/base.css">

    <!-- Load the student dashboard styles. -->
    <link rel="stylesheet" href="assets/css/dashboard.css">

    <!-- Keep dashboard panels visible when JavaScript is disabled. -->
    <noscript>
        <style>
            .tp {
                display: grid !important;
            }
        </style>
    </noscript>

</head>

<body>

<?php

// Highlight the dashboard link in the navigation bar.
$active = 'dashboard';

// Include the shared navigation bar.
include 'nav.php';

?>

<!-- ==================================================
     STEP 11: MAIN DASHBOARD CONTAINER
=================================================== -->

<main class="dash">

    <!-- Display the student's welcome message and details. -->
    <header class="welcome">

        <div>

            <!-- Display the student's first name. -->
            <h1>
                Welcome back,
                <?= e(explode(' ', $u['name'])[0]) ?>
            </h1>

            <!-- Display today's date and student number. -->
            <p>
                <?= date('l, j F Y') ?>
                · Student ID <?= e($u['student_no']) ?>
            </p>

        </div>

        <!-- Link to the space reservation page. -->
        <a class="btn" href="reserve.php">
            Reserve a space
        </a>

    </header>


    <!-- Display a temporary success or error message. -->
    <?php if ($flash): ?>

        <p class="msg <?= e($flash[0]) ?>">
            <?= e($flash[1]) ?>
        </p>

    <?php endif; ?>


    <!-- ==================================================
         STEP 12: DASHBOARD GRID
    =================================================== -->

    <div class="dgrid">

        <!-- LEFT COLUMN: BOOKINGS AND STUDY TRACKER -->
        <div class="col">

            <!-- ------------------------------------------
                 BOOKINGS SCHEDULED FOR TODAY
            ------------------------------------------- -->

            <h2>
                Your bookings today (<?= count($td) ?>)
            </h2>

            <div class="tdy">

                <?php

                // Rotate through the card colour classes.
                $cl = ['mint', 'rose', 'lilac'];

                // Display each confirmed booking for today.
                foreach ($td as $i => $booking):

                ?>

                    <article class="tc <?= $cl[$i % 3] ?>">

                        <!-- Display the space name. -->
                        <b class="nm">
                            <?= e($booking['name']) ?>
                        </b>

                        <!-- Display the booking time and floor. -->
                        <span>
                            <?= hr($booking['start_hour']) ?>
                            –
                            <?= hr($booking['end_hour']) ?>
                            · <?= e($booking['floor']) ?>
                        </span>

                        <!-- Link to the bookings section. -->
                        <a
                            class="go"
                            href="#bookings"
                            aria-label="View booking"
                        >
                            &#8599;
                        </a>

                    </article>

                <?php endforeach; ?>


                <!-- Show a message when there are no bookings today. -->
                <?php if (!$td): ?>

                    <article class="tc sun">

                        <b class="nm">Nothing booked today</b>

                        <span>
                            Find a seat for your next study session.
                        </span>

                        <a
                            class="go"
                            href="reserve.php"
                            aria-label="Reserve a space"
                        >
                            &#8599;
                        </a>

                    </article>

                <?php endif; ?>

            </div>


            <!-- ------------------------------------------
                 STUDY TRACKER
            ------------------------------------------- -->

            <h2>Study tracker</h2>

            <div class="trk">

                <!-- Display completed booking sessions. -->
                <div class="tc mint">

                    <small>Completed</small>

                    <b><?= $done ?></b>

                    <span>sessions</span>

                </div>

                <!-- Display booked hours for the current week. -->
                <div class="tc sun">

                    <small>This week</small>

                    <b><?= $wk ?>h</b>

                    <span>of <?= $goal ?>h goal</span>

                </div>

                <!-- Display upcoming confirmed bookings. -->
                <div class="tc lilac">

                    <small>Upcoming</small>

                    <b><?= count($up) ?></b>

                    <span>bookings</span>

                </div>

            </div>


            <!-- ------------------------------------------
                 MOTIVATIONAL QUOTE CARD
            ------------------------------------------- -->

            <aside class="qcard">

                <small>
                    Motivation, updates every 5 minutes
                </small>

                <!-- JavaScript inserts the motivational quote here. -->
                <p id="qtext"></p>

            </aside>


            <!-- ------------------------------------------
                 DASHBOARD WIDGETS
            ------------------------------------------- -->

            <div class="widgets">

                <!-- ACTIVE RESERVATION WIDGET -->
                <div class="wcard">

                    <div class="rc-h">

                        <div>

                            <!-- Original hard-coded reservation details. -->
                            <b>B-14</b>

                            <small>
                                Your active reservation · Quiet Zone
                            </small>

                        </div>

                        <span class="rc-v">
                            1h 18m left
                            <i></i>
                        </span>

                    </div>

                    <!-- Circular progress indicator. -->
                    <div class="gauge" style="--p:65">

                        <div class="g-t">

                            <b>65%</b>

                            <small>
                                10:30 AM – 12:30 PM
                            </small>

                        </div>

                    </div>

                    <!-- Reservation navigation actions. -->
                    <div class="reservation-actions">

                        <a
                            href="#bookings"
                            class="btn sm"
                        >
                            View reservation
                        </a>

                        <a
                            href="#bookings"
                            class="cancel-btn"
                        >
                            Cancel
                        </a>

                    </div>

                </div>


                <!-- WEEKLY GOAL WIDGET -->
                <div class="wcard">

                    <div class="rc-h">

                        <div>

                            <b>Weekly goal</b>

                            <small>
                                Hours booked this week
                            </small>

                        </div>

                        <span class="rc-v">
                            <?= $wk ?>h of <?= $goal ?>h
                            <i></i>
                        </span>

                    </div>

                    <!-- Display progress towards the weekly goal. -->
                    <div
                        class="gauge"
                        style="--p:<?= $pct ?>"
                    >

                        <div class="g-t">

                            <b><?= $pct ?>%</b>

                            <small>
                                <?= $wk ?> of <?= $goal ?> hours
                            </small>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- RIGHT COLUMN: CALENDAR AND UPCOMING EVENTS -->
        <div class="col">

            <!-- ------------------------------------------
                 BOOKING CALENDAR
            ------------------------------------------- -->

            <div class="cal">

                <div class="cal-h">

                    <!-- Navigate to the previous month. -->
                    <button
                        type="button"
                        id="cp"
                        aria-label="Previous month"
                    >
                        &lsaquo;
                    </button>

                    <!-- JavaScript displays the month and year. -->
                    <b id="cal-t"></b>

                    <!-- Navigate to the next month. -->
                    <button
                        type="button"
                        id="cn"
                        aria-label="Next month"
                    >
                        &rsaquo;
                    </button>

                </div>

                <!-- Display the weekday headings. -->
                <div class="cal-w">

                    <span>Mon</span>
                    <span>Tue</span>
                    <span>Wed</span>
                    <span>Thu</span>
                    <span>Fri</span>
                    <span>Sat</span>
                    <span>Sun</span>

                </div>

                <!-- JavaScript generates the calendar days here. -->
                <div class="cal-g" id="cal-g"></div>

            </div>


            <!-- ------------------------------------------
                 UPCOMING EVENTS
            ------------------------------------------- -->

            <div class="cal">

                <!-- JavaScript updates this heading. -->
                <h2 id="ev-h">Upcoming events</h2>

                <!-- JavaScript inserts the event list here. -->
                <ul class="evl" id="ev"></ul>

            </div>

        </div>

    </div>


    <!-- ==================================================
         STEP 13: DASHBOARD TABS
    =================================================== -->

    <div class="dtabs" role="tablist">

        <!-- Open the student's booking history. -->
        <button data-t="bookings">
            My bookings
        </button>

        <!-- Open notifications and show the unread count. -->
        <button data-t="notifications">

            Notifications

            <?php if ($unread): ?>

                <em><?= $unread ?></em>

            <?php endif; ?>

        </button>

    </div>


    <!-- ==================================================
         STEP 14: MY BOOKINGS TABLE
    =================================================== -->

    <section id="bookings" class="tp">

        <div class="tbl">

            <table>

                <!-- Table headings. -->
                <tr>
                    <th>ID</th>
                    <th>Space</th>
                    <th>Floor</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Seats</th>
                    <th>Status</th>
                    <th></th>
                </tr>


                <!-- Display every booking belonging to the student. -->
                <?php foreach ($mine as $booking): ?>

                    <tr>

                        <!-- Format the booking ID. -->
                        <td>
                            BK-<?= str_pad(
                                $booking['id'],
                                5,
                                '0',
                                STR_PAD_LEFT
                            ) ?>
                        </td>

                        <!-- Display the space name. -->
                        <td>
                            <?= e($booking['name']) ?>
                        </td>

                        <!-- Display the floor. -->
                        <td>
                            <?= e($booking['floor']) ?>
                        </td>

                        <!-- Display the booking date. -->
                        <td>
                            <?= e($booking['booking_date']) ?>
                        </td>

                        <!-- Display the booking time. -->
                        <td>
                            <?= hr($booking['start_hour']) ?>
                            –
                            <?= hr($booking['end_hour']) ?>
                        </td>

                        <!-- Display the booked seats or room type. -->
                        <td>
                            <?= e(
                                $booking['seats']
                                    ? str_replace(
                                        ',',
                                        ', ',
                                        $booking['seats']
                                    )
                                    : 'Whole room'
                            ) ?>
                        </td>

                        <!-- Display the booking status. -->
                        <td>

                            <span class="pill <?= e(
                                strtolower($booking['status'])
                            ) ?>">

                                <?= e($booking['status']) ?>

                            </span>

                        </td>

                        <!-- Display the cancellation action when allowed. -->
                        <td>

                            <?php if (
                                $booking['status'] === 'Confirmed' &&
                                $booking['booking_date'] >= $today
                            ): ?>

                                <form
                                    method="post"
                                    data-confirm="Cancel this booking?"
                                >

                                    <!-- Identify the requested action. -->
                                    <input
                                        type="hidden"
                                        name="a"
                                        value="cancel"
                                    >

                                    <!-- Send the booking ID to the server. -->
                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $booking['id'] ?>"
                                    >

                                    <button class="link">
                                        Cancel
                                    </button>

                                </form>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>


                <!-- Display a message if the student has no bookings. -->
                <?php if (!$mine): ?>

                    <tr>
                        <td colspan="8">
                            No bookings yet. Reserve a space to get started.
                        </td>
                    </tr>

                <?php endif; ?>

            </table>

        </div>

    </section>


    <!-- ==================================================
         STEP 15: NOTIFICATIONS SECTION
    =================================================== -->

    <section id="notifications" class="tp">

        <div class="notes">

            <!-- Display each notification. -->
            <?php foreach ($nt as $notification): ?>

                <p class="<?= $notification['is_read']
                    ? ''
                    : 'unread' ?>">

                    <!-- Display the notification message. -->
                    <span>
                        <?= e($notification['message']) ?>
                    </span>

                    <!-- Display the notification creation time. -->
                    <small>
                        <?= e(
                            date(
                                'j M, g:i A',
                                strtotime($notification['created_at'])
                            )
                        ) ?>
                    </small>

                </p>

            <?php endforeach; ?>


            <!-- Display a message if there are no notifications. -->
            <?php if (!$nt): ?>

                <p>
                    No notifications yet.
                    Booking confirmations and cancellations appear here.
                </p>

            <?php endif; ?>

        </div>


        <!-- Show the mark-as-read button only when unread notifications exist. -->
        <?php if ($unread): ?>

            <form method="post">

                <!-- Identify the notification action. -->
                <input
                    type="hidden"
                    name="a"
                    value="read"
                >

                <button class="btn ghost sm">
                    Mark all as read
                </button>

            </form>

        <?php endif; ?>

    </section>

</main>


<!-- ==================================================
     STEP 16: FOOTER
=================================================== -->

<?php

// Include the shared website footer.
include 'footer.php';

?>


<!-- ==================================================
     STEP 17: MOTIVATION POP-UP
=================================================== -->

<div
    id="qpop"
    class="qpop"
    role="status"
    aria-live="polite"
    hidden
>

    <!-- Pop-up heading. -->
    <b>Study motivation</b>

    <!-- JavaScript inserts the motivational quote here. -->
    <p id="qp"></p>

    <!-- Close the pop-up. -->
    <button
        type="button"
        id="qx"
        aria-label="Close"
    >
        &times;
    </button>

</div>


<!-- ==================================================
     STEP 18: PASS BOOKING DATA TO JAVASCRIPT
=================================================== -->

<script>

// Convert PHP booking events into a JavaScript array.
// JSON_HEX_TAG and JSON_HEX_AMP help prevent unsafe HTML
// characters from being inserted directly into the script.
const EV = <?= json_encode(
    $ev,
    JSON_HEX_TAG | JSON_HEX_AMP
) ?>;

</script>


<!-- Load the dashboard's JavaScript functionality. -->
<script src="assets/js/dashboard.js"></script>

</body>
</html>
