
<?php

// ==================================================
// STEP 1: LOAD CONFIGURATION AND FETCH SPACE DATA
// ==================================================

// Load database connections, session, and helper functions.
require 'config.php';

// Retrieve all study spaces, ordered by their IDs.
$sp = adb()->query(
    "SELECT * FROM spaces ORDER BY id"
)->fetchAll();

// Calculate the total seating capacity of all spaces.
$seats = array_sum(array_column($sp, 'capacity'));

// Count the number of unique floors.
$floors = count(
    array_unique(array_column($sp, 'floor'))
);


// ==================================================
// STEP 2: CHECK THE USER'S LOGIN STATUS
// ==================================================

// Check whether the current user is a student.
$stu = ($_SESSION['role'] ?? '') == 'student';

// If the user is a student, retrieve their details.
// Otherwise, use an empty array.
$me = $stu
    ? sdb()->query(
        'SELECT student_no, name, email
         FROM students
         WHERE id = ' . (int) $_SESSION['id']
    )->fetch()
    : [];

?>

<!doctype html>
<html lang="en">

<head>

    <!-- Define character encoding and responsive viewport settings. -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Set the browser tab title. -->
    <title>Find a space · Reading Room</title>

    <!-- Load the shared website styles and reservation page styles. -->
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/reservation.css">

</head>

