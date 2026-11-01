<?php

require_once dirname(__DIR__) . '/db.php';
require_once __DIR__ . '/qr-network.php';

$qrPhoneOrigin = detectQrPhoneOrigin();

$totalBooks = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
$totalAvailable = $pdo->query("SELECT SUM(available_copies) FROM books")->fetchColumn() ?: 0;
$activeLoans = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'Borrowed'")->fetchColumn();
$auditCount = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();

$loans = $pdo->query("SELECT l.*, b.title, b.isbn FROM loans l JOIN books b ON l.book_id = b.book_id WHERE l.status = 'Borrowed' ORDER BY l.loan_id DESC")->fetchAll();
$books = $pdo->query("SELECT * FROM books ORDER BY book_id DESC")->fetchAll();
$copyRows = $pdo->query(
    "SELECT bc.copy_id, bc.book_id, l.borrower_name
    FROM book_copies bc
    LEFT JOIN loans l ON l.copy_id = bc.copy_id AND l.status = 'Borrowed'
    ORDER BY bc.book_id, bc.copy_id"
)->fetchAll();
$bookCopies = [];
foreach ($copyRows as $copy) {
    $bookCopies[(int)$copy['book_id']][] = [
        'id' => (int)$copy['copy_id'],
        'borrowerName' => $copy['borrower_name'],
    ];
}
$bookCount = count($books);
$logs = $pdo->query("SELECT * FROM audit_logs ORDER BY log_id DESC")->fetchAll();

$qrBorrowBook = null;
$qrBorrowNotice = null;
$qrReturnLoan = null;
$qrReturnNotice = null;
if (array_key_exists('copy', $_GET)) {
    $qrCopyId = filter_input(INPUT_GET, 'copy', FILTER_VALIDATE_INT);
    if ($qrCopyId === false || $qrCopyId === null || $qrCopyId < 1) {
        $qrBorrowNotice = 'The QR code does not contain a valid copy.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT bc.copy_id, b.book_id, b.title, b.author, b.isbn, b.available_copies, l.loan_id, l.borrower_name
            FROM book_copies bc
            JOIN books b ON b.book_id = bc.book_id
            LEFT JOIN loans l ON l.copy_id = bc.copy_id AND l.status = 'Borrowed'
            WHERE bc.copy_id = ?"
        );
        $stmt->execute([$qrCopyId]);
        $qrCopy = $stmt->fetch();
        if (!$qrCopy) {
            $qrBorrowNotice = 'That physical copy no longer exists.';
        } elseif ($qrCopy['loan_id'] !== null) {
            $qrReturnLoan = [
                'id' => (int)$qrCopy['loan_id'],
                'copyId' => (int)$qrCopy['copy_id'],
                'title' => $qrCopy['title'],
                'borrowerName' => $qrCopy['borrower_name'],
                'isbn' => $qrCopy['isbn'],
            ];
        } elseif ((int)$qrCopy['available_copies'] < 1) {
            $qrBorrowNotice = 'No copies of that book are currently available.';
        } else {
            $qrBorrowBook = [
                'id' => (int)$qrCopy['book_id'],
                'copyId' => (int)$qrCopy['copy_id'],
                'title' => $qrCopy['title'],
                'isbn' => $qrCopy['isbn'],
            ];
        }
    }
} elseif (array_key_exists('borrow_book', $_GET)) {
    $qrBorrowBookId = filter_input(INPUT_GET, 'borrow_book', FILTER_VALIDATE_INT);
    if ($qrBorrowBookId === false || $qrBorrowBookId === null || $qrBorrowBookId < 1) {
        $qrBorrowNotice = 'The QR code does not contain a valid book.';
    } else {
        $stmt = $pdo->prepare('SELECT book_id, title, author, isbn, available_copies FROM books WHERE book_id = ?');
        $stmt->execute([$qrBorrowBookId]);
        $qrBorrowBook = $stmt->fetch();
        if (!$qrBorrowBook) {
            $qrBorrowNotice = 'That book is no longer in the catalog.';
        } elseif ((int)$qrBorrowBook['available_copies'] < 1) {
            $qrBorrowNotice = 'No copies of that book are currently available.';
            $qrBorrowBook = null;
        }
    }
}
if (array_key_exists('return_loan', $_GET)) {
    $qrReturnLoanId = filter_input(INPUT_GET, 'return_loan', FILTER_VALIDATE_INT);
    if ($qrReturnLoanId === false || $qrReturnLoanId === null || $qrReturnLoanId < 1) {
        $qrReturnNotice = 'The QR code does not contain a valid loan.';
    } else {
        $stmt = $pdo->prepare("SELECT l.loan_id, l.borrower_name, b.title, b.isbn FROM loans l JOIN books b ON l.book_id = b.book_id WHERE l.loan_id = ? AND l.status = 'Borrowed'");
        $stmt->execute([$qrReturnLoanId]);
        $qrReturnLoan = $stmt->fetch();
        if (!$qrReturnLoan) {
            $qrReturnNotice = 'That loan is already returned or no longer exists.';
        }
    }
}

$msg = $_GET['msg'] ?? '';
$msgMap = [
    'borrowed' => ['Book borrowed successfully!', 'success'],
    'returned' => ['Book returned successfully!', 'success'],
    'deleted'  => ['Book deleted successfully!', 'warning'],
    'added'    => ['New book added successfully!', 'success'],
    'invalid'  => ['Please check the submitted information and try again.', 'error'],
    'not_active' => ['That loan is no longer active.', 'warning'],
    'not_found' => ['That book could not be found.', 'warning'],
    'out_of_stock' => ['No copies of that book are currently available.', 'warning'],
    'copy_unavailable' => ['That physical copy has already been borrowed.', 'warning'],
    'copy_added' => ['Physical copy added. Generate and print its QR code.', 'success'],
    'error'    => ['The database could not complete that action. Check the server log.', 'error'],
];

$pageConfig = [
    'phoneOrigin' => $qrPhoneOrigin,
    'bookCopies' => $bookCopies,
    'message' => isset($msgMap[$msg])
        ? ['text' => $msgMap[$msg][0], 'type' => $msgMap[$msg][1]]
        : null,
    'qrBorrowBook' => $qrBorrowBook
        ? [
            'id' => (int)($qrBorrowBook['book_id'] ?? $qrBorrowBook['id']),
            'copyId' => isset($qrBorrowBook['copyId'])
                ? (int)$qrBorrowBook['copyId']
                : (isset($qrBorrowBook['copy_id']) ? (int)$qrBorrowBook['copy_id'] : null),
            'title' => $qrBorrowBook['title'],
            'isbn' => $qrBorrowBook['isbn'],
        ]
        : null,
    'qrBorrowNotice' => $qrBorrowNotice,
    'qrReturnLoan' => $qrReturnLoan
        ? [
            'id' => (int)($qrReturnLoan['id'] ?? $qrReturnLoan['loan_id']),
            'copyId' => isset($qrReturnLoan['copyId']) ? (int)$qrReturnLoan['copyId'] : (isset($qrReturnLoan['copy_id']) ? (int)$qrReturnLoan['copy_id'] : null),
            'title' => $qrReturnLoan['title'],
            'borrowerName' => $qrReturnLoan['borrowerName'] ?? $qrReturnLoan['borrower_name'],
            'isbn' => $qrReturnLoan['isbn'],
        ]
        : null,
    'qrReturnNotice' => $qrReturnNotice,
];
