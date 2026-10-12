
<?php

// Load database configuration and start the session.
require 'config.php';

// Set the response format to JSON.
header('Content-Type: application/json');

// Get the requested action from the URL or form data.
$a = $_REQUEST['a'] ?? '';


// --------------------------------------------------
// STEP 1: Send a JSON response and stop execution
// --------------------------------------------------
function out($d, $c = 200)
{
    // Set the HTTP response status code.
    http_response_code($c);

    // Convert the data into JSON and return it.
    echo json_encode($d);

    // Stop the script after sending the response.
    exit;
}


// --------------------------------------------------
// STEP 2: Retrieve a study space using its ID
// --------------------------------------------------
function space($id)
{
    // Prepare a query to find the requested space.
    $s = adb()->prepare(
        "SELECT * FROM spaces WHERE id = ?"
    );

    // Insert the space ID and execute the query.
    $s->execute([$id]);

    // Return the matching space record.
    return $s->fetch();
}


// --------------------------------------------------
// STEP 3: Get all seat labels belonging to a space
// --------------------------------------------------
function allSeats($sp)
{
    // Generate the seat layout and combine all seat blocks.
    return array_merge(...seatLayout($sp)['blocks']);
}


// --------------------------------------------------
// STEP 4: Find seats already booked during each hour
// --------------------------------------------------
function takenByHour($sp, $d)
{
    // Get every seat available in the selected space.
    $all = allSeats($sp);

    // Find confirmed bookings for this space and date.
    $s = sdb()->prepare(
        "SELECT start_hour, end_hour, seats
         FROM bookings
         WHERE space_id = ?
           AND booking_date = ?
           AND status = 'Confirmed'"
    );

    // Execute the query using the space ID and date.
    $s->execute([$sp['id'], $d]);

    // Store booked seat labels grouped by hour.
    $t = [];

    // Process each confirmed booking.
    foreach ($s as $r) {

        // If no seat labels exist, treat the entire room
        // as booked (for older bookings).
        $L = $r['seats']
            ? explode(',', $r['seats'])
            : $all;

        // Mark the selected seats as booked for each hour.
        for ($h = $r['start_hour']; $h < $r['end_hour']; $h++) {

            // Add these seats to the seats already booked
            // during this hour.
            $t[$h] = array_merge($t[$h] ?? [], $L);
        }
    }

    // Return the booked seats grouped by hour.
    return $t;
}


// --------------------------------------------------
// STEP 5: Find seats booked during a selected time range
// --------------------------------------------------
function takenIn($sp, $d, $st, $en)
{
    // Store seats booked during the requested time range.
    $tk = [];

    // Get booked seats for each hour on the selected date.
    foreach (takenByHour($sp, $d) as $h => $L) {

        // Include only hours within the requested interval.
        if ($h >= $st && $h < $en) {
            $tk = array_merge($tk, $L);
        }
    }

    // Remove duplicate seat labels and return the result.
    return array_values(array_unique($tk));
}


// --------------------------------------------------
// STEP 6: Handle the available full-room time slots
// --------------------------------------------------
if ($a == 'slots') {

    // Get the selected space.
    $sp = space($_GET['space']);

    // Count the total number of seats in that space.
    $n = count(allSeats($sp));

    // Store hours during which every seat is booked.
    $f = [];

    // Check the booked seats for each hour.
    foreach (takenByHour($sp, $_GET['date']) as $h => $L) {

        // If every seat is booked, mark the hour as full.
        if (count(array_unique($L)) >= $n) {
            $f[] = $h;
        }
    }

    // Return the hours when the entire space is unavailable.
    out($f);
}


// --------------------------------------------------
// STEP 7: Handle seat-layout and availability requests
// --------------------------------------------------
if ($a == 'seats') {

    // Get the selected space.
    $sp = space($_GET['space']);

    // Return the seat layout, booked seats, and seat limit.
    out(
        seatLayout($sp) + [
            'taken' => takenIn(
                $sp,
                $_GET['date'],
                (int) $_GET['start'],
                (int) $_GET['end']
            ),

            // Limit one booking to 10 seats or the space capacity.
            'max' => min($sp['capacity'], 10)
        ]
    );
}


// --------------------------------------------------
// STEP 8: Check whether the user is a signed-in student
// --------------------------------------------------
if (($_SESSION['role'] ?? '') != 'student') {

    // Reject booking requests from unauthenticated users
    // or users who do not have the student role.
    out([
        'error' => 'Please sign in first.'
    ], 401);
}


