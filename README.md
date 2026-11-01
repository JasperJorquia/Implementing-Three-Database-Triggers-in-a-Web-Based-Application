## Library Management System — Database Triggers Demo

> A modern Library Management System built with PHP & MySQL showcasing **database triggers**.

## How to Run

### Prerequisites
- **XAMPP** installed with Apache and MySQL

### Steps
1. **Start XAMPP** — Enable both **Apache** and **MySQL** modules
2. **Create the Database** — Open phpMyAdmin (`http://localhost/phpmyadmin`) and run the SQL setup below
3. **Open the app** — Navigate to: `http://localhost/your projuect folder name/index.php`

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

-- 3. Loans Table
CREATE TABLE IF NOT EXISTS loans (
    loan_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    borrower_name VARCHAR(255) NOT NULL,
    borrow_date DATE NOT NULL,
    return_date DATE DEFAULT NULL,
    status ENUM('Borrowed', 'Returned') DEFAULT 'Borrowed',
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE
);

-- 4. Audit Logs Table
CREATE TABLE IF NOT EXISTS audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. TRIGGER: After INSERT on loans → Decrease available_copies
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

-- 6. TRIGGER: After UPDATE on loans (return) → Increase available_copies
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

-- 7. TRIGGER: Before DELETE on books → Archive to audit_logs
DELIMITER //
CREATE TRIGGER trg_before_delete_book
BEFORE DELETE ON books
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, details) 
    VALUES ('BOOK_DELETED', CONCAT('Deleted Book — ID: ', OLD.book_id, ', Title: "', OLD.title, '", Author: "', OLD.author, '", ISBN: ', OLD.isbn, ', Copies: ', OLD.available_copies));
END//
DELIMITER ;

-- 8. Sample Data
INSERT INTO books (title, author, isbn, available_copies) VALUES
('The Great Gatsby', 'F. Scott Fitzgerald', '978-0-7432-7356-5', 3),
('To Kill a Mockingbird', 'Harper Lee', '978-0-0611-2008-4', 5),
('1984', 'George Orwell', '978-0-4511-5245-8', 2),
('Pride and Prejudice', 'Jane Austen', '978-0-1413-9518-2', 4),
('The Catcher in the Rye', 'J.D. Salinger', '978-0-3160-7693-5', 1);
```

---

