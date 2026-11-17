# Company Staff Portal

A PHP web platform for managing the **COMPANY** database from *Fundamentals of Database Systems* (Elmasri & Navathe). HR staff can add employees, assign them to projects, and search the staff directory. Every form submission is saved to a MariaDB/MySQL database.

The platform is built on the PHP database examples in **Chapter 11 ("Web Database Programming Using PHP")** and uses the COMPANY schema and sample data from **Chapter 5**.

![Dashboard](docs/screenshots/dashboard.png)

---

## Contents

1. [Features](#features)
2. [Requirements](#requirements)
3. [Setup (XAMPP on Windows)](#setup-xampp-on-windows)
4. [Using the platform](#using-the-platform)
5. [How the forms talk to the database](#how-the-forms-talk-to-the-database)
6. [Security](#security)
7. [How this adapts the textbook examples](#how-this-adapts-the-textbook-examples)
8. [Unique features](#unique-features)
9. [Project structure](#project-structure)
10. [Troubleshooting](#troubleshooting)

---

## Features

| Page | What it does | Tables used |
|---|---|---|
| **Dashboard** (`index.php`) | Live totals, department headcount and average salary, recent form activity | all |
| **Add Employee** (`add_employee.php`) | Main form. Validates and saves a new employee | `EMPLOYEE` (insert) |
| **Assign Hours** (`log_hours.php`) | Second form. Puts an employee on a project for N hours a week, or updates existing hours | `WORKS_ON` (insert or update) |
| **Directory** (`employees.php`) | Search by name or address, filter by department, sort, export to CSV | `EMPLOYEE`, `DEPARTMENT`, `WORKS_ON` (joins) |

---

## Requirements

- **XAMPP** 8.x (Apache + PHP 8.x + MariaDB). Any PHP 8 / MySQL 5.7+ / MariaDB 10.4+ stack also works.
- The PHP extension `pdo_mysql`, which is enabled by default in XAMPP.

---

## Setup (XAMPP on Windows)

### 1. Get the code into `htdocs`

```cmd
cd C:\xampp\htdocs
git clone https://github.com/<your-username>/company-portal.git
```

You can also download the ZIP from GitHub and unzip it so the folder is `C:\xampp\htdocs\company-portal`.

### 2. Start Apache and MySQL

Open the **XAMPP Control Panel** and click **Start** next to both **Apache** and **MySQL**.

### 3. Create the database

Run the setup script as the MySQL root user. On XAMPP, root has no password by default, so just press Enter at the password prompt.

```cmd
cd C:\xampp\htdocs\company-portal
C:\xampp\mysql\bin\mysql -u root -p < database\setup.sql
```

This script does the following:
- Creates a new database, **`company_portal`**. It doesn't touch any existing `company` database.
- Creates a dedicated MySQL account, **`portal_app`**, that can only read and write rows in `company_portal`.
- Creates the tables `EMPLOYEE`, `DEPARTMENT`, `DEPT_LOCATIONS`, `PROJECT`, `WORKS_ON` and `SUBMISSION_LOG`.
- Loads the textbook's sample data (8 employees, 3 departments, 6 projects).

> You can also use phpMyAdmin instead: open http://localhost/phpmyadmin, go to the **Import** tab, choose `database/setup.sql`, then click **Go**.

> **Note:** running `setup.sql` again resets the database to the sample data.

### 4. Create your config file

```cmd
copy config\config.example.php config\config.php
```

Open `config\config.php`. If you changed the `portal_app` password in `setup.sql` (recommended), change `db_password` here to match. `config.php` is listed in `.gitignore`, so your password is never pushed to GitHub.

### 5. Open the portal

Go to **http://localhost/company-portal/**

If the Dashboard shows 8 employees, the connection works.

---

## Using the platform

### Add an employee
1. Click **Add Employee** in the top menu.
2. Fill in the form. Every field maps to a column in `EMPLOYEE`:

| Form field | Column | Rules |
|---|---|---|
| First name | `Fname` | Required. Letters, spaces, `'` and `-` only. Max 15 characters |
| Middle initial | `Minit` | Optional. One letter, saved in uppercase |
| Last name | `Lname` | Required. Max 15 characters |
| SSN | `Ssn` (primary key) | Required. Exactly 9 digits; dashes or spaces are removed (`123-45-6789` works). Must be unique |
| Birth date | `Bdate` | Required. Employee must be 16–100 years old |
| Sex | `Sex` | M or F |
| Address | `Address` | Required. Max 30 characters |
| Salary | `Salary` | Required. 0 – 9,999,999. Commas and `$` are allowed |
| Department | `Dno` (foreign key) | Required. Dropdown filled from the `DEPARTMENT` table |
| Supervisor | `Super_ssn` (foreign key) | Optional. Dropdown filled from the `EMPLOYEE` table |

3. Click **Save employee**.
   - **If every field is valid**, the row is inserted and you're taken to the Directory, where the new employee is highlighted with a green **new** badge.
   - **If any field is invalid**, nothing is saved. The form comes back with your values still filled in and a red message under each field that needs fixing.

![Validation](docs/screenshots/form-validation.png)

### Assign project hours
1. Click **Assign Hours**, or click **Hours** next to an employee in the Directory.
2. Pick the employee. The panel on the right shows their current projects and total weekly hours.
3. Pick a project, enter the hours (0.5–60, in half-hour steps), and click **Save hours**.
4. If the employee is already on that project, their hours are **updated** instead of producing a duplicate-key error.
5. The portal won't let an employee's total go over **60 hours a week** across all projects. If it would, the error message tells you the most hours you can still assign.

![Assign hours](docs/screenshots/assign-hours.png)

### Search the directory
- Type part of a name or address (for example `Wong` or `Houston`), pick a department, choose a sort order, and click **Search**.
- **Export CSV** downloads the current search results, which you can open in Excel.
- SSNs are masked as `***-**-1234` on screen.

![Directory](docs/screenshots/directory.png)

---

## How the forms talk to the database

Here's what happens when someone submits **Add Employee**:

```
Browser                    add_employee.php                       MariaDB (company_portal)
───────                    ────────────────                       ────────────────────────
POST form + CSRF token ──► 1. csrf_check(): reject forged requests
                           2. trim + normalise every $_POST value
                           3. validate each field (rules above)
                           4. confirm Dno / Super_ssn exist  ─────► SELECT 1 FROM DEPARTMENT WHERE Dnumber = ?
                           5. INSERT with ? placeholders    ─────► INSERT INTO EMPLOYEE (...) VALUES (?,?,?,?,?,?,?,?,?,?)
                           6. on error 1062 (duplicate SSN):
                              show "already exists" under SSN
                           7. log the attempt              ─────► INSERT INTO SUBMISSION_LOG (...)
◄── 302 redirect ───────── 8. flash message + redirect to the Directory (Post/Redirect/Get)
```

- **Connection** (`includes/db.php`): one PDO connection per request, built from `config/config.php`, using `utf8mb4` and exceptions for errors.
- **Prepared statements**: user input is never pasted into SQL. It's always passed as `?` parameters, so input like `'; DROP TABLE EMPLOYEE; --` is stored as plain text and can't change the query.
- **Post/Redirect/Get**: after a successful save the browser is redirected, so refreshing the page can't submit the same form twice.
- **Assign Hours** works the same way, but saves with:
  ```sql
  INSERT INTO WORKS_ON (Essn, Pno, Hours) VALUES (?, ?, ?)
  ON DUPLICATE KEY UPDATE Hours = VALUES(Hours);
  ```

To check the saved data yourself:
```sql
mysql -u root -p company_portal
SELECT * FROM EMPLOYEE ORDER BY Lname;
SELECT * FROM WORKS_ON WHERE Essn = '<ssn>';
SELECT * FROM SUBMISSION_LOG ORDER BY Log_id DESC;
```

---

## Security

| Threat | Protection | Where |
|---|---|---|
| SQL injection | PDO prepared statements with `?` placeholders. The sort column comes from a fixed list of allowed values | every query |
| Cross-site scripting (XSS) | All output is escaped with `htmlspecialchars` through the `e()` helper | every page |
| Cross-site request forgery (CSRF) | Each form has a random session token, checked with `hash_equals` | `includes/helpers.php` |
| Stolen DB credentials | The app uses the limited `portal_app` account, not `root`. It can't `DROP` or `ALTER` tables (tested) | `database/setup.sql` |
| Password leaked on GitHub | Real settings live in `config/config.php`, which is in `.gitignore` | `.gitignore` |
| Browsing to private files | `Require all denied` in `.htaccess` for `config/`, `includes/`, `database/`, `docs/` | `.htaccess` files |
| Error messages revealing internals | Raw DB errors go to the PHP error log, and users see a plain message (unless `debug` is on) | `includes/db.php` |
| Bad data in the DB | Server-side validation, plus `CHECK`, `FOREIGN KEY` and `PRIMARY KEY` constraints in the schema | forms + `setup.sql` |
| Session cookie theft | `HttpOnly` and `SameSite=Lax` session cookies | `includes/helpers.php` |

> **Production note:** for a real deployment, serve the site over HTTPS and use a strong `portal_app` password.

---

## How this adapts the textbook examples

The textbook's Chapter 11 examples use the **PEAR DB** library. PEAR DB is no longer maintained and doesn't run on PHP 8, so this project uses **PDO**, PHP's built-in database layer. Each textbook concept maps directly to PDO:

| Textbook (Ch. 11) | This project |
|---|---|
| Fig. 11.1: form that posts back to itself and reads `$_POST` | `add_employee.php` and `log_hours.php` post to themselves the same way |
| Fig. 11.6 line 1: `DB::connect('mysqli://user:pass@host/db')` | `new PDO('mysql:host=…;dbname=…', $user, $pass)` in `includes/db.php` |
| Fig. 11.6 line 2: `DB::isError($d)` + `die()` | `try { … } catch (PDOException $e)` with a user-friendly message |
| Fig. 11.6 line 9: `setErrorHandling(PEAR_ERROR_DIE)` | `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` |
| Fig. 11.6 lines 11–12: INSERT built from `$_POST` (the book warns this is unsafe) | Not used |
| Fig. 11.6 lines 13–15: INSERT with `?` placeholders (the book's safe version) | Used for **every** query |
| Fig. 11.7 lines 8–13: dynamic query from user input | Directory search: the `WHERE` clause is built from the filters the user picks |
| Fig. 11.7 lines 14–17: `getAll()` + `foreach` | `fetchAll()` + `foreach` when printing table rows |
| Fig. 11.6 lines 3–7: `CREATE TABLE` from PHP | Moved to `setup.sql`, so the web account never needs `CREATE` permission |

The schema goes beyond the book's simplified 4-column `EMPLOYEE` example. It uses the full COMPANY schema (Fig. 5.7) with its real foreign keys, including the circular `EMPLOYEE` ↔ `DEPARTMENT` reference, which the setup script handles by pausing foreign-key checks while it loads the sample data.

---

## Unique features

- **Two linked forms.** One adds an entity (`EMPLOYEE`). The other writes to a relationship table (`WORKS_ON`) and uses an upsert, so assigning an employee to a project twice updates the hours instead of failing.
- **Business rule enforced across rows.** The portal rejects any assignment that would push an employee over 60 hours a week across all projects, and the error message says how many hours are still available.
- **Audit trail.** The `SUBMISSION_LOG` table records every submission (saved, updated or rejected) with a timestamp, and the Dashboard shows the most recent ones. SSNs in the log are masked.
- **Friendly duplicate handling.** MySQL error 1062 (duplicate primary key) appears as "An employee with this SSN already exists" under the SSN field, instead of a database error page.
- **Forgiving input.** SSNs typed with dashes or spaces, salaries with `$` or commas, and lowercase middle initials are all accepted and cleaned up before saving.
- **Dropdowns filled from the database.** Department, supervisor and project lists always match the data, so the form can't send an ID that doesn't exist (and the server checks again in case someone tampers with the form).
- **Shareable searches and CSV export.** Searches use GET parameters, so a filtered Directory URL can be bookmarked, and **Export CSV** downloads exactly the rows on screen.
- **Least-privilege database account.** The app can't drop or alter tables even if its password leaks.
- **Responsive layout.** Works on phone screens as well as desktop.

---

## Project structure

```
company-portal/
├── index.php              Dashboard
├── add_employee.php       Form 1: insert into EMPLOYEE
├── log_hours.php          Form 2: insert/update WORKS_ON
├── employees.php          Directory: search, filter, sort, CSV export
├── assets/
│   └── style.css
├── config/
│   ├── config.example.php Template (committed)
│   ├── config.php         Your real settings (git-ignored)
│   └── .htaccess          Blocks web access
├── includes/
│   ├── db.php             PDO connection
│   ├── helpers.php        CSRF, escaping, flash messages, audit log
│   ├── header.php         Shared page header + navigation
│   ├── footer.php
│   └── .htaccess
├── database/
│   ├── setup.sql          Database, app user, tables, sample data
│   └── .htaccess
└── docs/screenshots/
```

---

## Troubleshooting

| Problem | Fix |
|---|---|
| *"Missing config/config.php"* | Do setup step 4 |
| *"Cannot connect to the database"* | Check that MySQL is running in XAMPP, that you ran `setup.sql`, and that the password in `config.php` matches the one in `setup.sql`. Set `'debug' => true` to see the exact error |
| `Access denied for user 'portal_app'` | The passwords don't match. Either edit `config.php`, or run `setup.sql` again |
| `ERROR 1064` when running `setup.sql` | Run the whole file with `<` as shown in step 3, rather than pasting it in pieces |
| Page shows raw PHP code | Open it through `http://localhost/...`, not by double-clicking the file |
| Blank page | Check `C:\xampp\apache\logs\error.log` |

---