// --------------------------------------------------
// STEP 9: Process a new seat booking
// --------------------------------------------------
if ($a == 'book') {

    // Get the selected space and booking details.
    $sp = space($_POST['space']);
    $d  = $_POST['date'];
    $st = (int) $_POST['start'];
    $en = (int) $_POST['end'];


    // STEP 9.1: Check whether the space allows reservations.
    if (!$sp || !$sp['reservable']) {
        out([
            'error' => 'This space is walk-in only.'
        ], 400);
    }


    // STEP 9.2: Restrict bookings to today and the next 7 days.
    if (
        $d < date('Y-m-d') ||
        $d > date('Y-m-d', strtotime('+7 days'))
    ) {
        out([
            'error' => 'Choose a date within the next 7 days.'
        ], 400);
    }


    // STEP 9.3: Validate the booking time.
    // Bookings must be between 9 AM and 5 PM,
    // last at least 1 hour, and last no more than 4 hours.
    if (
        $st < 9 ||
        $en > 17 ||
        $en <= $st ||
        $en - $st > 4
    ) {
        out([
            'error' => 'Pick 1–4 hours between 9 AM and 5 PM.'
        ], 400);
    }


    // STEP 9.4: Read the selected seats and remove duplicates.
    $sel = array_values(
        array_unique(
            array_filter(
                explode(',', $_POST['seats'] ?? '')
            )
        )
    );

    // Set the maximum number of seats allowed per booking.
    $mx = min($sp['capacity'], 10);


    // STEP 9.5: Validate the selected seats.
    // Reject empty selections, excessive seat counts,
    // and seat labels that do not belong to this space.
    if (
        !$sel ||
        count($sel) > $mx ||
        array_diff($sel, allSeats($sp))
    ) {
        out([
            'error' => "Choose between 1 and $mx seats."
        ], 400);
    }


    // STEP 9.6: Check whether any selected seat is already booked.
    $taken = takenIn($sp, $d, $st, $en);

    // Reject the booking if at least one selected seat is unavailable.
    if (array_intersect($sel, $taken)) {
        out([
            'error' => 'Some of those seats were just taken. Pick other seats.'
        ], 409);
    }


    // STEP 9.7: Check whether the student already has
    // another confirmed booking that overlaps this time.
    $o = sdb()->prepare(
        "SELECT COUNT(*)
         FROM bookings
         WHERE student_id = ?
           AND booking_date = ?
           AND status = 'Confirmed'
           AND start_hour < ?
           AND end_hour > ?"
    );

    // Bind the student, date, and requested time interval.
    $o->execute([
        $_SESSION['id'],
        $d,
        $en,
        $st
    ]);

    // Reject the booking if an overlapping reservation exists.
    if ($o->fetchColumn()) {
        out([
            'error' => 'You already have a booking at that time.'
        ], 409);
    }


    // STEP 9.8: Format the selected seats for the response.
    $sl = implode(', ', $sel);


    // STEP 9.9: Save the booking in the database.
    $insert = sdb()->prepare(
        "INSERT INTO bookings
         (
             student_id,
             space_id,
             booking_date,
             start_hour,
             end_hour,
             group_size,
             seats,
             purpose
         )
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    // Execute the INSERT query with the booking details.
    $insert->execute([
        $_SESSION['id'],
        $sp['id'],
        $d,
        $st,
        $en,
        count($sel),
        implode(',', $sel),
        substr($_POST['purpose'] ?? '', 0, 120)
    ]);


    // STEP 9.10: Generate a readable booking reference.
    // Example: BK-00001
    $id = 'BK-' . str_pad(
        sdb()->lastInsertId(),
        5,
        '0',
        STR_PAD_LEFT
    );


    // STEP 9.11: Format the booking date and time.
    $w = "$d, " . hr($st) . ' – ' . hr($en);


    // STEP 9.12: Notify the student that the booking is confirmed.
    notify(
        $_SESSION['id'],
        "Booking $id confirmed: {$sp['name']}, seat(s) $sl, $w."
    );


    // STEP 9.13: Return the booking confirmation as JSON.
    out([
        'id'    => $id,
        'space' => $sp['name'],
        'when'  => $w,
        'seats' => $sl
    ]);
}


// --------------------------------------------------
// STEP 10: Handle unsupported actions
// --------------------------------------------------

// Return an error if the requested action is not recognised.
out([
    'error' => 'Unknown action'
], 400);

?>
