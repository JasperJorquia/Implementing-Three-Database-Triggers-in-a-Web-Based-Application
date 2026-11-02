<?php
require_once 'db.php';

$action = $_GET['action'] ?? '';

function redirectWithMessage(string $message): void {
    header('Location: index.php?msg=' . rawurlencode($message));
    exit();
}

try {
    if ($action === 'borrow' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $bookId = filter_input(INPUT_POST, 'book_id', FILTER_VALIDATE_INT);
        $copyIdInput = trim($_POST['copy_id'] ?? '');
        $copyId = $copyIdInput === '' ? null : filter_var($copyIdInput, FILTER_VALIDATE_INT);
        $borrowerName = trim($_POST['borrower_name'] ?? '');
        if (!$bookId || $borrowerName === '' || ($copyIdInput !== '' && ($copyId === false || $copyId < 1))) {
            redirectWithMessage('invalid');
        }

        $pdo->beginTransaction();
        $bookStmt = $pdo->prepare('SELECT available_copies FROM books WHERE book_id = ? FOR UPDATE');
        $bookStmt->execute([$bookId]);
        $availableCopies = $bookStmt->fetchColumn();
        if ($availableCopies === false) {
            $pdo->rollBack();
            redirectWithMessage('not_found');
        }
        if ((int)$availableCopies < 1) {
            $pdo->rollBack();
            redirectWithMessage('out_of_stock');
        }

        if ($copyId) {
            $copyStmt = $pdo->prepare('SELECT copy_id FROM book_copies WHERE copy_id = ? AND book_id = ? FOR UPDATE');
            $copyStmt->execute([$copyId, $bookId]);
            $selectedCopyId = $copyStmt->fetchColumn();
            if ($selectedCopyId === false) {
                $pdo->rollBack();
                redirectWithMessage('not_found');
            }
            $activeLoanStmt = $pdo->prepare("SELECT loan_id FROM loans WHERE copy_id = ? AND status = 'Borrowed' LIMIT 1");
            $activeLoanStmt->execute([$selectedCopyId]);
            if ($activeLoanStmt->fetchColumn() !== false) {
                $pdo->rollBack();
                redirectWithMessage('copy_unavailable');
            }
        } else {
            $copyStmt = $pdo->prepare(
                "SELECT bc.copy_id
                FROM book_copies bc
                WHERE bc.book_id = ?
                    AND NOT EXISTS (
                        SELECT 1 FROM loans l
                        WHERE l.copy_id = bc.copy_id AND l.status = 'Borrowed'
                    )
                ORDER BY bc.copy_id
                LIMIT 1
                FOR UPDATE"
            );
            $copyStmt->execute([$bookId]);
            $selectedCopyId = $copyStmt->fetchColumn();
            if ($selectedCopyId === false) {
                $pdo->rollBack();
                redirectWithMessage('out_of_stock');
            }
        }

        $stmt = $pdo->prepare("INSERT INTO loans (book_id, copy_id, borrower_name, borrow_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$bookId, $selectedCopyId, $borrowerName, date('Y-m-d')]);
        $pdo->commit();
        redirectWithMessage('borrowed');
    }

    if ($action === 'return' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $loanId = filter_input(INPUT_POST, 'loan_id', FILTER_VALIDATE_INT);
        if (!$loanId) redirectWithMessage('invalid');

        $stmt = $pdo->prepare("UPDATE loans SET status = 'Returned', return_date = ? WHERE loan_id = ? AND status = 'Borrowed'");
        $stmt->execute([date('Y-m-d'), $loanId]);
        redirectWithMessage($stmt->rowCount() ? 'returned' : 'not_active');
    }

    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $bookId = filter_input(INPUT_POST, 'book_id', FILTER_VALIDATE_INT);
        if (!$bookId) redirectWithMessage('invalid');

        $stmt = $pdo->prepare("DELETE FROM books WHERE book_id = ?");
        $stmt->execute([$bookId]);
        redirectWithMessage($stmt->rowCount() ? 'deleted' : 'not_found');
    }

    if ($action === 'add_book' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        $copies = filter_input(INPUT_POST, 'copies', FILTER_VALIDATE_INT);
        if ($title === '' || $author === '' || $isbn === '' || $copies === false || $copies === null || $copies < 0) {
            redirectWithMessage('invalid');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO books (title, author, isbn, available_copies) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $author, $isbn, $copies]);
        $bookId = (int)$pdo->lastInsertId();
        $copyStmt = $pdo->prepare('INSERT INTO book_copies (book_id) VALUES (?)');
        for ($copyNumber = 0; $copyNumber < $copies; $copyNumber++) {
            $copyStmt->execute([$bookId]);
        }
        $pdo->commit();
        redirectWithMessage('added');
    }

    if ($action === 'add_copy' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $bookId = filter_input(INPUT_POST, 'book_id', FILTER_VALIDATE_INT);
        if (!$bookId || $bookId < 1) redirectWithMessage('invalid');

        $pdo->beginTransaction();
        $bookStmt = $pdo->prepare('SELECT title FROM books WHERE book_id = ? FOR UPDATE');
        $bookStmt->execute([$bookId]);
        $bookTitle = $bookStmt->fetchColumn();
        if ($bookTitle === false) {
            $pdo->rollBack();
            redirectWithMessage('not_found');
        }

        $copyStmt = $pdo->prepare('INSERT INTO book_copies (book_id) VALUES (?)');
        $copyStmt->execute([$bookId]);
        $copyId = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE books SET available_copies = available_copies + 1 WHERE book_id = ?')->execute([$bookId]);
        $auditStmt = $pdo->prepare("INSERT INTO audit_logs (action, details) VALUES ('COPY_ADDED', ?)");
        $auditStmt->execute(["Copy #$copyId added to Book ID #$bookId: $bookTitle"]);
        $pdo->commit();
        redirectWithMessage('copy_added');
    }

    redirectWithMessage('invalid');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Library action failed: ' . $e->getMessage());
    redirectWithMessage('error');
}
?>