<body class="home">

    <?php
    // ==================================================
    // STEP 3: DISPLAY THE NAVIGATION BAR
    // ==================================================

    // Identify the current page for the navigation menu.
    $active = 'reserve';

    // Include the shared navigation component.
    include 'nav.php';
    ?>


    <!-- ==================================================
         STEP 4: DISPLAY THE SIGN-OUT MESSAGE
    =================================================== -->

    <?php if (isset($_GET['out'])): ?>

        <!-- Show a message after the user signs out. -->
        <p class="msg good toast" id="toast" role="status">
            You have been signed out.
        </p>

    <?php endif; ?>


    <!-- ==================================================
         STEP 5: CREATE THE HERO SECTION
    =================================================== -->

    <section class="hero">

        <div class="hl">

            <!--
                HERO IMAGE:
                Save the library or building photo as
                assets/img/hero.jpg.

                The image is displayed through the existing CSS.
                A dark blue overlay improves text readability.
            -->

            <!-- Display the breadcrumb navigation. -->
            <nav class="crumb">
                <a href="reserve.php">Home</a>
                <span>&rsaquo;</span>
                <b>Find a space</b>
            </nav>

            <!-- Introduce the available study spaces. -->
            <h1>Find your perfect study space</h1>

            <p>
                Discover and reserve study environments tailored
                to your learning needs.
            </p>


            <!-- ==========================================
                 STEP 5.1: DISPLAY LIBRARY STATISTICS
            =========================================== -->

            <dl class="stats">

                <!-- Total seating capacity. -->
                <div>
                    <dt data-n="<?= $seats ?>">0</dt>

                    <dd>
                        <svg width="15" height="15"
                             viewBox="0 0 16 16"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="1.6"
                             stroke-linecap="round"
                             stroke-linejoin="round"
                             aria-hidden="true">
                            <path d="M4 12V6a4 4 0 018 0v6M3 12h10v3H3z"/>
                        </svg>

                        Total seats
                    </dd>
                </div>

                <!-- Total number of floors. -->
                <div>
                    <dt data-n="<?= $floors ?>">0</dt>

                    <dd>
                        <svg width="15" height="15"
                             viewBox="0 0 16 16"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="1.6"
                             stroke-linecap="round"
                             stroke-linejoin="round"
                             aria-hidden="true">
                            <path d="M8 2l6 3-6 3-6-3zM2 8l6 3 6-3M2 11l6 3 6-3"/>
                        </svg>

                        Floors
                    </dd>
                </div>

                <!-- Total number of study spaces. -->
                <div>
                    <dt data-n="<?= count($sp) ?>">0</dt>

                    <dd>
                        <svg width="15" height="15"
                             viewBox="0 0 16 16"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="1.6"
                             stroke-linecap="round"
                             stroke-linejoin="round"
                             aria-hidden="true">
                            <path d="M4 14V2h8v12M2 14h12M9 8h.01"/>
                        </svg>

                        Spaces
                    </dd>
                </div>

                <!-- Daily operating hours. -->
                <div>
                    <dt>9–5</dt>

                    <dd>
                        <svg width="15" height="15"
                             viewBox="0 0 16 16"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="1.6"
                             stroke-linecap="round"
                             stroke-linejoin="round"
                             aria-hidden="true">
                            <circle cx="8" cy="8" r="6"/>
                            <path d="M8 4v4l3 2"/>
                        </svg>

                        Daily hours
                    </dd>
                </div>

            </dl>

        </div>


        <!-- ==========================================
             STEP 5.2: DISPLAY THE LIVE CLOCK
        =========================================== -->

        <aside class="hclock" aria-label="Current date and time">

            <!-- JavaScript displays the current clock information here. -->
            <div class="hcg" id="hcg"></div>

            <!-- SVG clock face and hands. -->
            <svg class="hca"
                 viewBox="0 0 200 200"
                 aria-hidden="true">

                <circle class="hcf" cx="100" cy="100" r="96"/>

                <!-- JavaScript generates the clock markings. -->
                <g id="hct"></g>

                <!-- Clock numbers. -->
                <text x="100" y="32">12</text>
                <text x="168" y="101">3</text>
                <text x="100" y="170">6</text>
                <text x="32" y="101">9</text>

                <!-- Clock hands controlled by JavaScript. -->
                <line id="hch" class="hch"
                      x1="100" y1="100" x2="100" y2="58"/>

                <line id="hcm" class="hcm"
                      x1="100" y1="100" x2="100" y2="38"/>

                <line id="hcs" class="hcs"
                      x1="100" y1="114" x2="100" y2="24"/>

                <!-- Centre point of the clock. -->
                <circle cx="100" cy="100" r="4.5" fill="#ff4d4f"/>

            </svg>

            <!-- JavaScript displays the date, time, and related information. -->
            <div class="hcd" id="hcd"></div>
            <div class="hct" id="hcc"></div>
            <div class="hco" id="hco"></div>

        </aside>

    </section>


    <!-- ==================================================
         STEP 6: CREATE THE MAIN STUDY SPACE AREA
    =================================================== -->

    <main class="wrap" id="spaces">


        <!-- ==========================================
             STEP 6.1: CREATE THE FILTER SIDEBAR
        =========================================== -->

        <aside class="filters">

            <h2>Filter</h2>

            <!-- Filter spaces by floor. -->
            <label>
                Floor

                <select data-f="floor">
                    <option value="">All floors</option>

                    <?php foreach (
                        array_unique(array_column($sp, 'floor')) as $v
                    ): ?>

                        <option><?= e($v) ?></option>

                    <?php endforeach; ?>

                </select>
            </label>


            <!-- Filter spaces by sound level. -->
            <label>
                Sound level

                <select data-f="sound">
                    <option value="">Any level</option>
                    <option>Silent</option>
                    <option>Quiet</option>
                    <option>Collaborative</option>
                </select>
            </label>


            <!-- Filter spaces by seating capacity. -->
            <label>
                Capacity

                <select data-f="cap">
                    <option value="">Any size</option>
                    <option value="1-5">1–5 people</option>
                    <option value="6-15">6–15 people</option>
                    <option value="16-30">16–30 people</option>
                    <option value="31-999">31+ people</option>
                </select>
            </label>


            <!-- Filter spaces by reservation availability. -->
            <label>
                Booking

                <select data-f="res">
                    <option value="">All spaces</option>
                    <option value="1">Reservable</option>
                    <option value="0">Walk-in only</option>
                </select>
            </label>


            <!-- Filter spaces by their intended purpose. -->
            <label>
                Purpose

                <select data-f="purpose">
                    <option value="">Any purpose</option>
                    <option>Individual Study</option>
                    <option>Group Study</option>
                    <option>Presentations</option>
                </select>
            </label>


            <!-- Button used by JavaScript to clear all filters. -->
            <button class="btn ghost" id="clear">
                Clear filters
            </button>

        </aside>


        <!-- ==========================================
             STEP 6.2: DISPLAY THE SPACE RESULTS
        =========================================== -->

        <div>

            <!-- JavaScript updates the number of matching spaces. -->
            <p id="count" class="count"></p>

            <!-- Container holding all study space cards. -->
            <div class="grid">

                <?php foreach ($sp as $s): ?>

                    <!-- Each article represents one study space. -->
                    <article
                        class="card"
                        data-floor="<?= e($s['floor']) ?>"
                        data-sound="<?= e($s['sound']) ?>"
                        data-cap="<?= $s['capacity'] ?>"
                        data-res="<?= $s['reservable'] ?>"
                        data-purpose="<?= e($s['purpose']) ?>"
                    >


                        <!-- ==================================
                             STEP 6.3: DISPLAY THE SPACE IMAGE
                        =================================== -->

                        <div class="ph">

                            <!--
                                Space images can be uploaded through
                                Admin > Spaces or stored in:
                                assets/img/spaces/
                            -->

                            <?php if (
                                spaceImg($s['image'], $s['name'])
                            ): ?>

                                <!-- Display the space image if available. -->
                                <img
                                    src="<?= spaceImg($s['image'], $s['name']) ?>"
                                    alt="<?= e($s['name']) ?>"
                                >

                            <?php else: ?>

                                <!-- Display a placeholder if no image exists. -->
                                <span>Photo coming soon</span>

                            <?php endif; ?>


                            <!-- Show the sound level as a label. -->
                            <b class="tag <?= strtolower($s['sound']) ?>">
                                <?= e($s['sound']) ?>
                            </b>

                        </div>


                        <!-- ==================================
                             STEP 6.4: DISPLAY SPACE DETAILS
                        =================================== -->

                        <div class="cb">

                            <!-- Space name. -->
                            <h3><?= e($s['name']) ?></h3>

                            <!-- Space description. -->
                            <p><?= e($s['description']) ?></p>

                            <!-- Floor and seating capacity. -->
                            <p class="meta">
                                <?= e($s['floor']) ?>
                                · Up to <?= $s['capacity'] ?> people
                            </p>


                            <!-- ==================================
                                 STEP 6.5: DISPLAY THE BOOKING OPTION
                            =================================== -->

                            <?php if ($s['reservable']): ?>

                                <!--
                                    Store the selected space details in
                                    data attributes for reservation.js.
                                -->

                                <button
                                    class="btn reserve"
                                    data-id="<?= $s['id'] ?>"
                                    data-name="<?= e($s['name']) ?>"
                                    data-cap="<?= $s['capacity'] ?>"
                                    data-floor="<?= e($s['floor']) ?>"
                                    data-sound="<?= e($s['sound']) ?>"
                                    data-ut="<?= e($s['user_type']) ?>"
                                    data-fac="<?= e($s['facilities']) ?>"
                                    data-img="<?= spaceImg($s['image'], $s['name']) ?>"
                                >
                                    <?= $stu ? 'Reserve' : 'Sign in to reserve' ?>
                                </button>

                            <?php else: ?>

                                <!-- Explain that reservations are unavailable. -->
                                <span class="walk">Walk-in only</span>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>


            <!-- Displayed by JavaScript when no spaces match the filters. -->
            <p id="none" class="empty" hidden>
                No spaces match. Clear a filter to see more.
            </p>

        </div>

    </main>


    <!-- ==================================================
         STEP 7: CREATE THE RESERVATION DIALOG
    =================================================== -->

    <dialog id="dlg">

        <!-- Form containing all reservation details. -->
        <form id="bf">


            <!-- ==========================================
                 STEP 7.1: DIALOG HEADER AND CLOSE BUTTON
            =========================================== -->

            <div class="mh">

                <div>
                    <small>Reserve study space</small>
                    <h2 id="dt">Reserve</h2>
                </div>

                <!-- Close the reservation dialog through JavaScript. -->
                <button
                    type="button"
                    class="xb"
                    id="x"
                    aria-label="Close"
                >
                    &times;
                </button>

            </div>


            <!-- ==========================================
                 STEP 7.2: DIALOG CONTENT
            =========================================== -->

            <div class="mb">


                <!-- ======================================
                     STEP 7.3: SHOW SELECTED SPACE DETAILS
                ======================================= -->

                <aside class="ml">

                    <!-- Display the selected space's image. -->
                    <div class="mimg">

                        <img id="mi" alt="" hidden>

                        <span id="mph">Photo coming soon</span>

                    </div>

                    <!-- JavaScript fills in these details. -->
                    <dl>

                        <dt>Floor</dt>
                        <dd id="d-floor"></dd>

                        <dt>Capacity</dt>
                        <dd id="d-cap"></dd>

                        <dt>Sound level</dt>
                        <dd id="d-sound"></dd>

                        <dt>User type</dt>
                        <dd id="d-ut"></dd>

                        <dt>Facilities</dt>
                        <dd id="d-fac"></dd>

                    </dl>

                </aside>


                <!-- ======================================
                     STEP 7.4: SELECT DATE AND TIME
                ======================================= -->

                <div class="mr">

                    <h3><b>1</b>Date and time</h3>

                    <!-- Select the reservation date. -->
                    <label>
                        Booking date

                        <input
                            type="date"
                            name="date"
                            required
                        >
                    </label>

                    <!-- The student chooses the start and end time.
                         JavaScript fills both drop-down lists. -->
                    <p class="lbl">
                        Choose your time
                        <span class="chip">max 4 hours</span>
                    </p>

                    <div class="two">

                        <label>
                            Start time

                            <select id="t-start"></select>
                        </label>

                        <label>
                            End time

                            <select id="t-end" disabled></select>
                        </label>

                    </div>

                    <!-- Display the reservation time restrictions. -->
                    <p class="note">
                        Library open 9:00 AM – 5:00 PM.
                        Book up to 7 days ahead.
                    </p>


                    <!-- ==================================
                         STEP 7.5: SELECT SEATS
                    =================================== -->

                    <h3><b>2</b>Choose your seats</h3>

                    <!-- JavaScript renders the interactive seat layout. -->
                    <div id="smap" class="smap">

                        <p class="note">
                            Choose your start and end time first to
                            see which seats are free.
                        </p>

                    </div>

                    <!-- Display the seats selected by the student. -->
                    <p class="pickline">
                        Selected seats:
                        <b id="sel">None yet</b>
                    </p>


                    <!-- ==================================
                         STEP 7.6: DISPLAY STUDENT DETAILS
                    =================================== -->

                    <h3><b>3</b>Your details</h3>

                    <!-- Student ID and full name are read-only. -->
                    <div class="two">

                        <label>
                            Student ID

                            <input
                                value="<?= e($me['student_no'] ?? '') ?>"
                                readonly
                            >
                        </label>

                        <label>
                            Full name

                            <input
                                value="<?= e($me['name'] ?? '') ?>"
                                readonly
                            >
                        </label>

                    </div>

                    <!-- Display the student's registered email. -->
                    <label>
                        Email

                        <input
                            value="<?= e($me['email'] ?? '') ?>"
                            readonly
                        >
                    </label>


                    <!-- ==================================
                         STEP 7.7: COLLECT FINAL DETAILS
                    =================================== -->

                    <h3><b>4</b>Final details</h3>

                    <!-- Allow the student to enter an optional purpose. -->
                    <label>
                        Purpose of study

                        <textarea
                            name="purpose"
                            maxlength="120"
                            rows="2"
                            placeholder="Briefly describe your study session..."
                        ></textarea>
                    </label>

                    <!-- Require agreement to the booking terms. -->
                    <label class="chk">

                        <input type="checkbox" required>

                        I agree to the booking terms and conditions

                    </label>

                    <!-- Display validation or booking messages. -->
                    <p id="bm" class="msg" hidden></p>

                </div>

            </div>


            <!-- ==========================================
                 STEP 7.8: DIALOG ACTION BUTTONS
            =========================================== -->

            <div class="mf">

                <!-- Cancel the reservation process. -->
                <button type="button" class="btn ghost" id="c">
                    Cancel
                </button>

                <!-- Submit the reservation form. -->
                <button class="btn" id="ok">
                    Confirm reservation
                </button>

            </div>

        </form>

    </dialog>


    <!-- ==================================================
         STEP 7.9: BOOKING CONFIRMATION POP-UP
         JavaScript fills in the details and opens this
         pop-up after a reservation is saved.
    =================================================== -->

    <dialog id="okdlg" aria-labelledby="ok-title">

        <!-- Header with the success icon and close button. -->
        <div class="mh">

            <div class="okt">

                <span class="okic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#fff"
                         stroke-width="3" stroke-linecap="round"
                         stroke-linejoin="round">
                        <path d="M5 12.5l4.5 4.5L19 7.5"></path>
                    </svg>
                </span>

                <div>
                    <small>Reservation confirmed</small>
                    <h2 id="ok-title">Successfully reserved!</h2>
                </div>

            </div>

            <button
                type="button"
                class="xb"
                id="ok-x"
                aria-label="Close"
            >
                &times;
            </button>

        </div>

        <!-- Booking details. -->
        <div class="okb">

            <p class="okid">
                Booking ID
                <b id="ok-id"></b>
            </p>

            <dl class="okl">

                <dt>Space</dt>
                <dd id="ok-space"></dd>

                <dt>Date</dt>
                <dd id="ok-date"></dd>

                <dt>Start time</dt>
                <dd id="ok-start"></dd>

                <dt>End time</dt>
                <dd id="ok-end"></dd>

                <dt>Seat(s)</dt>
                <dd id="ok-seats"></dd>

            </dl>

        </div>

        <!-- Close the pop-up or go to the student dashboard. -->
        <div class="mf">

            <button type="button" class="btn ghost" id="ok-done">
                Done
            </button>

            <a class="btn" href="dashboard.php">
                View in My dashboard
            </a>

        </div>

    </dialog>


    <!-- ==================================================
         STEP 8: INCLUDE THE FOOTER
    =================================================== -->

    <?php include 'footer.php'; ?>


    <!-- ==================================================
         STEP 9: PASS THE LOGIN STATUS TO JAVASCRIPT
    =================================================== -->

    <script>
        // Make the student's login status available to reservation.js.
        const STU = <?= json_encode($stu) ?>;
    </script>


    <!-- ==================================================
         STEP 10: LOAD RESERVATION PAGE JAVASCRIPT
    =================================================== -->

    <!-- Handles filtering, the clock, seat selection, and reservations. -->
    <script src="assets/js/reservation.js"></script>

</body>
</html>
