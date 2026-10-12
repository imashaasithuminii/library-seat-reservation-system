
<?php

// Start the session to access user login information.
session_start();

// Prevent pages from being cached by the browser.
// This helps prevent users from viewing old pages after signing out.
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Database connection settings.
const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';

// Library contact details displayed in the website footer.
const LIB_NAME = 'Reading Room Library';
const LIB_PHONE = '+94 11 000 0000';
const LIB_EMAIL = 'library@example.edu';
const LIB_ADDRESS = 'University Campus, Sri Lanka';

/**
 * Connect to a database using PDO.
 * Reuse an existing connection to avoid creating multiple connections
 * to the same database during a request.
 */
function db($name)
{
    static $connections = [];

    return $connections[$name] ??= new PDO(
        "mysql:host=" . DB_HOST . ";dbname=$name;charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
}

// Connect to the admin and student databases.
function adb()
{
    return db('lsrs_admin');
}

function sdb()
{
    return db('lsrs_student');
}

/**
 * Convert special HTML characters into safe HTML entities.
 * This helps prevent HTML injection when displaying user-provided data.
 */
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES);
}

/**
 * Check whether the logged-in user has the required role.
 * Redirect unauthorized users to the login page.
 */
function need($role)
{
    if (($_SESSION['role'] ?? '') !== $role) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Convert a 24-hour clock hour into a 12-hour format.
 * Example: 14 becomes 2 PM.
 */
function hr($hour)
{
    return ($hour % 12 ?: 12) . ($hour < 12 ? ' AM' : ' PM');
}

/**
 * Find an image for a library space.
 * First, check the supplied filename.
 * If it does not exist, try a filename generated from the space name.
 * Return an empty string if no matching image is found.
 */
function spaceImg($filename, $name = '')
{
    $directory = __DIR__ . '/assets/img/spaces/';

    // Check whether the supplied image filename exists.
    if ($filename && is_file($directory . $filename)) {
        return 'assets/img/spaces/' . e($filename);
    }

    // Generate a filename from the space name and check common formats.
    if ($name) {
        $slug = trim(
            preg_replace('/[^a-z0-9]+/', '-', strtolower($name)),
            '-'
        );

        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            $path = "$directory$slug.$extension";

            if (is_file($path)) {
                return "assets/img/spaces/$slug.$extension";
            }
        }
    }

    return '';
}

// Create a default administrator account during the first setup.
// Default login: admin@library.local
// Default password: admin123
if (!adb()->query("SELECT COUNT(*) FROM admins")->fetchColumn()) {
    adb()->prepare(
        "INSERT INTO admins (name, email, password)
         VALUES ('Head Librarian', 'admin@library.local', ?)"
    )->execute([
        password_hash('admin123', PASSWORD_DEFAULT)
    ]);
}

/**
 * Save a notification for a student.
 * Catch database exceptions so a missing notification table
 * does not interrupt the main page.
 */
function notify($studentId, $message)
{
    try {
        sdb()->prepare(
            "INSERT INTO notifications (student_id, message)
             VALUES (?, ?)"
        )->execute([$studentId, $message]);
    } catch (Exception $exception) {
        // Ignore notification errors to keep the page running.
    }
}

/**
 * Identify the type of library space using its name, facilities,
 * and purpose. The result determines how its seats are arranged.
 */
function spaceKind($space)
{
    $name = strtolower($space['name']);
    $facilities = strtolower($space['facilities'] ?? '');

    if (
        str_contains($name, 'computer') ||
        str_contains($facilities, 'computers')
    ) {
        return 'lab';
    }

    if (
        str_contains($name, 'exam') ||
        ($space['purpose'] ?? '') === 'Presentations'
    ) {
        return 'hall';
    }

    if (
        str_contains($name, 'lounge') ||
        str_contains($name, 'corner') ||
        str_contains($name, 'relaxation') ||
        str_contains($facilities, 'sofa')
    ) {
        return 'sofa';
    }

    if (str_contains($name, 'pod')) {
        return 'pods';
    }

    if (
        str_contains($name, 'room') ||
        str_contains($name, 'unit')
    ) {
        return 'table';
    }

    if (
        str_contains($name, 'collaborative') ||
        str_contains($name, 'open')
    ) {
        return 'tables';
    }

    // Use individual study desks as the default layout.
    return 'desks';
}

/**
 * Generate the seat layout for a library space.
 * The arrangement depends on the space type and its capacity.
 * Seat labels use a letter and a number, such as A1 or B2.
 */
function seatLayout($space)
{
    // Ensure that every space has at least one seat.
    $capacity = max(1, (int) $space['capacity']);
    $kind = spaceKind($space);

    // Calculate the number of seats in each row or block.
    $columns = fn($limit, $multiplier) =>
        $capacity <= 4
            ? $capacity
            : min(
                $limit,
                (int) ceil(sqrt($capacity * $multiplier))
            );

    // Select the arrangement according to the space type.
    $size = match ($kind) {
        'hall' => $columns(14, 2.2),

        'lab', 'desks' => $columns(10, 1.6),

        'pods' => max(1, min(4, (int) ceil($capacity / 2))),

        'table' => $capacity,

        'tables' => 6,

        'sofa' => $capacity <= 4 ? 2 : 3
    };

    // Create seat labels and divide them into rows or blocks.
    $blocks = [];

    for ($i = 0, $rowNumber = 0;
         $i < $capacity;
         $i += $size, $rowNumber++) {

        $row = [];

        for (
            $j = 0;
            $j < $size && $i + $j < $capacity;
            $j++
        ) {
            $row[] = chr(65 + $rowNumber % 26) . ($j + 1);
        }

        $blocks[] = $row;
    }

    // Set the front or reference point for each space type.
    $front = [
        'hall' => 'PRESENTER / FRONT',
        'lab' => 'INSTRUCTOR',
        'desks' => 'LIBRARIAN DESK',
        'pods' => 'ENTRANCE',
        'table' => '',
        'tables' => 'ENTRANCE',
        'sofa' => 'ENTRANCE'
    ][$kind];

    // Return the layout details for use by other functions.
    return [
        'kind' => $kind,
        'front' => $front,
        'blocks' => $blocks
    ];
}