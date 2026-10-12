
<?php

// Shared navigation header.
// Set $active to 'reserve', 'dashboard', or 'profile'
// before including this file.

$isStudent = ($_SESSION['role'] ?? '') === 'student';
$active = $active ?? '';
$unreadCount = 0;

// Count unread notifications for the logged-in student.
if ($isStudent) {
    try {
        $stmt = sdb()->prepare(
            "SELECT COUNT(*) 
             FROM notifications 
             WHERE student_id = ? AND is_read = 0"
        );

        $stmt->execute([(int) $_SESSION['id']]);
        $unreadCount = (int) $stmt->fetchColumn();

    } catch (Exception $exception) {
        // Keep the header working if notifications cannot be loaded.
    }
}
?>

<!-- Main navigation header -->
<header class="nav">

    <!-- Library logo and brand name -->
    <a class="brand" href="reserve.php">
        <!-- Logo image: assets/img/logo.png -->
        <img
            src="assets/img/logo.png"
            alt=""
            onerror="this.remove()"
        >
        Reading Room
    </a>

    <nav>

        <!-- Live clock -->
        <span id="clock" class="clock"></span>

        <!-- Space reservation link -->
        <a
            href="reserve.php"
            class="<?= $active === 'reserve' ? 'cur' : '' ?>"
        >
            Reserve a space
        </a>

        <?php if ($isStudent): ?>

            <!-- Student dashboard -->
            <a
                href="dashboard.php"
                class="<?= $active === 'dashboard' ? 'cur' : '' ?>"
            >
                My dashboard
            </a>

            <!-- Notification bell and unread count -->
            <a
                class="bell<?= $unreadCount ? ' ring' : '' ?>"
                href="dashboard.php#notifications"
                title="Notifications"
                aria-label="Notifications"
            >
                <svg
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/>
                </svg>

                <?php if ($unreadCount): ?>
                    <em><?= $unreadCount ?></em>
                <?php endif; ?>
            </a>

            <!-- Student profile link with initial and first name -->
            <a
                class="me <?= $active === 'profile' ? 'cur' : '' ?>"
                href="profile.php"
                title="My profile"
            >
                <i>
                    <?= e(strtoupper(mb_substr($_SESSION['name'], 0, 1))) ?>
                </i>

                <?= e(explode(' ', $_SESSION['name'])[0]) ?>
            </a>

            <!-- Sign-out button -->
            <a class="btn sm ghost" href="logout.php">
                Sign out
            </a>

        <?php else: ?>

            <!-- Sign-in button for guests and other non-student users -->
            <a class="btn sm" href="login.php">
                Sign in
            </a>

        <?php endif; ?>

    </nav>

</header>