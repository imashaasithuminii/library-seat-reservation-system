
<?php
require 'config.php';
need('admin');

/* =========================================
   HANDLE ADMIN ACTIONS
   ========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['a'] ?? '';

    /* Change password */
    if ($a === 'pw') {
        $stmt = adb()->prepare(
            "SELECT password FROM admins WHERE id = ?"
        );
        $stmt->execute([$_SESSION['id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin || !password_verify($_POST['cur'] ?? '', $admin['password'])) {
            $_SESSION['flash'] = ['bad', 'Current password is incorrect.'];
        } elseif (
            strlen($_POST['new'] ?? '') < 6 ||
            ($_POST['new'] ?? '') !== ($_POST['new2'] ?? '')
        ) {
            $_SESSION['flash'] = [
                'bad',
                'New passwords must match and be at least 6 characters.'
            ];
        } else {
            $stmt = adb()->prepare(
                "UPDATE admins SET password = ? WHERE id = ?"
            );
            $stmt->execute([
                password_hash($_POST['new'], PASSWORD_DEFAULT),
                $_SESSION['id']
            ]);

            $_SESSION['flash'] = ['good', 'Password changed successfully.'];
        }
    }

    /* Cancel booking and notify student */
    elseif ($a === 'cancel') {
        $stmt = sdb()->prepare("
            SELECT b.*, s.name AS space_name
            FROM bookings b
            JOIN lsrs_admin.spaces s ON s.id = b.space_id
            WHERE b.id = ? AND b.status = 'Confirmed'
        ");
        $stmt->execute([(int)($_POST['id'] ?? 0)]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($booking) {
            $stmt = sdb()->prepare("
                UPDATE bookings
                SET status = 'Cancelled'
                WHERE id = ? AND status = 'Confirmed'
            ");
            $stmt->execute([$booking['id']]);

            if ($stmt->rowCount() > 0) {
                notify(
                    $booking['student_id'],
                    'Booking BK-' .
                    str_pad($booking['id'], 5, '0', STR_PAD_LEFT) .
                    ': ' . $booking['space_name'] . ', ' .
                    $booking['booking_date'] . ', ' .
                    hr($booking['start_hour']) . '–' .
                    hr($booking['end_hour']) .
                    ' was cancelled by the library.'
                );

                $_SESSION['flash'] = [
                    'good',
                    'Booking cancelled and student notified.'
                ];
            }
        } else {
            $_SESSION['flash'] = [
                'bad',
                'Booking not found or already cancelled.'
            ];
        }
    }

    /* Add or edit a space */
    elseif ($a === 'add' || $a === 'edit') {
        $img = $_POST['old'] ?? null;
        $file = $_FILES['image'] ?? null;

        if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $_SESSION['flash'] = ['bad', 'Image upload failed.'];
                header('Location: admin.php');
                exit;
            }

            $extension = strtolower(
                pathinfo($file['name'], PATHINFO_EXTENSION)
            );

            $imageInfo = getimagesize($file['tmp_name']);
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

            if (
                !in_array($extension, $allowedExtensions, true) ||
                $imageInfo === false ||
                !in_array($imageInfo['mime'], $allowedMimeTypes, true)
            ) {
                $_SESSION['flash'] = [
                    'bad',
                    'Upload a valid JPG, PNG, or WebP image.'
                ];
                header('Location: admin.php');
                exit;
            }

            $img = uniqid('sp_', true) . '.' . $extension;

            if (!move_uploaded_file(
                $file['tmp_name'],
                "assets/img/spaces/$img"
            )) {
                $_SESSION['flash'] = ['bad', 'Could not save the image.'];
                header('Location: admin.php');
                exit;
            }
        }

        $values = [
            trim($_POST['name'] ?? ''),
            trim($_POST['floor'] ?? ''),
            $_POST['sound'] ?? 'Quiet',
            (int)($_POST['capacity'] ?? 1),
            isset($_POST['reservable']) ? 1 : 0,
            $_POST['purpose'] ?? 'Individual Study',
            trim($_POST['description'] ?? ''),
            trim($_POST['facilities'] ?? ''),
            $_POST['user_type'] ?? 'Students',
            $img
        ];

        if (
            $values[0] === '' ||
            $values[1] === '' ||
            $values[3] < 1 ||
            !in_array($values[2], ['Silent', 'Quiet', 'Collaborative'], true) ||
            !in_array(
                $values[5],
                ['Individual Study', 'Group Study', 'Presentations'],
                true
            ) ||
            !in_array(
                $values[8],
                ['Students', 'Staff', 'Students and staff'],
                true
            )
        ) {
            $_SESSION['flash'] = ['bad', 'Please check the space details.'];
            header('Location: admin.php');
            exit;
        }

        if ($a === 'add') {
            $stmt = adb()->prepare("
                INSERT INTO spaces
                (
                    name, floor, sound, capacity, reservable,
                    purpose, description, facilities, user_type, image
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute($values);

            $_SESSION['flash'] = ['good', 'Space added successfully.'];
        } else {
            $stmt = adb()->prepare("
                UPDATE spaces
                SET name=?, floor=?, sound=?, capacity=?, reservable=?,
                    purpose=?, description=?, facilities=?, user_type=?, image=?
                WHERE id=?
            ");
            $stmt->execute([
                ...$values,
                (int)($_POST['id'] ?? 0)
            ]);

            $_SESSION['flash'] = ['good', 'Space updated successfully.'];
        }
    }

    /* Delete a space */
    elseif ($a === 'delete') {
        try {
            $stmt = adb()->prepare("DELETE FROM spaces WHERE id = ?");
            $stmt->execute([(int)($_POST['id'] ?? 0)]);

            $_SESSION['flash'] = $stmt->rowCount()
                ? ['good', 'Space deleted successfully.']
                : ['bad', 'Space not found.'];
        } catch (PDOException $error) {
            $_SESSION['flash'] = [
                'bad',
                'This space may have bookings and cannot be deleted.'
            ];
        }
    }

    header('Location: admin.php');
    exit;
}

/* =========================================
   LOAD DASHBOARD DATA
   ========================================= */

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$sp = adb()->query("
    SELECT * FROM spaces ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);

$bk = sdb()->query("
    SELECT b.*, s.name AS space, st.name AS student, st.student_no
    FROM bookings b
    JOIN lsrs_admin.spaces s ON s.id = b.space_id
    JOIN students st ON st.id = b.student_id
    ORDER BY b.booking_date DESC, b.start_hour DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

$n = fn($sql) => sdb()->query($sql)->fetchColumn();

/* =========================================
   CHART 1: BOOKINGS OVER THE LAST 7 DAYS
   ========================================= */

$dailyRows = sdb()->query("
    SELECT booking_date, COUNT(*) AS total
    FROM bookings
    WHERE booking_date >= CURDATE() - INTERVAL 6 DAY
      AND booking_date < CURDATE() + INTERVAL 1 DAY
    GROUP BY booking_date
    ORDER BY booking_date
")->fetchAll(PDO::FETCH_ASSOC);

$dailyMap = [];

foreach ($dailyRows as $row) {
    $dailyMap[$row['booking_date']] = (int)$row['total'];
}

$dailyLabels = [];
$dailyValues = [];

for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $dailyLabels[] = date('D, M j', strtotime($day));
    $dailyValues[] = $dailyMap[$day] ?? 0;
}

/* =========================================
   CHART 2: BOOKING STATUS
   ========================================= */

$statusRows = sdb()->query("
    SELECT status, COUNT(*) AS total
    FROM bookings
    GROUP BY status
    ORDER BY status
")->fetchAll(PDO::FETCH_ASSOC);

$statusLabels = array_column($statusRows, 'status');
$statusValues = array_map('intval', array_column($statusRows, 'total'));

/* =========================================
   CHART 3: POPULAR ROOMS
   ========================================= */

$popularRows = sdb()->query("
    SELECT s.name, COUNT(b.id) AS total
    FROM lsrs_admin.spaces s
    LEFT JOIN bookings b ON b.space_id = s.id
    GROUP BY s.id, s.name
    ORDER BY total DESC, s.name ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$popularLabels = array_column($popularRows, 'name');
$popularValues = array_map('intval', array_column($popularRows, 'total'));

/* =========================================
   CHART 4: TODAY'S ROOM AVAILABILITY
   ========================================= */

$availabilityRows = sdb()->query("
    SELECT
        s.id,
        s.name,
        s.capacity,
        COALESCE(SUM(
            CASE
                WHEN b.id IS NOT NULL THEN
                    CASE
                        WHEN b.group_size > 0 THEN b.group_size
                        ELSE 1
                    END
                ELSE 0
            END
        ), 0) AS reserved
    FROM lsrs_admin.spaces s
    LEFT JOIN bookings b
        ON b.space_id = s.id
        AND b.booking_date = CURDATE()
        AND b.status = 'Confirmed'
    GROUP BY s.id, s.name, s.capacity
    ORDER BY s.id
")->fetchAll(PDO::FETCH_ASSOC);

$availabilityLabels = [];
$availabilityCapacity = [];
$availabilityReserved = [];
$availabilityAvailable = [];

foreach ($availabilityRows as $room) {
    $capacity = max(0, (int)$room['capacity']);
    $reserved = min($capacity, max(0, (int)$room['reserved']));

    $availabilityLabels[] = $room['name'];
    $availabilityCapacity[] = $capacity;
    $availabilityReserved[] = $reserved;
    $availabilityAvailable[] = max(0, $capacity - $reserved);
}

/* Space selected for editing */
$edit = null;

foreach ($sp as $space) {
    if ((int)$space['id'] === (int)($_GET['edit'] ?? 0)) {
        $edit = $space;
        break;
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin · Reading Room</title>

    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/admin.css">

    <style>
        .analytics {
            margin-top: 45px;
            padding-top: 28px;
            border-top: 1px solid #e5e7eb;
        }

        .analytics-intro {
            color: #64748b;
            margin: 8px 0 22px;
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .analytics-card {
            min-width: 0;
            padding: 22px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
        }

        .analytics-card h3 {
            margin: 0 0 7px;
            font-size: 16px;
        }

        .analytics-card p {
            margin: 0 0 18px;
            color: #64748b;
            font-size: 13px;
        }

        .analytics-card:last-child {
            grid-column: 1 / -1;
        }

        .analytics-graph {
            position: relative;
            width: 100%;
            height: 280px;
        }

        .analytics-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 14px 0 24px;
        }

        .analytics-nav a {
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 8px;
            background: #f1efff;
            color: #5144bb;
            font-size: 13px;
        }

        @media (max-width: 700px) {
            .analytics-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            .analytics-card:last-child {
                grid-column: auto;
            }

            .analytics-card {
                padding: 16px;
            }

            .analytics-graph {
                height: 240px;
            }
        }
    </style>
</head>

<body class="admin">

<aside class="side">
    <a class="brand" href="admin.php">
        <img src="assets/img/logo.png" alt="" onerror="this.remove()">
        Reading Room
        <small>Librarian console</small>
    </a>

    <div id="clock" class="clock"></div>

    <nav>
        <a href="#overview">Overview</a>
        <a href="#bookings">Bookings</a>
        <a href="#manage">Spaces</a>
        <a href="#account">Account</a>
        <a href="#analytics">Analytics</a>
        <a href="index.php">View student site</a>
        <a href="logout.php">Sign out</a>
    </nav>
</aside>

<main class="adm">

    <h1 id="overview">
        Good day, <?= e($_SESSION['name'] ?? 'Admin') ?>
    </h1>

    <?php if ($flash): ?>
        <p class="msg <?= e($flash[0]) ?>">
            <?= e($flash[1]) ?>
        </p>
    <?php endif; ?>

    <!-- Dashboard statistics -->
    <div class="kpis">
        <div>
            <b><?= (int)$n("
                SELECT COUNT(*) FROM bookings
                WHERE booking_date = CURDATE()
                  AND status = 'Confirmed'
            ") ?></b>
            Today's bookings
        </div>

        <div>
            <b><?= (int)$n("
                SELECT COUNT(*) FROM bookings
                WHERE booking_date >= CURDATE()
                  AND status = 'Confirmed'
            ") ?></b>
            Upcoming
        </div>

        <div>
            <b><?= (int)$n("SELECT COUNT(*) FROM students") ?></b>
            Students
        </div>

        <div>
            <b><?= count($sp) ?></b>
            Spaces
        </div>
    </div>

    <!-- Bookings table -->
    <h2 id="bookings">Bookings</h2>

    <input
        id="q"
        class="search"
        type="search"
        placeholder="Search by student, ID or space"
    >

    <div class="tbl">
        <table id="bt">
            <tr>
                <th>ID</th>
                <th>Student</th>
                <th>Space</th>
                <th>Date</th>
                <th>Time</th>
                <th>Seats / Group</th>
                <th>Status</th>
                <th></th>
            </tr>

            <?php foreach ($bk as $b): ?>
                <tr>
                    <td>BK-<?= str_pad($b['id'], 5, '0', STR_PAD_LEFT) ?></td>
                    <td>
                        <?= e($b['student']) ?>
                        <small><?= e($b['student_no']) ?></small>
                    </td>
                    <td><?= e($b['space']) ?></td>
                    <td><?= e($b['booking_date']) ?></td>
                    <td><?= hr($b['start_hour']) ?>–<?= hr($b['end_hour']) ?></td>
                    <td>
                        <?php
                        if (!empty($b['seats'])) {
                            echo e(str_replace(',', ', ', $b['seats']));
                        } elseif (!empty($b['group_size'])) {
                            echo (int)$b['group_size'] . ' people';
                        } else {
                            echo 'Whole room';
                        }
                        ?>
                    </td>
                    <td>
                        <span class="pill <?= strtolower(e($b['status'])) ?>">
                            <?= e($b['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($b['status'] === 'Confirmed'): ?>
                            <form method="post"
                                  data-confirm="Cancel this booking? The student will be notified.">
                                <input type="hidden" name="a" value="cancel">
                                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                <button class="link">Cancel</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- Space management -->
    <h2 id="manage">Spaces</h2>

    <div class="tbl">
        <table>
            <tr>
                <th>Photo</th>
                <th>Name</th>
                <th>Floor</th>
                <th>Sound</th>
                <th>Capacity</th>
                <th>Booking</th>
                <th></th>
            </tr>

            <?php foreach ($sp as $s): ?>
                <tr>
                    <td>
                        <?php
                        $image = spaceImg($s['image'], $s['name']);

                        if ($image) {
                            echo '<img class="thumb" src="' .
                                e($image) . '" alt="">';
                        } else {
                            echo '—';
                        }
                        ?>
                    </td>
                    <td><?= e($s['name']) ?></td>
                    <td><?= e($s['floor']) ?></td>
                    <td><?= e($s['sound']) ?></td>
                    <td><?= (int)$s['capacity'] ?></td>
                    <td><?= $s['reservable'] ? 'Reservable' : 'Walk-in' ?></td>
                    <td class="acts">
                        <a class="link" href="?edit=<?= (int)$s['id'] ?>#form">Edit</a>

                        <form method="post" data-confirm="Delete this space?">
                            <input type="hidden" name="a" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <button class="link">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- Add/edit space form -->
    <form id="form" class="panel" method="post" enctype="multipart/form-data">
        <h3><?= $edit ? 'Edit space' : 'Add a space' ?></h3>

        <input type="hidden" name="a" value="<?= $edit ? 'edit' : 'add' ?>">

        <?php if ($edit): ?>
            <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
            <input type="hidden" name="old" value="<?= e($edit['image']) ?>">
        <?php endif; ?>

        <label>
            Name
            <input name="name" required value="<?= e($edit['name'] ?? '') ?>">
        </label>

        <label>
            Floor
            <input name="floor" required value="<?= e($edit['floor'] ?? '') ?>">
        </label>

        <label>
            Sound
            <select name="sound">
                <?php foreach (['Silent', 'Quiet', 'Collaborative'] as $o): ?>
                    <option value="<?= e($o) ?>"
                        <?= (($edit['sound'] ?? '') === $o) ? 'selected' : '' ?>>
                        <?= e($o) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Capacity
            <input type="number" name="capacity" min="1" required
                   value="<?= e($edit['capacity'] ?? '') ?>">
        </label>

        <label>
            Purpose
            <select name="purpose">
                <?php foreach (['Individual Study', 'Group Study', 'Presentations'] as $o): ?>
                    <option value="<?= e($o) ?>"
                        <?= (($edit['purpose'] ?? '') === $o) ? 'selected' : '' ?>>
                        <?= e($o) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Description
            <input name="description" value="<?= e($edit['description'] ?? '') ?>">
        </label>

        <label>
            Facilities
            <input name="facilities" value="<?= e($edit['facilities'] ?? '') ?>">
        </label>

        <label>
            User type
            <select name="user_type">
                <?php foreach (['Students', 'Staff', 'Students and staff'] as $o): ?>
                    <option value="<?= e($o) ?>"
                        <?= (($edit['user_type'] ?? '') === $o) ? 'selected' : '' ?>>
                        <?= e($o) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Photo (JPG, PNG, WebP)
            <input type="file" name="image"
                   accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
        </label>

        <label class="chk">
            <input type="checkbox" name="reservable"
                <?= (!$edit || $edit['reservable']) ? 'checked' : '' ?>>
            Reservable online
        </label>

        <button class="btn"><?= $edit ? 'Save changes' : 'Add space' ?></button>
    </form>

    <!-- Account -->
    <form id="account" class="panel" method="post">
        <h3>My account: change password</h3>
        <input type="hidden" name="a" value="pw">

        <label>
            Current password
            <input type="password" name="cur" required>
        </label>

        <label>
            New password
            <input type="password" name="new" minlength="6" required>
        </label>

        <label>
            Confirm new password
            <input type="password" name="new2" minlength="6" required>
        </label>

        <button class="btn">Update password</button>
    </form>

    <!-- =========================================
         ANALYTICS
         ========================================= -->

    <section class="analytics" id="analytics">
        <h2>Booking Analytics</h2>
        <p class="analytics-intro">
            Booking trends, booking status, popular rooms, and today's seat availability.
        </p>

        <div class="analytics-nav">
            <a href="#daily-analytics">Daily bookings</a>
            <a href="#status-analytics">Booking status</a>
            <a href="#popular-analytics">Popular rooms</a>
            <a href="#availability-analytics">Room availability</a>
        </div>

        <div class="analytics-grid">

            <article class="analytics-card" id="daily-analytics">
                <h3>Booking Activity</h3>
                <p>Bookings per day over the last seven days</p>
                <div class="analytics-graph">
                    <canvas id="dailyChart"></canvas>
                </div>
            </article>

            <article class="analytics-card" id="status-analytics">
                <h3>Booking Status</h3>
                <p>Green = confirmed; red = cancelled</p>
                <div class="analytics-graph">
                    <canvas id="statusChart"></canvas>
                </div>
            </article>

            <article class="analytics-card" id="popular-analytics">
                <h3>Most Popular Rooms</h3>
                <p>Top five rooms by total recorded bookings</p>
                <div class="analytics-graph">
                    <canvas id="popularChart"></canvas>
                </div>
            </article>

            <article class="analytics-card" id="availability-analytics">
                <h3>Room Availability Today</h3>
                <p>
                    Each full bar represents room capacity.
                    Coloured sections are reserved seats; grey sections are available seats.
                </p>
                <div class="analytics-graph" style="height:360px">
                    <canvas id="availabilityChart"></canvas>
                </div>
            </article>

        </div>
    </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
/* PHP data */
const dailyLabels = <?= json_encode(
    $dailyLabels,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;
const dailyValues = <?= json_encode($dailyValues) ?>;

const statusLabels = <?= json_encode(
    $statusLabels,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;
const statusValues = <?= json_encode($statusValues) ?>;

const popularLabels = <?= json_encode(
    $popularLabels,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;
const popularValues = <?= json_encode($popularValues) ?>;

const availabilityLabels = <?= json_encode(
    $availabilityLabels,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;
const availabilityCapacity = <?= json_encode($availabilityCapacity) ?>;
const availabilityReserved = <?= json_encode($availabilityReserved) ?>;
const availabilityAvailable = <?= json_encode($availabilityAvailable) ?>;

Chart.defaults.font.family = 'Arial, sans-serif';
Chart.defaults.color = '#64748b';

/* Consistent room colours */
const roomColours = {
    'Silent Reading Hall': '#2563EB',
    'Quiet Study Pods': '#16A34A',
    'Discussion Room A': '#EA580C',
    'Discussion Room B': '#9333EA',
    'Presentation Studio': '#DB2777',
    'Research Loft': '#0891B2'
};

const fallbackColours = [
    '#64748B', '#CA8A04', '#0D9488',
    '#7C3AED', '#E11D48'
];

function roomColour(name, index) {
    return roomColours[name] ||
        fallbackColours[index % fallbackColours.length];
}

/* Chart 1: daily bookings */
new Chart(document.getElementById('dailyChart'), {
    type: 'line',
    data: {
        labels: dailyLabels,
        datasets: [{
            label: 'Bookings',
            data: dailyValues,
            borderColor: '#6558D3',
            backgroundColor: 'rgba(101, 88, 211, 0.15)',
            fill: true,
            tension: 0.35,
            pointRadius: 4,
            pointHoverRadius: 7,
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0 }
            }
        }
    }
});

/* Chart 2: booking status circle */
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: statusLabels,
        datasets: [{
            data: statusValues,
            backgroundColor: statusLabels.map(status => {
                if (status === 'Confirmed') return '#16A34A';
                if (status === 'Cancelled') return '#DC2626';
                return '#64748B';
            }),
            borderColor: '#FFFFFF',
            borderWidth: 3,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '55%',
        plugins: {
            legend: {
                display: true,
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce(
                            (sum, value) => sum + value, 0
                        );
                        const count = context.parsed;
                        const percentage = total
                            ? ((count / total) * 100).toFixed(1)
                            : '0.0';

                        return `${context.label}: ${count} (${percentage}%)`;
                    }
                }
            }
        }
    }
});

/* Chart 3: popular rooms, each with its own colour */
new Chart(document.getElementById('popularChart'), {
    type: 'bar',
    data: {
        labels: popularLabels,
        datasets: [{
            label: 'Total bookings',
            data: popularValues,
            backgroundColor: popularLabels.map(roomColour),
            borderRadius: 6,
            maxBarThickness: 38
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            x: {
                beginAtZero: true,
                ticks: { precision: 0 }
            }
        }
    }
});

/* Chart 4: room capacity, reserved seats and available seats */
const availabilityColours = availabilityLabels.map(roomColour);

new Chart(document.getElementById('availabilityChart'), {
    type: 'bar',
    data: {
        labels: availabilityLabels,
        datasets: [
            {
                label: 'Reserved seats',
                data: availabilityReserved,
                backgroundColor: availabilityColours,
                borderRadius: 4,
                stack: 'seats'
            },
            {
                label: 'Available seats',
                data: availabilityAvailable,
                backgroundColor: '#E5E7EB',
                borderRadius: 4,
                stack: 'seats'
            }
        ]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    afterTitle: function(items) {
                        const i = items[0].dataIndex;
                        return 'Total capacity: ' + availabilityCapacity[i];
                    },
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.x;
                    }
                }
            }
        },
        scales: {
            x: {
                stacked: true,
                beginAtZero: true,
                ticks: { precision: 0 },
                title: {
                    display: true,
                    text: 'Seats'
                }
            },
            y: {
                stacked: true
            }
        }
    }
});
</script>

<script src="assets/js/common.js"></script>
<script src="assets/js/admin.js"></script>

</body>
</html>
