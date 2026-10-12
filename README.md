# Library Seat Reservation System

CS12012 – Web Development · Intake 43 · Group Assignment

A web system where students browse the library's study spaces, choose a seat, and reserve it online, and where librarians manage spaces and bookings.
Built only with **HTML, CSS, JavaScript, PHP and MySQL**.

## Group details

| Index No. | Name | Degree Programme | Individual Contribution |
|-----------|------|------------------|-------------------------|
| [Index No.] | Imasha Sithumini | [Degree Programme] | Reservation page (booking pop-up with seat map) and student dashboard |
| [Index No.] | [Member 2 name] | [Degree Programme] | Login and register module |
| [Index No.] | [Member 3 name] | [Degree Programme] | Admin console |
| [Index No.] | [Member 4 name] | [Degree Programme] | [Their part] |

Group ID: [Group ID]

## Features

- Browse all study spaces without signing in; only signed-in students can reserve
- Filter spaces by floor, sound level, capacity, booking type and purpose
- Booking pop-up with space details, seat selection, and a confirmation message
- Student dashboard: bookings, study tracker, calendar with upcoming events, notifications
- Librarian console: manage spaces (with photos) and bookings, separate login
- Two separate databases: `lsrs_admin` (admins, spaces) and `lsrs_student` (students, bookings, notifications)

## Modules

| Module | Files |
|--------|-------|
| Login / Register | `login.php`, `logout.php`, `assets/css/login.css`, `assets/js/login.js` |
| Reservation | `reserve.php`, `reservation_api.php`, `assets/css/reservation.css`, `assets/js/reservation.js` |
| Student dashboard | `dashboard.php`, `profile.php`, `assets/css/dashboard.css`, `assets/js/dashboard.js` |
| Admin console | `admin.php`, `assets/css/admin.css`, `assets/js/admin.js` |
| Shared | `config.php`, `nav.php`, `footer.php`, `assets/css/base.css`, `assets/js/common.js`, `sql/` |

## How to run

1. Install XAMPP and start **Apache** and **MySQL**.
2. Copy this folder into `C:\xampp\htdocs\`.
3. Open `http://localhost/phpmyadmin`, click **Import**, and import `sql/schema.sql`. This creates both databases.
4. If your MySQL has a password, edit `DB_USER` and `DB_PASS` in `config.php`. XAMPP's default (user `root`, empty password) works as is.
5. Open `http://localhost/<folder-name>/` (for example `http://localhost/lsrs/`).

**Librarian login:** `login.php`, then the Librarian tab. Demo account: `admin@library.local` / `admin123`

## Pictures

Optional. See `assets/img/README.txt` and `assets/img/spaces/README.txt` for the exact file names (`hero.jpg`, `logo.png`, one photo per room).

## Future enhancements

Email and SMS notifications, online payments for paid rooms, QR check-in at the seat, a mobile app, and library events on the dashboard calendar.
reservation-system\
