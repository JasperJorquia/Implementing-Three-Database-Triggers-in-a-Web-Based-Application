# Library Management System — Database Triggers Demo

A PHP and MySQL library management application demonstrating database triggers for borrowing, returning, and deleting books. It also tracks individual physical copies and provides reusable QR codes for those copies.

## Project files

- `index.php` — Dashboard and application interface
- `action.php` — Handles book, copy, borrow, return, and delete actions
- `db.php` — MySQL connection settings
- `includes/dashboard-data.php` — Dashboard queries and scanned-copy handling
- `assets/css/app.css` — Application styles and responsive layouts
- `assets/js/app.js` — Filtering, forms, dialogs, and QR interactions
- `database-schema.sql` — Fresh-install database, sample data, and all three triggers
- `migrate-copy-qr.sql` — One-time migration for an existing database
- `Open Library Management System.url` — Windows shortcut to the local app

## Requirements

- Windows with [XAMPP](https://www.apachefriends.org/)
- Apache and MySQL (or MariaDB) enabled in XAMPP
- PHP with the PDO MySQL extension enabled (included with the standard XAMPP PHP setup)
- A browser; an internet connection is needed to load the QR scanner/generator library from its CDN

## Install and run

1. Put this project folder inside XAMPP's `htdocs` directory.
2. Open the XAMPP Control Panel and start **Apache** and **MySQL**.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`.
4. Create/select the database named `library_db`, then import [`database-schema.sql`](./database-schema.sql). It creates the tables, three triggers, and sample books and copies. Import it only into a clean database; it does not drop existing tables.
5. Check the connection settings in [`db.php`](./db.php). The defaults are host `localhost`, database `library_db`, user `root`, and an empty password, which match a typical local XAMPP setup. If your MySQL credentials differ, update these values.
6. Open `http://localhost/Implementing-Three-Database-Triggers-in-a-Web-Based-Application/`, or double-click `Open Library Management System.url`.

If the page reports a database connection error, confirm MySQL is running, the database exists, and the credentials in `db.php` match your MySQL configuration.

### Existing database upgrade

Back up the database first. For an existing library database, select `library_db` in phpMyAdmin and import [`migrate-copy-qr.sql`](./migrate-copy-qr.sql) once. This migration adds physical-copy tracking and assigns copies to active loans. Do not run it a second time, and do not run it on a fresh database that already uses `database-schema.sql`.

## Database triggers

The fresh-install schema creates these triggers:

| Trigger | Event | Database effect |
|---|---|---|
| `trg_after_borrow` | After a loan is inserted | Decreases `books.available_copies` by one and writes a `BOOK_BORROWED` row to `audit_logs`. |
| `trg_after_return` | After a loan is updated from `Borrowed` to `Returned` | Increases `books.available_copies` by one and writes a `BOOK_RETURNED` row to `audit_logs`. Repeated updates to an already returned loan do not increment the count again. |
| `trg_before_delete_book` | Before a book is deleted | Writes a `BOOK_DELETED` row containing the book details to `audit_logs`. |

### Verify the triggers and capture evidence

Run this check on a development database, not on data you need to preserve. In phpMyAdmin's **SQL** tab, create a temporary test book and one physical copy:

```sql
INSERT INTO books (title, author, isbn, available_copies)
VALUES ('Trigger Test Book', 'System Test', 'TRIGGER-TEST-001', 1);
SET @test_book_id = LAST_INSERT_ID();

INSERT INTO book_copies (book_id) VALUES (@test_book_id);
SET @test_copy_id = LAST_INSERT_ID();

SELECT @test_book_id AS test_book_id, @test_copy_id AS test_copy_id;
```

Keep the returned IDs. Use those numeric IDs in the following statements, replacing `BOOK_ID`, `COPY_ID`, and `LOAN_ID` with the actual values.

1. **Borrow trigger:** Insert a loan, then check that availability changed from `1` to `0` and that the audit row exists.

   ```sql
   INSERT INTO loans (book_id, copy_id, borrower_name, borrow_date)
   VALUES (BOOK_ID, COPY_ID, 'Trigger Test Borrower', CURDATE());
   SELECT LAST_INSERT_ID() AS test_loan_id;
   SELECT available_copies FROM books WHERE book_id = BOOK_ID;
   SELECT action, details FROM audit_logs
   WHERE action = 'BOOK_BORROWED' ORDER BY log_id DESC LIMIT 1;
   ```

2. **Return trigger:** Use the loan ID returned above. Check that availability returns to `1` and the return audit row exists.

   ```sql
   UPDATE loans
   SET status = 'Returned', return_date = CURDATE()
   WHERE loan_id = LOAN_ID AND status = 'Borrowed';
   SELECT available_copies FROM books WHERE book_id = BOOK_ID;
   SELECT action, details FROM audit_logs
   WHERE action = 'BOOK_RETURNED' ORDER BY log_id DESC LIMIT 1;
   ```

3. **Delete trigger:** Delete the temporary book and check that its deletion is recorded. The related test copy and loan are removed by the schema's foreign-key cascades; the audit entry remains.

   ```sql
   DELETE FROM books WHERE book_id = BOOK_ID;
   SELECT action, details FROM audit_logs
   WHERE action = 'BOOK_DELETED' ORDER BY log_id DESC LIMIT 1;
   ```

Capture screenshots of the SQL statements and their result rows (or save equivalent query output) for all three checks. The expected effects above describe what to verify; do not present them as evidence until you have run the checks successfully.

## Borrowing, returns, and reusable QR codes

1. In the book catalog, choose **Copies / QR** beside a title. Each physical copy has its own ID and QR code. Print a code once and attach it to that copy.
2. A scan of an available copy opens its borrow form; a scan of a borrowed copy opens its return confirmation. After return, the same QR label can be reused. A borrowed copy cannot be checked out twice.
3. Use **Add physical copy** to track another copy of a title. Adding a new title also creates one copy record per quantity entered.
4. Choose **Scan QR code** in the sidebar to use the device camera or scan an image. Camera use requires browser permission and a secure context such as `localhost` or HTTPS.

QR links use `http://localhost` and therefore open the app on the computer running XAMPP. A phone interprets `localhost` as the phone itself, so these QR links are intended for scanning on that same computer, not for accessing the laptop from a phone. The QR generator and scanner libraries load from a CDN, so an internet connection is needed when using those features.

## Submission checklist

- Include all application source files and required assets listed above.
- Include `database-schema.sql` as the database export; it contains the tables, relationships, sample data, and all three triggers.
- Include this README with setup, database configuration, run instructions, and trigger verification steps.
- Include screenshots or saved query output demonstrating the successful execution and database effects of each of the three triggers.
- Put the deliverables into one ZIP named `IM101_LastName_FirstName_DatabaseTriggers.zip`, replacing `LastName` and `FirstName` with your name.

The ZIP is a submission deliverable and is not generated automatically by the application.
