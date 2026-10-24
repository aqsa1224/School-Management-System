# School Management System

A role-based school management web application built with **Laravel 12**, **MySQL** and **Bootstrap 5**, with a black and gold theme. It covers students, teachers, classes, attendance, exams and marks, fee collection (vouchers, receipts, PDFs) and reports.

![Dashboard](doc/screenshots/01-dashboard.png)

## Features

- **Authentication and roles**: login/logout, password reset, profile and password change. Four staff roles with permissions defined in one file (`config/roles.php`): Admin, Teacher, Accountant, Receptionist.
- **Dashboard**: live counts of students, teachers and subjects.
- **Students**: add, view, edit, delete, photo upload, search and filters. Teachers only see students of their own classes.
- **Teachers**: manage teachers and assign them to classes and subjects.
- **Classes and sections**: classes with sections, and subjects linked to classes.
- **Attendance**: bulk marking per class, view and report.
- **Exams and marks**: create exams, enter and edit marks, result sheets and printable result cards.
- **Fees**: fee types, single and bulk vouchers, payments, printable receipts and vouchers with PDF export.
- **Reports**: students, attendance, results, fees, daily collection, monthly collection.
- **Settings**: school name, address, logo, currency and pass percentage.
- **User management**: create staff users, activate or deactivate them. The last active admin is protected from deletion.

## Screenshots

| | |
|---|---|
| ![Login](doc/screenshots/02-login.png) | ![Students](doc/screenshots/03-students.png) |
| Login | Students list |
| ![Attendance](doc/screenshots/04-attendance.png) | ![Marks](doc/screenshots/05-marks.png) |
| Attendance | Marks entry |
| ![Fee voucher](doc/screenshots/06-fee-voucher.png) | ![Reports](doc/screenshots/07-reports.png) |
| Fee voucher | Reports |

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2+, Laravel 12 |
| Database | MySQL (XAMPP for local development) |
| Frontend | Blade, Bootstrap 5.3, Bootstrap Icons, SCSS, Vite |
| PDF | dompdf |
| Auth scaffolding | Laravel Breeze |
| Tests | Pest |

## Getting started (Windows + XAMPP)

**Requirements:** PHP 8.2+, Composer, Node.js and npm, XAMPP (Apache + MySQL).

1. Start **MySQL** in the XAMPP Control Panel and create an empty database named `school_management_db` (phpMyAdmin).
2. Install and configure:

```powershell
cd backend
composer install
npm install
copy .env.example .env
php artisan key:generate
```

3. Open `.env` and set your database details:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=school_management_db
DB_USERNAME=root
DB_PASSWORD=
```

4. Create the tables and the starting data (admin user, classes, settings):

```powershell
php artisan migrate
php artisan db:seed
php artisan storage:link
```

5. Build assets and run the app:

```powershell
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000`.

> The seeder creates an admin account (`admin@school.test`). **Change its password right after the first login.**

### Optional: demo data

For screenshots or trying the system out only (never on a real school's database):

```powershell
php artisan db:seed --class=DemoDataSeeder
```

This adds sample subjects, students, an accountant, a receptionist and a teacher account (all with the demo password `password`).

## Roles and permissions

| Role | Can do |
|---|---|
| Admin | Everything |
| Teacher | View students and mark attendance/marks for their own classes, academic reports |
| Accountant | View students, manage fees, fee types, fee reports |
| Receptionist | View and manage students |

Permissions are edited in `config/roles.php`.

## Running the tests

```powershell
php artisan test
```

Tests use an in-memory SQLite database (see `phpunit.xml`), so your MySQL data is not touched.

## Project structure

```
backend/
├── app/Http/Controllers/   # one controller per module
├── app/Models/
├── config/roles.php        # roles and permissions
├── database/migrations/
├── database/seeders/       # DatabaseSeeder (starter), DemoDataSeeder (demo)
├── resources/views/        # Blade views per module
├── resources/scss/         # black and gold theme (_variables.scss)
├── routes/web.php
└── tests/Feature/          # Pest tests per module
```


## License
 project is proprietary.
