        const modalReturnFocus = new Map();
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let currentQrTarget = null;
        let qrLibraryPromise = null;
        let qrScannerLibraryPromise = null;
        let qrScanner = null;
        let qrScanHandled = false;

        function switchTab(tabName) {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                const isActive = btn.dataset.tab === tabName;
                btn.classList.toggle('active', isActive);
                btn.setAttribute('aria-selected', String(isActive));
            });
            document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
            document.getElementById(`panel-${tabName}`).classList.add('active');

            document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
            const navItem = document.querySelector(`.nav-item[data-section="${tabName}"]`);
            if (navItem) navItem.classList.add('active');
            document.getElementById('tabs-container').scrollIntoView({
                behavior: prefersReducedMotion ? 'auto' : 'smooth',
                block: 'start'
            });
            closeSidebar();
        }

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: prefersReducedMotion ? 'auto' : 'smooth' });
            document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
            document.querySelector('.nav-item[data-section="dashboard"]').classList.add('active');
            closeSidebar();
        }

        function openModal(id) {
            const modal = document.getElementById(id);
            modalReturnFocus.set(id, document.activeElement);
            modal.setAttribute('aria-hidden', 'false');
            modal.style.display = 'flex';
            requestAnimationFrame(() => {
                if (modal.getAttribute('aria-hidden') === 'true') return;
                modal.classList.add('show');
                modal.querySelector('.modal-body input:not([type="hidden"]):not([type="file"])')?.focus();
            });
        }
        function closeModal(id) {
            const modal = document.getElementById(id);
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
            setTimeout(() => {
                if (modal.classList.contains('show')) return;
                modal.style.display = 'none';
                modalReturnFocus.get(id)?.focus();
                modalReturnFocus.delete(id);
            }, 300);
        }
        function openBorrowModal(bookId, bookTitle, isbn = '', copyId = '') {
            document.getElementById('borrow-book-id').value = bookId;
            document.getElementById('borrow-copy-id').value = copyId;
            document.getElementById('borrow-book-title').textContent = bookTitle;
            document.getElementById('borrow-book-isbn').textContent = isbn ? `ISBN: ${isbn}` : '';
            document.getElementById('borrow-name').value = '';
            openModal('borrowModal');
        }

        function loadQrLibrary() {
            if (window.QRCode) return Promise.resolve();
            if (qrLibraryPromise) return qrLibraryPromise;

            qrLibraryPromise = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
                script.onload = () => window.QRCode
                    ? resolve()
                    : reject(new Error('The QR code library did not initialize.'));
                script.onerror = () => reject(new Error('Could not load the QR code library.'));
                document.head.appendChild(script);
            }).catch(error => {
                qrLibraryPromise = null;
                throw error;
            });
            return qrLibraryPromise;
        }

        function buildQrUrl() {
            const baseUrl = new URL(window.location.pathname, 'http://localhost');
            baseUrl.searchParams.set(currentQrTarget.parameter, currentQrTarget.id);
            return baseUrl.toString();
        }

        function refreshQrCode() {
            if (!currentQrTarget || !window.QRCode) return;
            const container = document.getElementById('bookQrCode');
            container.replaceChildren();
            try {
                const targetUrl = buildQrUrl();
                new QRCode(container, {
                    text: targetUrl,
                    width: 240,
                    height: 240,
                    colorDark: '#101827',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
                document.getElementById('bookQrTarget').textContent = targetUrl;
            } catch (error) {
                document.getElementById('bookQrTarget').textContent = '';
                showToast(error.message || 'Enter a valid app address to update the QR code.', 'error');
            }
        }

        async function showQrCode(target) {
            currentQrTarget = target;
            document.getElementById('bookQrTitle').textContent = `${target.label}: ${target.title}`;
            document.getElementById('bookQrPrintTitle').textContent = target.title;
            document.getElementById('bookQrDetails').textContent = target.details;
            openModal('bookQrModal');

            try {
                await loadQrLibrary();
                if (!currentQrTarget || document.getElementById('bookQrModal').getAttribute('aria-hidden') === 'true') return;
                refreshQrCode();
            } catch (error) {
                showToast(error.message || 'Could not generate the QR code.', 'error');
                closeModal('bookQrModal');
            }
        }

        function showCopyQr(button) {
            showQrCode({
                parameter: 'copy',
                id: button.dataset.copyId,
                title: button.dataset.bookTitle,
                details: `Copy #${button.dataset.copyId} · ${button.dataset.copyStatus || 'Reusable for borrowing and returning'} · ISBN: ${button.dataset.bookIsbn}`,
                label: 'Reusable physical copy QR code'
            });
        }

        function manageBookCopies(button) {
            const bookId = button.dataset.bookId;
            const bookTitle = button.dataset.bookTitle;
            const isbn = button.dataset.bookIsbn;
            const copies = window.libraryAppConfig.bookCopies[bookId] || [];
            const list = document.getElementById('bookCopiesList');
            list.replaceChildren();
            document.getElementById('bookCopiesBookTitle').textContent = `${bookTitle} · ISBN ${isbn}`;
            document.getElementById('add-copy-book-id').value = bookId;

            if (copies.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'qr-help';
                empty.textContent = 'No physical copies yet. Add a copy to create its reusable QR code.';
                list.appendChild(empty);
            }

            copies.forEach(copy => {
                const row = document.createElement('div');
                row.className = 'book-copy-row';
                const description = document.createElement('div');
                description.className = 'book-copy-description';
                const name = document.createElement('strong');
                name.textContent = `Copy #${copy.id}`;
                const status = document.createElement('span');
                status.textContent = copy.borrowerName
                    ? `Borrowed by ${copy.borrowerName}`
                    : 'Available';
                description.append(name, status);

                const qrButton = document.createElement('button');
                qrButton.type = 'button';
                qrButton.className = 'btn btn-secondary btn-xs';
                qrButton.textContent = 'View / print QR';
                qrButton.dataset.copyId = copy.id;
                qrButton.dataset.bookTitle = bookTitle;
                qrButton.dataset.bookIsbn = isbn;
                qrButton.dataset.copyStatus = copy.borrowerName ? 'Borrowed' : 'Available';
                qrButton.addEventListener('click', () => showCopyQr(qrButton));

                row.append(description, qrButton);
                list.appendChild(row);
            });

            openModal('bookCopiesModal');
        }

        async function copyBookQrLink() {
            try {
                const targetUrl = buildQrUrl();
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(targetUrl);
                } else {
                    const input = document.createElement('textarea');
                    input.value = targetUrl;
                    input.style.position = 'fixed';
                    input.style.opacity = '0';
                    document.body.appendChild(input);
                    input.select();
                    const copied = document.execCommand('copy');
                    input.remove();
                    if (!copied) throw new Error('Copy is unavailable. Select and copy the link shown below the QR code.');
                }
                showToast('Local QR link copied.', 'success');
            } catch (error) {
                showToast(error.message || 'Could not copy the local QR link.', 'error');
            }
        }

        function printBookQr() {
            if (!currentQrTarget || !document.querySelector('#bookQrCode canvas, #bookQrCode img')) {
                showToast('Generate the QR code before printing.', 'warning');
                return;
            }
            document.body.classList.add('print-qr-active');
            window.addEventListener('afterprint', () => document.body.classList.remove('print-qr-active'), { once: true });
            window.print();
            document.body.classList.remove('print-qr-active');
        }

        function loadQrScannerLibrary() {
            if (window.Html5Qrcode) return Promise.resolve();
            if (qrScannerLibraryPromise) return qrScannerLibraryPromise;

            qrScannerLibraryPromise = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
                script.onload = () => window.Html5Qrcode
                    ? resolve()
                    : reject(new Error('The QR scanner library did not initialize.'));
                script.onerror = () => reject(new Error('Could not load the QR scanner library.'));
                document.head.appendChild(script);
            }).catch(error => {
                qrScannerLibraryPromise = null;
                throw error;
            });
            return qrScannerLibraryPromise;
        }

        function setQrScannerStatus(message, type = '') {
            const status = document.getElementById('qrScannerStatus');
            status.textContent = message;
            status.dataset.state = type;
        }

        function openQrScanner() {
            qrScanHandled = false;
            document.getElementById('qrImageInput').value = '';
            setQrScannerStatus('Choose a camera or scan a QR image.');
            const cameraButton = document.getElementById('startQrCamera');
            const cameraAvailable = window.isSecureContext && navigator.mediaDevices?.getUserMedia;
            cameraButton.disabled = !cameraAvailable;
            cameraButton.title = cameraAvailable ? '' : 'Camera access requires HTTPS or localhost. On a phone using Wi-Fi HTTP, use Scan QR image.';
            openModal('qrScannerModal');
        }

        async function closeQrScanner() {
            await stopQrCamera();
            closeModal('qrScannerModal');
        }

        async function stopQrCamera() {
            if (!qrScanner) return;
            const scanner = qrScanner;
            qrScanner = null;
            try {
                if (scanner.isScanning) await scanner.stop();
                scanner.clear();
            } catch (error) {
                setQrScannerStatus(error.message || 'Could not stop the camera cleanly.', 'error');
            }
        }

        async function startQrCamera() {
            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                setQrScannerStatus('Camera access needs HTTPS or localhost. Use Scan QR image on this network.', 'error');
                return;
            }

            const cameraButton = document.getElementById('startQrCamera');
            cameraButton.disabled = true;
            setQrScannerStatus('Starting camera…');
            try {
                await loadQrScannerLibrary();
                if (document.getElementById('qrScannerModal').getAttribute('aria-hidden') === 'true') return;
                qrScanner = new Html5Qrcode('qrScannerReader');
                await qrScanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 240, height: 240 } },
                    decodedText => handleScannedQr(decodedText),
                    () => {}
                );
                setQrScannerStatus('Camera ready. Point it at a book or loan QR code.');
            } catch (error) {
                await stopQrCamera();
                setQrScannerStatus(error.message || 'Could not start the camera. Check camera permission or scan an image.', 'error');
            } finally {
                cameraButton.disabled = false;
            }
        }

        async function scanQrImage(file) {
            if (!file) return;
            setQrScannerStatus('Reading QR image…');
            let imageScanner;
            try {
                await loadQrScannerLibrary();
                imageScanner = new Html5Qrcode('qrScannerReader');
                const decodedText = await imageScanner.scanFile(file, true);
                await handleScannedQr(decodedText);
            } catch (error) {
                setQrScannerStatus(error.message || 'Could not read a QR code from that image. Try another image.', 'error');
            } finally {
                if (imageScanner) {
                    try {
                        imageScanner.clear();
                    } catch (error) {
                        setQrScannerStatus(error.message || 'Could not reset the QR image scanner.', 'error');
                    }
                }
            }
        }

        async function handleScannedQr(decodedText) {
            if (qrScanHandled) return;

            let scannedUrl;
            try {
                scannedUrl = new URL(decodedText);
            } catch {
                setQrScannerStatus('This QR code is not a valid library link.', 'error');
                return;
            }

            const allowedOrigins = [window.location.origin, 'http://localhost', 'https://localhost'];
            const appPath = new URL(window.location.pathname, window.location.origin).pathname;
            const isCopyCode = scannedUrl.searchParams.has('copy') && scannedUrl.searchParams.size === 1;
            const isBorrowCode = scannedUrl.searchParams.has('borrow_book') && scannedUrl.searchParams.size === 1;
            const isReturnCode = scannedUrl.searchParams.has('return_loan') && scannedUrl.searchParams.size === 1;
            const parameter = isCopyCode ? 'copy' : isBorrowCode ? 'borrow_book' : 'return_loan';
            const itemId = Number(scannedUrl.searchParams.get(parameter));
            if (
                !allowedOrigins.includes(scannedUrl.origin)
                || scannedUrl.pathname !== appPath
                || (!isCopyCode && !isBorrowCode && !isReturnCode)
                || !Number.isSafeInteger(itemId)
                || itemId < 1
                || scannedUrl.username
                || scannedUrl.password
            ) {
                setQrScannerStatus('This QR code is not a valid copy, book, or loan code for this app.', 'error');
                return;
            }

            qrScanHandled = true;
            setQrScannerStatus(isCopyCode ? 'Copy found. Checking its current status…' : isBorrowCode ? 'Book found. Opening the borrow form…' : 'Loan found. Opening return confirmation…', 'success');
            await stopQrCamera();
            window.location.assign(scannedUrl.href);
        }

        document.getElementById('qrImageInput').addEventListener('change', event => {
            scanQrImage(event.target.files?.[0]);
        });

        function openQrReturnConfirm(loan) {
            document.getElementById('qrReturnLoanId').value = loan.id;
            document.getElementById('qrReturnConfirmDetails').textContent =
                `Return "${loan.title}" borrowed by ${loan.borrowerName}?`;
            const overlay = document.getElementById('qrReturnConfirmOverlay');
            overlay.setAttribute('aria-hidden', 'false');
            overlay.classList.add('show');
            overlay.querySelector('.confirm-box').focus();
        }

        function closeQrReturnConfirm() {
            const overlay = document.getElementById('qrReturnConfirmOverlay');
            overlay.classList.remove('show');
            overlay.setAttribute('aria-hidden', 'true');
        }

        function confirmDelete(bookId, bookTitle) {
            const overlay = document.getElementById('confirmDeleteOverlay');
            modalReturnFocus.set('confirmDeleteOverlay', document.activeElement);
            document.getElementById('confirm-delete-text').textContent =
                `Are you sure you want to delete "${bookTitle}"? The BEFORE DELETE trigger will archive this record to audit logs automatically.`;
            document.getElementById('confirm-delete-book-id').value = bookId;
            overlay.setAttribute('aria-hidden', 'false');
            overlay.classList.add('show');
            overlay.querySelector('.confirm-box').focus();
        }
        function closeConfirm() {
            document.getElementById('confirmDeleteOverlay').classList.remove('show');
            document.getElementById('confirmDeleteOverlay').setAttribute('aria-hidden', 'true');
            modalReturnFocus.get('confirmDeleteOverlay')?.focus();
            modalReturnFocus.delete('confirmDeleteOverlay');
        }

        function toggleSidebar() {
            const isOpen = document.getElementById('sidebar').classList.toggle('open');
            const toggle = document.getElementById('mobileToggle');
            toggle.setAttribute('aria-expanded', String(isOpen));
            toggle.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
        }
        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('mobileToggle').setAttribute('aria-expanded', 'false');
            document.getElementById('mobileToggle').setAttribute('aria-label', 'Open navigation menu');
        }
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    if (this.id === 'qrScannerModal') closeQrScanner();
                    else closeModal(this.id);
                }
            });
        });
        document.getElementById('qrReturnConfirmOverlay').addEventListener('click', function(event) {
            if (event.target === this) closeQrReturnConfirm();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const openModalOverlay = document.querySelector('.modal-overlay.show');
                if (openModalOverlay) {
                    if (openModalOverlay.id === 'qrScannerModal') closeQrScanner();
                    else closeModal(openModalOverlay.id);
                } else if (document.getElementById('qrReturnConfirmOverlay').classList.contains('show')) closeQrReturnConfirm();
                else if (document.getElementById('confirmDeleteOverlay').classList.contains('show')) closeConfirm();
                return;
            }

            if (e.key === 'Tab') {
                const activeDialog = document.querySelector('.modal-overlay.show .modal, .confirm-overlay.show .confirm-box');
                if (!activeDialog) return;
                const focusable = [...activeDialog.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])')];
                if (focusable.length === 0) {
                    e.preventDefault();
                    activeDialog.focus();
                    return;
                }
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (e.shiftKey && (document.activeElement === first || document.activeElement === activeDialog)) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });

        const bookSearch = document.getElementById('bookSearch');
        const bookStatusFilter = document.getElementById('bookStatusFilter');
        function filterBooks() {
            const query = (bookSearch?.value || '').trim().toLowerCase();
            const status = bookStatusFilter?.value || 'all';
            const rows = [...document.querySelectorAll('#books-table tbody tr')];
            let visibleCount = 0;
            rows.forEach(row => {
                const matchesText = (row.dataset.search || '').includes(query);
                const matchesStatus = status === 'all' || row.dataset.status === status;
                row.hidden = !(matchesText && matchesStatus);
                if (!row.hidden) visibleCount++;
            });
            const noResults = document.getElementById('bookNoResults');
            if (noResults) noResults.hidden = visibleCount > 0;
            const resultCount = document.getElementById('bookResultCount');
            if (resultCount) {
                resultCount.textContent = query || status !== 'all'
                    ? `Showing ${visibleCount} of ${rows.length} books`
                    : `${rows.length} books · Sorted by book ID ↑`;
            }
            const clearButton = document.getElementById('clearBookFilters');
            if (clearButton) clearButton.disabled = !query && status === 'all';
        }

        function clearBookFilters() {
            if (!bookSearch || !bookStatusFilter) return;
            bookSearch.value = '';
            bookStatusFilter.value = 'all';
            filterBooks();
            bookSearch.focus();
        }
        bookSearch?.addEventListener('input', filterBooks);
        bookStatusFilter?.addEventListener('change', filterBooks);
        document.getElementById('clearBookFilters')?.addEventListener('click', clearBookFilters);

        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
            toast.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
            const text = document.createElement('span');
            text.textContent = message;
            toast.appendChild(text);
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 4000);
        }

        window.addEventListener('DOMContentLoaded', () => {
            const config = window.libraryAppConfig;
            if (config.message) {
                showToast(config.message.text, config.message.type);
                history.replaceState(null, '', window.location.pathname);
            }
            if (config.qrBorrowBook) {
                openBorrowModal(config.qrBorrowBook.id, config.qrBorrowBook.title, config.qrBorrowBook.isbn, config.qrBorrowBook.copyId || '');
            } else if (config.qrBorrowNotice) {
                showToast(config.qrBorrowNotice, 'warning');
            }
            if (config.qrReturnLoan) {
                openQrReturnConfirm(config.qrReturnLoan);
            } else if (config.qrReturnNotice) {
                showToast(config.qrReturnNotice, 'warning');
            }
        });
