USE library_db;

CREATE TABLE book_copies (
    copy_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    INDEX idx_book_copies_book (book_id),
    CONSTRAINT fk_book_copies_book FOREIGN KEY (book_id)
        REFERENCES books(book_id) ON DELETE CASCADE
);

ALTER TABLE loans
    ADD COLUMN copy_id INT NULL AFTER book_id,
    ADD INDEX idx_loans_copy_status (copy_id, status);

DROP PROCEDURE IF EXISTS migrate_existing_book_copies;
DELIMITER //
CREATE PROCEDURE migrate_existing_book_copies()
BEGIN
    DECLARE finished BOOLEAN DEFAULT FALSE;
    DECLARE current_book_id INT;
    DECLARE copies_to_create INT;
    DECLARE copy_number INT;
    DECLARE current_loan_id INT;
    DECLARE current_loan_book_id INT;
    DECLARE current_copy_id INT;

    DECLARE book_cursor CURSOR FOR
        SELECT b.book_id,
            GREATEST(b.available_copies, 0) + (
                SELECT COUNT(*)
                FROM loans l
                WHERE l.book_id = b.book_id AND l.status = 'Borrowed'
            )
        FROM books b
        ORDER BY b.book_id;
    DECLARE loan_cursor CURSOR FOR
        SELECT loan_id, book_id
        FROM loans
        WHERE status = 'Borrowed'
        ORDER BY book_id, loan_id;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET finished = TRUE;

    OPEN book_cursor;
    create_book_copies: LOOP
        FETCH book_cursor INTO current_book_id, copies_to_create;
        IF finished THEN
            LEAVE create_book_copies;
        END IF;

        SET copy_number = 0;
        WHILE copy_number < copies_to_create DO
            INSERT INTO book_copies (book_id) VALUES (current_book_id);
            SET copy_number = copy_number + 1;
        END WHILE;
    END LOOP;
    CLOSE book_cursor;

    SET finished = FALSE;
    OPEN loan_cursor;
    assign_borrowed_copies: LOOP
        FETCH loan_cursor INTO current_loan_id, current_loan_book_id;
        IF finished THEN
            LEAVE assign_borrowed_copies;
        END IF;

        SET current_copy_id = (
            SELECT bc.copy_id
            FROM book_copies bc
            WHERE bc.book_id = current_loan_book_id
                AND NOT EXISTS (
                    SELECT 1 FROM loans l WHERE l.copy_id = bc.copy_id
                )
            ORDER BY bc.copy_id
            LIMIT 1
        );
        IF current_copy_id IS NULL THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Could not assign a physical copy to every active loan.';
        END IF;
        UPDATE loans SET copy_id = current_copy_id WHERE loan_id = current_loan_id;
    END LOOP;
    CLOSE loan_cursor;
END//
DELIMITER ;

CALL migrate_existing_book_copies();
DROP PROCEDURE migrate_existing_book_copies;

ALTER TABLE loans
    ADD CONSTRAINT fk_loans_copy FOREIGN KEY (copy_id)
        REFERENCES book_copies(copy_id) ON DELETE SET NULL;
