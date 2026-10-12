
<?php
// Shared footer, back-to-top button, and live clock script.
// Update library contact details in config.php.
?>

<!-- Main website footer -->
<footer class="site">

    <div class="fg">

        <!-- Library information and contact details -->
        <div>

            <div class="brand">
                <!-- Library logo: assets/img/logo.png -->
                <img
                    src="assets/img/logo.png"
                    alt=""
                    onerror="this.remove()"
                >

                <?= e(LIB_NAME) ?>
            </div>

            <p>
                Your gateway to quiet places and focused work.
                Reserve a seat, room or studio in under a minute.
            </p>

            <!-- Library contact information -->
            <dl class="ct">
                <dt>Phone</dt>
                <dd><?= e(LIB_PHONE) ?></dd>

                <dt>Email</dt>
                <dd><?= e(LIB_EMAIL) ?></dd>

                <dt>Address</dt>
                <dd><?= e(LIB_ADDRESS) ?></dd>
            </dl>

        </div>

        <!-- Quick navigation links -->
        <div>
            <h4>Quick links</h4>

            <ul>
                <li>
                    <a href="reserve.php">Reserve a space</a>
                </li>

                <?php if (($_SESSION['role'] ?? '') === 'student'): ?>

                    <li>
                        <a href="dashboard.php">My dashboard</a>
                    </li>

                    <li>
                        <a href="profile.php">My profile</a>
                    </li>

                    <li>
                        <a href="logout.php">Sign out</a>
                    </li>

                <?php else: ?>

                    <li>
                        <a href="login.php">Sign in</a>
                    </li>

                <?php endif; ?>
            </ul>
        </div>

        <!-- Library services -->
        <div>
            <h4>Services</h4>

            <ul>
                <li>
                    <a href="reserve.php">Study rooms</a>
                </li>

                <li>
                    <a href="reserve.php">Group study</a>
                </li>

                <li>
                    <a href="reserve.php">Presentation studio</a>
                </li>

                <li>
                    <a href="mailto:<?= e(LIB_EMAIL) ?>">Help desk</a>
                </li>
            </ul>
        </div>

        <!-- Library opening hours and social media links -->
        <div>
            <h4>Library hours</h4>

            <div class="hrs">

                <p>
                    <span>Mon – Fri</span>
                    <b>9:00 AM – 5:00 PM</b>
                </p>

                <p>
                    <span>Saturday</span>
                    <b>9:00 AM – 5:00 PM</b>
                </p>

                <p>
                    <span>Sunday</span>
                    <b>9:00 AM – 5:00 PM</b>
                </p>

            </div>

            <!-- Replace # with the actual social media page URLs -->
            <div class="soc">

                <a
                    href="#"
                    aria-label="Facebook"
                    style="background:#3b5998"
                >
                    f
                </a>

                <a
                    href="#"
                    aria-label="Instagram"
                    style="background:#c13584"
                >
                    ig
                </a>

                <a
                    href="#"
                    aria-label="LinkedIn"
                    style="background:#0a66c2"
                >
                    in
                </a>

                <a
                    href="#"
                    aria-label="YouTube"
                    style="background:#e00"
                >
                    yt
                </a>

                <a
                    href="#"
                    aria-label="X"
                    style="background:#111"
                >
                    x
                </a>

            </div>
        </div>

    </div>

    <!-- Copyright notice with the current year -->
    <p class="copy">
        &copy; <?= date('Y') ?>
        <?= e(LIB_NAME) ?>. All rights reserved.
    </p>

</footer>

<!-- Back-to-top button -->
<button id="top" aria-label="Back to top">
    &uarr;
</button>

<!-- Shared JavaScript for the live clock and common interactions -->
<script src="assets/js/common.js"></script>
