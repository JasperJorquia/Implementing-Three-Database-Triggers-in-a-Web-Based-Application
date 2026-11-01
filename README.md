## Library Management System — Database Triggers Demo

> A modern Library Management System built with PHP & MySQL showcasing **database triggers**.

## Project Structure

- `index.php` — Dashboard page and HTML template
- `includes/dashboard-data.php` — Database queries and page data
- `includes/qr-network.php` — Detects the laptop network address used by book QR codes
- `migrate-copy-qr.sql` — One-time migration for existing databases to track physical copies
- `assets/css/app.css` — App styles and responsive design
- `assets/js/app.js` — Tabs, forms, dialogs, filtering, and QR interactions
- `action.php` — Book and loan form submissions
- `db.php` — MySQL connection

## How to Run

### Prerequisites
- **XAMPP** installed with Apache and MySQL

### Steps
1. **Start XAMPP** — Enable both **Apache** and **MySQL** modules
2. **Create the Database** — Open phpMyAdmin (`http://localhost/phpmyadmin`) and run the SQL setup below
3. **Open the app** — On the computer running XAMPP, double-click `Open Library Management System.url` in the project folder, or visit `http://localhost/Implementing-Three-Database-Triggers-in-a-Web-Based-Application/`

---

## Borrow a Book with a Phone QR Code

1. In the book catalog, choose **Copies / QR** beside a title. Each physical copy has its own ID and QR code. Print the QR once and attach it to that copy.
2. The same copy QR is reusable: scanning an available copy opens its borrow form; scanning a borrowed copy opens its return confirmation. After return, that same label is ready for the next borrower. A borrowed copy cannot be checked out twice.
3. Use **Add physical copy** to add another individually tracked copy of a title. New titles also create one physical copy record for every copy quantity entered.
4. To use QR codes from a phone, open the app on the computer with its local network address instead of `localhost`. Find the computer's IPv4 address with `ipconfig`, then visit `http://COMPUTER-IP/Implementing-Three-Database-Triggers-in-a-Web-Based-Application/` on the computer.
5. The QR dialog detects the laptop's active network address and uses it in each copy code. Confirm the address is on the same Wi-Fi network as the phone; if it is unreachable, enter the laptop's Wi-Fi IPv4 address and choose **Update QR**. Apache must be allowed through the computer's firewall on the private network.

QR code generation loads a small JavaScript library from cdnjs when first needed, so an internet connection is required to create/print codes. Scanning the resulting QR code opens your local app; the book details and borrower name are submitted to your own XAMPP server.

### Existing database upgrade

Back up the database, then in phpMyAdmin select `library_db` → **Import** and run [`migrate-copy-qr.sql`](./migrate-copy-qr.sql) once. The migration creates individual copy records from each book's available quantity plus its active loans, then assigns each active loan to one copy. Previously returned loans remain in the history without a copy assignment. Do not run this migration a second time.

## Scan to Borrow or Return

- Choose **Scan QR code** in the sidebar to scan a QR using the device camera or an image.
- A physical-copy QR automatically opens the borrow form or return confirmation according to that copy's current status.
- Confirm returns before recording them.
- Camera scanning requires browser camera permission and a secure context such as `localhost` or HTTPS. When accessing the laptop by its Wi-Fi IP over HTTP, use **Scan QR image** instead.
- The scanner library loads from a CDN the first time, so an internet connection is needed to scan. Scans are accepted only for links belonging to this app.

---

##  Database Setup (SQL)

Open phpMyAdmin → SQL tab → paste and execute:

```sql
-- 1. Create Database
CREATE DATABASE IF NOT EXISTS library_db;
USE library_db;

-- 2. Books Table
CREATE TABLE IF NOT EXISTS books (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    isbn VARCHAR(20) NOT NULL,
    available_copies INT NOT NULL DEFAULT 0
);

-- 3. Physical Copies Table
CREATE TABLE IF NOT EXISTS book_copies (
    copy_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    INDEX idx_book_copies_book (book_id),
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE
);

-- 4. Loans Table
CREATE TABLE IF NOT EXISTS loans (
    loan_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    copy_id INT DEFAULT NULL,
    borrower_name VARCHAR(255) NOT NULL,
    borrow_date DATE NOT NULL,
    return_date DATE DEFAULT NULL,
    status ENUM('Borrowed', 'Returned') DEFAULT 'Borrowed',
    INDEX idx_loans_copy_status (copy_id, status),
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    FOREIGN KEY (copy_id) REFERENCES book_copies(copy_id) ON DELETE SET NULL
);

-- 5. Audit Logs Table
CREATE TABLE IF NOT EXISTS audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. TRIGGER: After INSERT on loans → Decrease available_copies
DELIMITER //
CREATE TRIGGER trg_after_borrow
AFTER INSERT ON loans
FOR EACH ROW
BEGIN
    UPDATE books SET available_copies = available_copies - 1 WHERE book_id = NEW.book_id;
    INSERT INTO audit_logs (action, details) 
    VALUES ('BOOK_BORROWED', CONCAT('Book ID: ', NEW.book_id, ' borrowed by ', NEW.borrower_name, ' on ', NEW.borrow_date));
END//
DELIMITER ;

-- 7. TRIGGER: After UPDATE on loans (return) → Increase available_copies
DELIMITER //
CREATE TRIGGER trg_after_return
AFTER UPDATE ON loans
FOR EACH ROW
BEGIN
    IF NEW.status = 'Returned' AND OLD.status = 'Borrowed' THEN
        UPDATE books SET available_copies = available_copies + 1 WHERE book_id = NEW.book_id;
        INSERT INTO audit_logs (action, details) 
        VALUES ('BOOK_RETURNED', CONCAT('Book ID: ', NEW.book_id, ' returned by ', NEW.borrower_name, ' on ', NEW.return_date));
    END IF;
END//
DELIMITER ;

-- 8. TRIGGER: Before DELETE on books → Archive to audit_logs
DELIMITER //
CREATE TRIGGER trg_before_delete_book
BEFORE DELETE ON books
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, details) 
    VALUES ('BOOK_DELETED', CONCAT('Deleted Book — ID: ', OLD.book_id, ', Title: "', OLD.title, '", Author: "', OLD.author, '", ISBN: ', OLD.isbn, ', Copies: ', OLD.available_copies));
END//
DELIMITER ;

-- 9. Sample Data
INSERT INTO books (title, author, isbn, available_copies) VALUES
('The Great Gatsby', 'F. Scott Fitzgerald', '978-0-7432-7356-5', 3),
('To Kill a Mockingbird', 'Harper Lee', '978-0-0611-2008-4', 5),
('1984', 'George Orwell', '978-0-4511-5245-8', 2),
('Pride and Prejudice', 'Jane Austen', '978-0-1413-9518-2', 4),
('The Catcher in the Rye', 'J.D. Salinger', '978-0-3160-7693-5', 1);

-- Create a separate physical copy record for every available copy in the sample catalog.
INSERT INTO book_copies (book_id)
SELECT b.book_id
FROM books b
JOIN (
    SELECT 1 AS copy_number UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
) copy_numbers ON copy_numbers.copy_number <= b.available_copies;
```

---
