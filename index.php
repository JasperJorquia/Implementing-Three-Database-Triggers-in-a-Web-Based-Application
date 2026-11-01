<?php
require_once __DIR__ . '/includes/dashboard-data.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management System</title>
    <meta name="description" content="A modern Library Management System demonstrating MySQL database triggers for IM101.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css?v=6">
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h1>
                <span class="brand-icon">▤</span>
                Library Management System
            </h1>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-label">Menu</div>
            <a href="#" class="nav-item active" data-section="dashboard" onclick="scrollToTop(); return false;">
                <span class="nav-icon">▦</span> Dashboard
            </a>
            <a href="#books-section" class="nav-item" data-section="books" onclick="switchTab('books'); return false;">
                <span class="nav-icon">▤</span> Book catalog
                <span class="badge"><?= $totalBooks ?></span>
            </a>
            <a href="#loans-section" class="nav-item" data-section="loans" onclick="switchTab('loans'); return false;">
                <span class="nav-icon">⇄</span> Active loans
                <span class="badge" style="background:var(--accent-amber);"><?= $activeLoans ?></span>
            </a>
            <div class="nav-label">Audit</div>
            <a href="#audit-section" class="nav-item" data-section="audit" onclick="switchTab('audit'); return false;">
                <span class="nav-icon">☷</span> Audit logs
                <span class="badge" style="background:var(--accent-purple);"><?= $auditCount ?></span>
            </a>
            <a href="#" class="nav-item" onclick="openModal('addBookModal'); return false;">
                <span class="nav-icon">+</span> Add new book
            </a>
            <a href="#" class="nav-item" onclick="openQrScanner(); return false;">
                <span class="nav-icon">▣</span> Scan QR code
            </a>
        </nav>
    </aside>

    <main class="main-content">
    
        <header class="topbar">
            <div class="topbar-actions">
                <button class="btn btn-secondary btn-sm" onclick="location.reload();">
                    ↻ Refresh
                </button>
                <button class="btn btn-primary btn-sm" onclick="openModal('addBookModal');">
                    + Add book
                </button>
            </div>
        </header>

        <div class="content">
            <div class="stats-grid">
                <div class="stat-card animate-in delay-1">
                    <div class="stat-value"><?= $totalBooks ?></div>
                    <div class="stat-label">Titles in collection</div>
                </div>
                <div class="stat-card animate-in delay-2">
                    <div class="stat-value"><?= $totalAvailable ?></div>
                    <div class="stat-label">Ready to borrow</div>
                </div>
                <div class="stat-card animate-in delay-3">
                    <div class="stat-value"><?= $activeLoans ?></div>
                    <div class="stat-label">Current loans</div>
                </div>
                <div class="stat-card animate-in delay-4">
                    <div class="stat-value"><?= $auditCount ?></div>
                    <div class="stat-label">Audit records</div>
                </div>
            </div>

            <div class="tabs-container animate-in delay-5" id="tabs-container">
                <div class="tabs-header" role="tablist" aria-label="Library records">
                    <button class="tab-btn active" id="tab-books" role="tab" aria-selected="true" aria-controls="panel-books" data-tab="books" onclick="switchTab('books');">
                        ▤ All books <span class="tab-count"><?= count($books) ?></span>
                    </button>
                    <button class="tab-btn" id="tab-loans" role="tab" aria-selected="false" aria-controls="panel-loans" data-tab="loans" onclick="switchTab('loans');">
                        ⇄ Checked out <span class="tab-count"><?= count($loans) ?></span>
                    </button>
                    <button class="tab-btn" id="tab-audit" role="tab" aria-selected="false" aria-controls="panel-audit" data-tab="audit" onclick="switchTab('audit');">
                        ☷ Audit trail <span class="tab-count"><?= count($logs) ?></span>
                    </button>
                </div>

                <div class="tab-panel active" id="panel-books" role="tabpanel" aria-labelledby="tab-books">
                    <div class="catalog-toolbar">
                        <label class="search-field" for="bookSearch"><span aria-hidden="true">⌕</span><input id="bookSearch" type="search" placeholder="Search by title, author, ISBN or book ID..."></label>
                        <select id="bookStatusFilter" aria-label="Filter by availability">
                            <option value="all">All statuses</option>
                            <option value="available">Available</option>
                            <option value="unavailable">Unavailable</option>
                        </select>
                        <button type="button" class="btn btn-secondary btn-sm" id="clearBookFilters" disabled>Clear filters</button>
                        <span class="sort-label" id="bookResultCount" aria-live="polite"><?= count($books) ?> books · Sorted by book ID ↑</span>
                    </div>
                    <?php if (count($books) > 0): ?>
                    <div style="overflow-x:auto;">
                        <table class="data-table" id="books-table">
                            <thead>
                                <tr>
                                    <th>Book ID</th>
                                    <th>Book / author</th>
                                    <th>ISBN</th>
                                    <th>Available copies</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($books as $book): ?>
                                <tr data-isbn="<?= htmlspecialchars($book['isbn'], ENT_QUOTES, 'UTF-8') ?>" data-search="<?= htmlspecialchars(strtolower($book['book_id'].' '.$book['title'].' '.$book['author'].' '.$book['isbn']), ENT_QUOTES) ?>" data-status="<?= $book['available_copies'] > 0 ? 'available' : 'unavailable' ?>">
                                    <td><span class="mobile-cell-label">Book ID</span>#<?= $book['book_id'] ?></td>
                                    <td class="book-author-cell"><span class="mobile-cell-label">Book / author</span><span class="book-title"><?= htmlspecialchars($book['title']) ?></span><span class="book-author"><?= htmlspecialchars($book['author']) ?></span></td>
                                    <td><span class="mobile-cell-label">ISBN</span><code style="background:var(--bg-primary);padding:3px 8px;border-radius:4px;font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($book['isbn']) ?></code></td>
                                    <td>
                                        <span class="mobile-cell-label">Available copies</span>
                                        <strong style="color:var(--text-primary);"><?= $book['available_copies'] ?></strong>
                                    </td>
                                    <td>
                                        <span class="mobile-cell-label">Status</span>
                                        <?php if ($book['available_copies'] > 0): ?>
                                            <span class="badge-status badge-available"><span class="badge-dot"></span> Available</span>
                                        <?php else: ?>
                                            <span class="badge-status badge-none"><span class="badge-dot"></span> Unavailable</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="mobile-cell-label">Actions</span>
                                        <div class="actions-cell">
                                            <?php if ($book['available_copies'] > 0): ?>
                                            <button type="button" class="btn btn-success btn-xs" data-book-id="<?= (int)$book['book_id'] ?>" data-book-title="<?= htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8') ?>" data-book-isbn="<?= htmlspecialchars($book['isbn'], ENT_QUOTES, 'UTF-8') ?>" onclick="openBorrowModal(this.dataset.bookId, this.dataset.bookTitle, this.dataset.bookIsbn);">
                                                 Borrow
                                            </button>
                                            <?php else: ?>
                                            <button class="btn btn-xs" style="opacity:0.4;cursor:not-allowed;background:var(--bg-primary);color:var(--text-muted);border:1px solid var(--border-color);" disabled>
                                                 Borrow
                                            </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-danger btn-xs" data-book-id="<?= (int)$book['book_id'] ?>" data-book-title="<?= htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8') ?>" onclick="confirmDelete(this.dataset.bookId, this.dataset.bookTitle);">
                                                 Delete
                                            </button>
                                            <button type="button" class="btn btn-secondary btn-xs" data-book-id="<?= (int)$book['book_id'] ?>" data-book-title="<?= htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8') ?>" data-book-isbn="<?= htmlspecialchars($book['isbn'], ENT_QUOTES, 'UTF-8') ?>" onclick="manageBookCopies(this);">
                                                Copies / QR
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="empty-state filter-empty" id="bookNoResults" role="status" hidden>
                        <h3>No matching books</h3>
                        <p>Try a different search or availability filter.</p>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <h3>No Books Yet</h3>
                        <p>Add your first book to get started with the library system.</p>
                        <br>
                        <button class="btn btn-primary btn-sm" onclick="openModal('addBookModal');">Add Your First Book</button>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="tab-panel" id="panel-loans" role="tabpanel" aria-labelledby="tab-loans">
                    <?php if (count($loans) > 0): ?>
                    <div style="overflow-x:auto;">
                        <table class="data-table" id="loans-table">
                            <thead>
                                <tr>
                                    <th>Loan ID</th>
                                    <th>Book Title</th>
                                    <th>Borrower</th>
                                    <th>Borrow Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($loans as $loan): ?>
                                <tr>
                                    <td><span class="mobile-cell-label">Loan ID</span>#<?= $loan['loan_id'] ?><br><span class="book-author">Copy #<?= (int)$loan['copy_id'] ?></span></td>
                                    <td class="title-cell"><span class="mobile-cell-label">Book title</span><?= htmlspecialchars($loan['title']) ?></td>
                                    <td><span class="mobile-cell-label">Borrower</span><?= htmlspecialchars($loan['borrower_name']) ?></td>
                                    <td><span class="mobile-cell-label">Borrow date</span><?= $loan['borrow_date'] ?></td>
                                    <td>
                                        <span class="mobile-cell-label">Status</span>
                                        <span class="badge-status badge-borrowed"><span class="badge-dot"></span> Borrowed</span>
                                    </td>
                                    <td>
                                        <span class="mobile-cell-label">Actions</span>
                                        <div class="actions-cell">
                                            <form action="action.php?action=return" method="POST" onsubmit="return confirm('Return this book?');" class="inline-action-form">
                                                <input type="hidden" name="loan_id" value="<?= (int)$loan['loan_id'] ?>">
                                                <button type="submit" class="btn btn-warning btn-xs">↩ Return book</button>
                                            </form>
                                            <button type="button" class="btn btn-secondary btn-xs" data-copy-id="<?= (int)$loan['copy_id'] ?>" data-book-title="<?= htmlspecialchars($loan['title'], ENT_QUOTES, 'UTF-8') ?>" data-book-isbn="<?= htmlspecialchars($loan['isbn'], ENT_QUOTES, 'UTF-8') ?>" data-copy-status="Borrowed" onclick="showCopyQr(this);">Copy QR</button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"> </div>
                        <h3>No Active Loans</h3>
                        <p>All books have been returned. Borrow a book from the Books tab to see loans here.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="tab-panel" id="panel-audit" role="tabpanel" aria-labelledby="tab-audit">
                    <?php if (count($logs) > 0): ?>
                    <div style="overflow-x:auto;">
                        <table class="data-table" id="audit-table">
                            <thead>
                                <tr>
                                    <th>Log ID</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><span class="mobile-cell-label">Log ID</span>#<?= $log['log_id'] ?></td>
                                    <td>
                                        <span class="mobile-cell-label">Action</span>
                                        <span class="badge-status" style="background:rgba(168,85,247,0.15);color:var(--accent-purple);">
                                            <?= htmlspecialchars($log['action'] ?? $log['action_type'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td style="max-width:400px;word-wrap:break-word;"><span class="mobile-cell-label">Details</span><?= htmlspecialchars($log['details'] ?? $log['description'] ?? 'N/A') ?></td>
                                    <td style="white-space:nowrap;"><span class="mobile-cell-label">Timestamp</span><?= $log['timestamp'] ?? $log['created_at'] ?? 'N/A' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <h3>No Audit Logs</h3>
                        <p>Audit logs will appear here automatically when triggered actions occur (e.g., deleting a book).</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <div class="modal-overlay" id="addBookModal" aria-hidden="true">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="addBookTitle">
            <div class="modal-header">
                <h3 id="addBookTitle"> Add New Book</h3>
                <button type="button" class="modal-close" aria-label="Close add book dialog" onclick="closeModal('addBookModal');">✕</button>
            </div>
            <form action="action.php?action=add_book" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="add-title">Book Title</label>
                        <input type="text" id="add-title" name="title" class="form-control" placeholder="Enter book title" required>
                    </div>
                    <div class="form-group">
                        <label for="add-author">Author</label>
                        <input type="text" id="add-author" name="author" class="form-control" placeholder="Enter author name" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add-isbn">ISBN</label>
                            <input type="text" id="add-isbn" name="isbn" class="form-control" placeholder="e.g. 978-0-123456-47-2" required>
                        </div>
                        <div class="form-group">
                            <label for="add-copies">Available Copies</label>
                            <input type="number" id="add-copies" name="copies" class="form-control" placeholder="e.g. 5" min="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('addBookModal');">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"> Add book</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="borrowModal" aria-hidden="true">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="borrowBookTitle">
            <div class="modal-header">
                <h3 id="borrowBookTitle"> Borrow Book</h3>
                <button type="button" class="modal-close" aria-label="Close borrow dialog" onclick="closeModal('borrowModal');">✕</button>
            </div>
            <form action="action.php?action=borrow" method="POST">
                <input type="hidden" name="book_id" id="borrow-book-id">
                <input type="hidden" name="copy_id" id="borrow-copy-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Selected Book</label>
                        <div id="borrow-book-title" style="padding:11px 16px;background:var(--bg-input);border:1px solid var(--border-color);border-radius:var(--radius-sm);color:var(--accent-blue);font-weight:600;font-size:0.9rem;"></div>
                    </div>
                    <p class="qr-book-details" id="borrow-book-isbn"></p>
                    <div class="form-group">
                        <label for="borrow-name">Borrower's Full Name</label>
                        <input type="text" id="borrow-name" name="borrower_name" class="form-control" placeholder="Enter borrower's full name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('borrowModal');">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm"> Confirm Borrow</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="bookQrModal" aria-hidden="true">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="bookQrTitle">
            <div class="modal-header">
                <h3 id="bookQrTitle">Reusable physical copy QR code</h3>
                <button type="button" class="modal-close" aria-label="Close QR code dialog" onclick="closeModal('bookQrModal');">✕</button>
            </div>
            <div class="modal-body">
                <h2 class="qr-print-title" id="bookQrPrintTitle"></h2>
                <p class="qr-book-details" id="bookQrDetails"></p>
                <p class="qr-help" id="bookQrReuseHelp">This QR identifies one physical copy. Keep the label attached to the book; scanning it borrows the copy when available or starts its return when checked out.</p>
                <p class="qr-help">QR links always use http://localhost. They open this app on the laptop; a phone's localhost refers to the phone itself.</p>
                <div class="qr-preview" id="bookQrCode" aria-label="Generated book QR code"></div>
                <p class="qr-book-details" id="bookQrTarget"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('bookQrModal');">Close</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="copyBookQrLink();">Copy link</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="printBookQr();">Print QR</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="bookCopiesModal" aria-hidden="true">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="bookCopiesTitle">
            <div class="modal-header">
                <h3 id="bookCopiesTitle">Physical copies and QR codes</h3>
                <button type="button" class="modal-close" aria-label="Close physical copies dialog" onclick="closeModal('bookCopiesModal');">✕</button>
            </div>
            <div class="modal-body">
                <p class="qr-book-details" id="bookCopiesBookTitle"></p>
                <p class="qr-help">Print one QR per physical copy and attach it to that copy. The same label works for borrowing and returning.</p>
                <div class="book-copies-list" id="bookCopiesList"></div>
                <form action="action.php?action=add_copy" method="POST" class="add-copy-form">
                    <input type="hidden" name="book_id" id="add-copy-book-id">
                    <button type="submit" class="btn btn-secondary btn-sm">+ Add physical copy</button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('bookCopiesModal');">Close</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="qrScannerModal" aria-hidden="true">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="qrScannerTitle">
            <div class="modal-header">
                <h3 id="qrScannerTitle">Scan a book or loan QR code</h3>
                <button type="button" class="modal-close" aria-label="Close QR scanner" onclick="closeQrScanner();">✕</button>
            </div>
            <div class="modal-body">
                <p class="qr-help">Scan a copy QR to borrow it when available or start its return when checked out. A return still asks you to confirm.</p>
                <div id="qrScannerReader" class="qr-scanner-reader"></div>
                <p id="qrScannerStatus" class="qr-scanner-status" role="status" aria-live="polite">Choose a camera or scan a QR image.</p>
                <div class="qr-scanner-actions">
                    <button type="button" class="btn btn-primary btn-sm" id="startQrCamera" onclick="startQrCamera();">Use camera</button>
                    <label class="btn btn-secondary btn-sm qr-image-label" for="qrImageInput">Scan QR image
                        <input type="file" id="qrImageInput" accept="image/*" capture="environment">
                    </label>
                </div>
                <p class="qr-help">Camera scanning needs browser permission and a secure connection (localhost or HTTPS). On a phone using local Wi-Fi HTTP, use “Scan QR image” to open the camera.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeQrScanner();">Close</button>
            </div>
        </div>
    </div>

    <div class="confirm-overlay" id="qrReturnConfirmOverlay" aria-hidden="true">
        <div class="confirm-box" role="dialog" aria-modal="true" aria-labelledby="qrReturnConfirmTitle" tabindex="-1">
            <h3 id="qrReturnConfirmTitle">Confirm book return</h3>
            <p id="qrReturnConfirmDetails"></p>
            <div class="confirm-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeQrReturnConfirm();">Cancel</button>
                <form action="action.php?action=return" method="POST" class="inline-action-form">
                    <input type="hidden" name="loan_id" id="qrReturnLoanId">
                    <button type="submit" class="btn btn-warning btn-sm">Confirm return</button>
                </form>
            </div>
        </div>
    </div>

    <div class="confirm-overlay" id="confirmDeleteOverlay" aria-hidden="true">
        <div class="confirm-box" role="dialog" aria-modal="true" aria-labelledby="confirmDeleteTitle" tabindex="-1">
            <h3 id="confirmDeleteTitle">Delete Book?</h3>
            <p id="confirm-delete-text">Are you sure you want to delete this book? A BEFORE DELETE trigger will archive it to audit logs.</p>
            <div class="confirm-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeConfirm();">Cancel</button>
                <form action="action.php?action=delete" method="POST" id="confirm-delete-form" class="inline-action-form">
                    <input type="hidden" name="book_id" id="confirm-delete-book-id">
                    <button type="submit" class="btn btn-danger btn-sm">Delete book</button>
                </form>
            </div>
        </div>
    </div>

    <button class="mobile-toggle" id="mobileToggle" type="button" aria-label="Open navigation menu" aria-controls="sidebar" aria-expanded="false" onclick="toggleSidebar();">☰</button>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        window.libraryAppConfig = <?= json_encode($pageConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    </script>
    <script src="assets/js/app.js?v=5" defer></script>
</body>
</html>