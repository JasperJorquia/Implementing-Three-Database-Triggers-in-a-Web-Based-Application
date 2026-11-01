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
        $borrowerName = trim($_POST['borrower_name'] ?? '');
        if (!$bookId || $borrowerName === '') redirectWithMessage('invalid');
        
        $stmt = $pdo->prepare("INSERT INTO loans (book_id, borrower_name, borrow_date) VALUES (?, ?, ?)");
        $stmt->execute([$bookId, $borrowerName, date('Y-m-d')]);
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

        $stmt = $pdo->prepare("INSERT INTO books (title, author, isbn, available_copies) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $author, $isbn, $copies]);
        redirectWithMessage('added');
    }

    redirectWithMessage('invalid');
} catch (PDOException $e) {
    error_log('Library action failed: ' . $e->getMessage());
    redirectWithMessage('error');
}
?>