CREATE DATABASE IF NOT EXISTS library_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE library_db;

CREATE TABLE books (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    isbn VARCHAR(20) NOT NULL,
    available_copies INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE book_copies (
    copy_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    UNIQUE KEY uq_book_copies_copy_book (copy_id, book_id),
    KEY idx_book_copies_book (book_id),
    CONSTRAINT fk_book_copies_book
        FOREIGN KEY (book_id) REFERENCES books(book_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE loans (
    loan_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    copy_id INT NOT NULL,
    borrower_name VARCHAR(255) NOT NULL,
    borrow_date DATE NOT NULL,
    return_date DATE DEFAULT NULL,
    status ENUM('Borrowed', 'Returned') NOT NULL DEFAULT 'Borrowed',
    KEY idx_loans_book_status (book_id, status),
    KEY idx_loans_copy_status (copy_id, status),
    CONSTRAINT fk_loans_book
        FOREIGN KEY (book_id) REFERENCES books(book_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_loans_copy_book
        FOREIGN KEY (copy_id, book_id)
        REFERENCES book_copies(copy_id, book_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

DELIMITER //

CREATE TRIGGER trg_after_borrow
AFTER INSERT ON loans
FOR EACH ROW
BEGIN
    UPDATE books
    SET available_copies = available_copies - 1
    WHERE book_id = NEW.book_id;

    INSERT INTO audit_logs (action, details)
    VALUES (
        'BOOK_BORROWED',
        CONCAT(
            'Book ID: ', NEW.book_id,
            ', Copy ID: ', NEW.copy_id,
            ', borrowed by ', NEW.borrower_name,
            ' on ', NEW.borrow_date
        )
    );
END//

CREATE TRIGGER trg_after_return
AFTER UPDATE ON loans
FOR EACH ROW
BEGIN
    IF NEW.status = 'Returned' AND OLD.status = 'Borrowed' THEN
        UPDATE books
        SET available_copies = available_copies + 1
        WHERE book_id = NEW.book_id;

        INSERT INTO audit_logs (action, details)
        VALUES (
            'BOOK_RETURNED',
            CONCAT(
                'Book ID: ', NEW.book_id,
                ', Copy ID: ', NEW.copy_id,
                ', returned by ', NEW.borrower_name,
                ' on ', NEW.return_date
            )
        );
    END IF;
END//

CREATE TRIGGER trg_before_delete_book
BEFORE DELETE ON books
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (action, details)
    VALUES (
        'BOOK_DELETED',
        CONCAT(
            'Deleted Book — ID: ', OLD.book_id,
            ', Title: "', OLD.title,
            '", Author: "', OLD.author,
            '", ISBN: ', OLD.isbn,
            ', Available Copies: ', OLD.available_copies
        )
    );
END//

DELIMITER ;

INSERT INTO books (title, author, isbn, available_copies) VALUES
    ('The Great Gatsby', 'F. Scott Fitzgerald', '978-0-7432-7356-5', 3),
    ('To Kill a Mockingbird', 'Harper Lee', '978-0-0611-2008-4', 5),
    ('1984', 'George Orwell', '978-0-4511-5245-8', 2),
    ('Pride and Prejudice', 'Jane Austen', '978-0-1413-9518-2', 4),
    ('The Catcher in the Rye', 'J.D. Salinger', '978-0-3160-7693-5', 1);

INSERT INTO book_copies (book_id)
SELECT b.book_id
FROM books b
JOIN (
    SELECT 1 AS copy_number
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
) AS copy_numbers
    ON copy_numbers.copy_number <= b.available_copies;
