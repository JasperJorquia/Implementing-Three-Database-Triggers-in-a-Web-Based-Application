<?php

require_once 'db.php';

$totalBooks = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
$totalAvailable = $pdo->query("SELECT SUM(available_copies) FROM books")->fetchColumn() ?: 0;
$activeLoans = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'Borrowed'")->fetchColumn();
$auditCount = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();

$loans = $pdo->query("SELECT l.*, b.title FROM loans l JOIN books b ON l.book_id = b.book_id WHERE l.status = 'Borrowed' ORDER BY l.loan_id DESC")->fetchAll();
$books = $pdo->query("SELECT * FROM books ORDER BY book_id DESC")->fetchAll();
$bookCount = count($books);
$logs = $pdo->query("SELECT * FROM audit_logs ORDER BY log_id DESC")->fetchAll();

$msg = $_GET['msg'] ?? '';
$msgMap = [
    'borrowed' => ['Book borrowed successfully!', 'success'],
    'returned' => ['Book returned successfully!', 'success'],
    'deleted'  => ['Book deleted successfully!', 'warning'],
    'added'    => ['New book added successfully!', 'success'],
    'invalid'  => ['Please check the submitted information and try again.', 'error'],
    'not_active' => ['That loan is no longer active.', 'warning'],
    'not_found' => ['That book could not be found.', 'warning'],
    'error'    => ['The database could not complete that action. Check the server log.', 'error'],
];
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
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-card: #1e293b;
            --bg-card-hover: #263348;
            --bg-input: #0f172a;
            --bg-table-row: #1e293b;
            --bg-table-hover: #263348;
            --border-color: #334155;
            --border-light: #475569;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent-blue: #3b82f6;
            --accent-blue-glow: rgba(59, 130, 246, 0.25);
            --accent-green: #22c55e;
            --accent-green-glow: rgba(34, 197, 94, 0.2);
            --accent-amber: #f59e0b;
            --accent-amber-glow: rgba(245, 158, 11, 0.2);
            --accent-purple: #a855f7;
            --accent-purple-glow: rgba(168, 85, 247, 0.2);
            --accent-red: #ef4444;
            --accent-red-glow: rgba(239, 68, 68, 0.2);
            --accent-cyan: #06b6d4;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.4);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.5);
            --sidebar-width: 260px;
            --transition: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        html { scroll-behavior: smooth; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }
        a { color: var(--accent-blue); text-decoration: none; }
        button { font-family: inherit; cursor: pointer; border: none; }
        input, select { font-family: inherit; }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--border-light); }

        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-secondary);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0;
            height: 100vh;
            z-index: 100;
            transition: transform var(--transition);
        }
        .sidebar-brand {
            padding: 28px 24px 24px;
            border-bottom: 1px solid var(--border-color);
        }
        .sidebar-brand h1 {
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar-brand h1 .brand-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
        }
        .sidebar-brand p {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 6px;
            font-weight: 500;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .sidebar-nav { padding: 16px 12px; flex: 1; }
        .nav-label {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-muted);
            padding: 8px 12px 8px;
            margin-top: 8px;
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 16px;
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
            transition: all var(--transition);
            cursor: pointer;
            margin-bottom: 2px;
        }
        .nav-item:hover { background: var(--bg-card-hover); color: var(--text-primary); }
        .nav-item.active {
            background: linear-gradient(135deg, rgba(59,130,246,0.15), rgba(168,85,247,0.1));
            color: var(--accent-blue);
            font-weight: 600;
        }
        .nav-item .nav-icon { font-size: 1.1rem; width: 22px; text-align: center; }
        .nav-item .badge {
            margin-left: auto;
            background: var(--accent-blue);
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 50px;
            min-width: 22px;
            text-align: center;
        }
        .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--border-color);
            font-size: 0.75rem;
            color: var(--text-muted);
            text-align: center;
        }
        .sidebar-footer span { font-weight: 600; color: var(--text-secondary); }

        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-color);
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .topbar-left h2 {
            font-size: 1.3rem;
            font-weight: 700;
            letter-spacing: -0.01em;
        }
        .topbar-left p {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
        .topbar-actions { display: flex; gap: 10px; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 600;
            transition: all var(--transition);
            border: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent-blue), #2563eb);
            color: #fff;
            box-shadow: 0 2px 12px var(--accent-blue-glow);
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 20px var(--accent-blue-glow);
        }
        .btn-secondary {
            background: var(--bg-card);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        .btn-secondary:hover { background: var(--bg-card-hover); border-color: var(--border-light); }
        .btn-danger {
            background: rgba(239, 68, 68, 0.15);
            color: var(--accent-red);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .btn-danger:hover { background: rgba(239, 68, 68, 0.25); }
        .btn-success {
            background: rgba(34, 197, 94, 0.15);
            color: var(--accent-green);
            border: 1px solid rgba(34, 197, 94, 0.3);
        }
        .btn-success:hover { background: rgba(34, 197, 94, 0.25); }
        .btn-warning {
            background: rgba(245, 158, 11, 0.15);
            color: var(--accent-amber);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .btn-warning:hover { background: rgba(245, 158, 11, 0.25); }
        .btn-sm { padding: 6px 14px; font-size: 0.78rem; }
        .btn-xs { padding: 5px 10px; font-size: 0.72rem; border-radius: 6px; }

        .content { padding: 28px 32px; flex: 1; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 24px;
            transition: all var(--transition);
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        .stat-card:nth-child(1)::before { background: linear-gradient(90deg, var(--accent-blue), var(--accent-cyan)); }
        .stat-card:nth-child(2)::before { background: linear-gradient(90deg, var(--accent-green), #16a34a); }
        .stat-card:nth-child(3)::before { background: linear-gradient(90deg, var(--accent-amber), #d97706); }
        .stat-card:nth-child(4)::before { background: linear-gradient(90deg, var(--accent-purple), #9333ea); }
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); border-color: var(--border-light); }
        .stat-card .stat-icon {
            width: 44px; height: 44px;
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            margin-bottom: 16px;
        }
        .stat-card:nth-child(1) .stat-icon { background: rgba(59,130,246,0.15); }
        .stat-card:nth-child(2) .stat-icon { background: rgba(34,197,94,0.15); }
        .stat-card:nth-child(3) .stat-icon { background: rgba(245,158,11,0.15); }
        .stat-card:nth-child(4) .stat-icon { background: rgba(168,85,247,0.15); }
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.1;
        }
        .stat-card:nth-child(1) .stat-value { color: var(--accent-blue); }
        .stat-card:nth-child(2) .stat-value { color: var(--accent-green); }
        .stat-card:nth-child(3) .stat-value { color: var(--accent-amber); }
        .stat-card:nth-child(4) .stat-value { color: var(--accent-purple); }
        .stat-card .stat-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-top: 4px;
        }

        .tabs-container {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        .tabs-header {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            padding: 0 8px;
            background: rgba(15,23,42,0.4);
        }
        .tab-btn {
            padding: 14px 24px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            background: none;
            border: none;
            cursor: pointer;
            position: relative;
            transition: all var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tab-btn:hover { color: var(--text-secondary); }
        .tab-btn.active { color: var(--accent-blue); }
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: 0; left: 16px; right: 16px;
            height: 2px;
            background: var(--accent-blue);
            border-radius: 2px 2px 0 0;
        }
        .tab-btn .tab-count {
            background: var(--bg-primary);
            font-size: 0.7rem;
            padding: 1px 8px;
            border-radius: 50px;
            color: var(--text-muted);
            font-weight: 700;
        }
        .tab-btn.active .tab-count { background: var(--accent-blue-glow); color: var(--accent-blue); }
        .tab-panel { display: none; padding: 0; }
        .tab-panel.active { display: block; }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table thead th {
            text-align: left;
            padding: 14px 20px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            background: rgba(15,23,42,0.3);
            border-bottom: 1px solid var(--border-color);
        }
        .data-table tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: background var(--transition);
        }
        .data-table tbody tr:last-child { border-bottom: none; }
        .data-table tbody tr:hover { background: var(--bg-card-hover); }
        .data-table td {
            padding: 14px 20px;
            font-size: 0.875rem;
            color: var(--text-secondary);
            vertical-align: middle;
        }
        .data-table td:first-child { color: var(--text-muted); font-weight: 600; font-size: 0.8rem; }
        .data-table .title-cell { color: var(--text-primary); font-weight: 600; }
        .data-table .actions-cell { display: flex; gap: 8px; flex-wrap: wrap; }

        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .badge-available { background: rgba(34,197,94,0.15); color: var(--accent-green); }
        .badge-borrowed { background: rgba(245,158,11,0.15); color: var(--accent-amber); }
        .badge-none { background: rgba(239,68,68,0.15); color: var(--accent-red); }
        .badge-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            display: inline-block;
        }
        .badge-available .badge-dot { background: var(--accent-green); }
        .badge-borrowed .badge-dot { background: var(--accent-amber); }
        .badge-none .badge-dot { background: var(--accent-red); }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: var(--text-muted);
        }
        .empty-state .empty-icon { font-size: 3rem; margin-bottom: 16px; opacity: 0.5; }
        .empty-state h3 { font-size: 1.1rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; }
        .empty-state p { font-size: 0.85rem; }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal-overlay.show { display: flex; opacity: 1; }
        .modal {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            width: 90%;
            max-width: 520px;
            box-shadow: var(--shadow-lg);
            transform: translateY(20px) scale(0.97);
            transition: transform 0.3s ease;
            overflow: hidden;
        }
        .modal-overlay.show .modal { transform: translateY(0) scale(1); }
        .modal-header {
            padding: 24px 28px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-header h3 { font-size: 1.15rem; font-weight: 700; }
        .modal-close {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            background: var(--bg-primary);
            color: var(--text-muted);
            font-size: 1.1rem;
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all var(--transition);
        }
        .modal-close:hover { background: var(--bg-card-hover); color: var(--text-primary); }
        .modal-body { padding: 24px 28px; }
        .modal-footer {
            padding: 16px 28px 24px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 7px;
        }
        .form-control {
            width: 100%;
            padding: 11px 16px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 0.9rem;
            transition: all var(--transition);
            outline: none;
        }
        .form-control:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px var(--accent-blue-glow);
        }
        .form-control::placeholder { color: var(--text-muted); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

        .toast-container {
            position: fixed;
            top: 24px; right: 24px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .toast {
            padding: 14px 20px;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: var(--shadow-lg);
            animation: toastIn 0.4s ease-out, toastOut 0.4s ease-in 3.5s forwards;
            min-width: 280px;
        }
        .toast-success { background: #065f46; color: #a7f3d0; border: 1px solid #047857; }
        .toast-warning { background: #78350f; color: #fde68a; border: 1px solid #92400e; }
        .toast-error { background: #7f1d1d; color: #fecaca; border: 1px solid #991b1b; }
        @keyframes toastIn { from { transform: translateX(100px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes toastOut { from { opacity: 1; } to { opacity: 0; transform: translateY(-10px); } }

        .trigger-banner {
            background: linear-gradient(135deg, rgba(59,130,246,0.08), rgba(168,85,247,0.08));
            border: 1px solid rgba(59,130,246,0.2);
            border-radius: var(--radius-md);
            padding: 16px 24px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        .trigger-banner .banner-icon {
            width: 38px; height: 38px;
            background: rgba(59,130,246,0.15);
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .trigger-banner strong { color: var(--text-primary); }

        .mobile-toggle {
            display: none;
            position: fixed;
            bottom: 24px; right: 24px;
            z-index: 200;
            width: 52px; height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            color: #fff;
            font-size: 1.4rem;
            border: none;
            box-shadow: var(--shadow-lg);
            cursor: pointer;
            align-items: center;
            justify-content: center;
        }

        .confirm-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(6px);
            z-index: 1100;
            align-items: center;
            justify-content: center;
        }
        .confirm-overlay.show { display: flex; }
        .confirm-box {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 32px;
            max-width: 400px;
            text-align: center;
            box-shadow: var(--shadow-lg);
        }
        .confirm-box .confirm-icon {
            font-size: 2.5rem;
            margin-bottom: 16px;
        }
        .confirm-box h3 { font-size: 1.1rem; margin-bottom: 8px; }
        .confirm-box p { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 24px; }
        .confirm-box .confirm-actions { display: flex; gap: 10px; justify-content: center; }

        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-toggle { display: flex; }
            .stats-grid { grid-template-columns: 1fr; }
            .topbar { padding: 14px 20px; }
            .content { padding: 20px 16px; }
            .form-row { grid-template-columns: 1fr; }
        }

        @keyframes shimmer { to { background-position: -200% 0; } }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in { animation: fadeInUp 0.5s ease-out both; }
        .delay-1 { animation-delay: 0.05s; }
        .delay-2 { animation-delay: 0.1s; }
        .delay-3 { animation-delay: 0.15s; }
        .delay-4 { animation-delay: 0.2s; }
        .delay-5 { animation-delay: 0.3s; }
        .delay-6 { animation-delay: 0.35s; }

        :root {
            --bg-primary: #101827; --bg-secondary: #152235; --bg-card: #18283d;
            --bg-card-hover: #20344c; --bg-input: #101b2b; --bg-table-row: #17263a;
            --bg-table-hover: #20344c; --border-color: #2b3c50; --border-light: #405873;
            --text-primary: #e8eff8; --text-secondary: #a9b9ca; --text-muted: #7f92a7;
            --accent-blue: #72a7ff; --accent-blue-glow: rgba(81, 145, 240, .20);
            --accent-green: #45d6a0; --accent-green-glow: rgba(69,214,160,.13);
            --accent-amber: #ffc46b; --accent-purple: #b4a2ff; --accent-red: #ff8e8e;
            --sidebar-width: 244px; --radius-sm: 7px; --radius-md: 10px; --radius-lg: 12px; --radius-xl: 16px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,.15); --shadow-md: 0 5px 18px rgba(0,0,0,.18); --shadow-lg: 0 15px 38px rgba(0,0,0,.28);
        }
        body { background: #101827; font-size: 13px; }
        .sidebar { background: #121f30; border-right-color: #27384b; }
        .sidebar-brand { padding: 22px 20px 20px; border-bottom-color: #27384b; }
        .sidebar-brand h1 { font-size: 1.05rem; font-weight: 700; letter-spacing: -.02em; line-height: 1.3; }
        .sidebar-brand h1 .brand-icon { width: 32px; height: 32px; background: #256b67; border-radius: 7px; font-size: .95rem; flex-shrink: 0; }
        .nav-label { padding-left: 12px; margin-top: 15px; letter-spacing: .08em; }
        .nav-item { border-radius: 7px; padding: 10px 13px; font-size: .82rem; }
        .nav-item.active { background: rgba(65, 162, 155, .13); color: #7dd5ca; }
        .nav-item .badge { background: #2a6d70; }
        .sidebar-footer { text-align: left; padding: 17px 20px; line-height: 1.7; }
        .main-content { background: #101827; }
        .topbar { min-height: 52px; padding: 12px 30px; background: #131f30; border-bottom-color: #27384b; justify-content: flex-end; }
        .topbar-left h2 { font-size: .86rem; font-weight: 600; color: #c6d4e3; letter-spacing: 0; }
        .topbar-left h2 .crumb { color: #5b7186; margin: 0 10px; }
        .topbar-left p { font-size: .72rem; }
        .content { padding: 27px 30px 30px; max-width: 1600px; width: 100%; margin: 0 auto; }
        .btn { border-radius: 7px; font-weight: 600; }
        .btn-primary { background: #247c77; color: #f2fffd; box-shadow: none; }
        .btn-primary:hover { background: #2b9088; box-shadow: 0 4px 14px rgba(36,124,119,.18); }
        .btn-secondary { background: #17263a; border: 1px solid #30445a; color: #c6d4e2; }
        .btn-secondary:hover { background: #20344c; border-color: #49627c; }
        .btn-success { background: rgba(69,214,160,.12); color: #70e0b5; border: 1px solid rgba(69,214,160,.18); }
        .btn-warning { background: rgba(255,196,107,.12); color: #ffd18a; border: 1px solid rgba(255,196,107,.22); }
        .btn-danger { background: rgba(255,142,142,.1); color: #ffa1a1; border: 1px solid rgba(255,142,142,.2); }
        .trigger-banner { background: #152c40; border-color: #294b61; color: #a9c0d2; padding: 13px 17px; margin-bottom: 19px; border-radius: 9px; }
        .trigger-banner .banner-icon { background: #1d4056; color: #78cfc8; }
        .trigger-banner strong { color: #d9edf3; }
        .stats-grid { gap: 14px; margin-bottom: 21px; }
        .stat-card { background: #152235; border-color: #2a3b50; padding: 15px 17px; border-radius: 10px; min-height: auto; }
        .stat-card::before { display: none; }
        .stat-card:hover { transform: translateY(-1px); border-color: #3b536b; box-shadow: var(--shadow-md); }
        .stat-card .stat-icon { float: right; width: 26px; height: 26px; margin: 0; font-size: .9rem; background: transparent !important; color: #829bb0; }
        .stat-card .stat-value { font-size: 1.55rem; color: #e1ebf4 !important; margin-top: 6px; }
        .stat-card:nth-child(2) .stat-value { color: #64d4bc !important; }
        .stat-card .stat-label { margin-top: 3px; font-size: .72rem; }
        .tabs-container { background: #152235; border-color: #2a3b50; border-radius: 11px; }
        .tabs-header { background: #152235; padding: 0 11px; }
        .tab-btn { padding: 15px 17px; color: #8fa4b8; font-size: .8rem; }
        .tab-btn.active { color: #78cfc8; }
        .tab-btn.active::after { background: #3a9b93; left: 13px; right: 13px; }
        .tab-btn .tab-count { background: #203047; color: #9cb0c4; }
        .tab-btn.active .tab-count { background: rgba(58,155,147,.16); color: #80d4cb; }
        .catalog-toolbar { display: flex; gap: 10px; align-items: center; padding: 15px 16px; border-bottom: 1px solid var(--border-color); flex-wrap: wrap; }
        .search-field { display: flex; align-items: center; gap: 9px; padding: 0 12px; min-width: 240px; flex: 1; height: 38px; background: #101b2b; border: 1px solid #2c4055; border-radius: 7px; color: #8197ab; }
        .search-field span { font-size: 1.35rem; line-height: 1; }
        .search-field input { flex: 1; min-width: 80px; background: transparent; border: 0; outline: 0; color: var(--text-primary); font: inherit; font-size: .76rem; }
        .search-field input::placeholder { color: #7f92a7; }
        .catalog-toolbar select { height: 38px; min-width: 122px; padding: 0 12px; background: #101b2b; border: 1px solid #2c4055; border-radius: 7px; color: #c3d0dc; font: inherit; font-size: .76rem; }
        .sort-label { margin-left: auto; color: #879bad; font-size: .7rem; white-space: nowrap; }
        .data-table thead th { padding: 11px 15px; background: #1a2b40; color: #91a6b8; border-bottom-color: #2b3e52; font-size: .67rem; letter-spacing: .02em; text-transform: none; }
        .data-table tbody tr { border-bottom-color: #283a4e; }
        .data-table tbody tr:hover { background: #1b2d43; }
        .data-table td { padding: 12px 15px; color: #a5b6c7; font-size: .79rem; }
        .data-table td:first-child { color: #7f93a8; font-size: .72rem; }
        .data-table .title-cell { color: #dce8f2; }
        .book-author-cell { min-width: 190px; }
        .book-title, .book-author { display: block; }
        .book-title { color: #d9e5ef; font-weight: 550; }
        .book-author { color: #8ca1b4; font-size: .68rem; margin-top: 2px; }
        .data-table code { white-space: nowrap; }
        .badge-status { padding: 4px 9px; border-radius: 5px; font-size: .67rem; font-weight: 600; }
        .badge-available { background: rgba(69,214,160,.12); color: #75dbb6; }
        .badge-borrowed { background: rgba(255,196,107,.12); color: #ffcd85; }
        .badge-none { background: rgba(255,142,142,.12); color: #ffa1a1; }
        .badge-dot { width: 5px; height: 5px; }
        .trigger-footer { display: flex; gap: 14px; align-items: center; margin-top: 22px; border: 1px solid #28534f; background: #142e3a; border-radius: 10px; padding: 15px 17px; }
        .trigger-footer-icon { width: 36px; height: 36px; display: grid; place-items: center; border-radius: 7px; background: #1f494f; color: #83d5cd; font-size: 1.2rem; flex-shrink: 0; }
        .trigger-footer-copy { display: grid; gap: 3px; flex: 1; }
        .trigger-footer-copy strong { font-size: .78rem; color: #d4e9e8; }
        .trigger-footer-copy span { font-size: .71rem; color: #8dbab9; }
        .inline-action-form { display: inline-flex; margin: 0; }
        .modal, .confirm-box { background: #152235; border-color: #30445a; }
        .modal-header, .modal-footer { border-color: #2a3d51; }
        .form-control { background: #101b2b; border-color: #30445a; }
        .form-control:focus { border-color: #3b9a94; box-shadow: 0 0 0 3px rgba(59,154,148,.14); }
        .modal-close { background: #101b2b; border-color: #30445a; }
        .toast-success { background: #16463f; color: #a8f1dd; border-color: #276b61; }
        @media (max-width: 768px) {
            .content { padding: 17px 12px 24px; }
            .topbar { padding: 13px 15px; }
            .stats-grid { grid-template-columns: repeat(2, minmax(0,1fr)); gap: 9px; }
            .stat-card { padding: 14px; min-height: 98px; }
            .stat-card .stat-value { font-size: 1.55rem; }
            .catalog-toolbar { padding: 12px; }
            .sort-label { width: 100%; margin-left: 0; }
            .trigger-footer { align-items: flex-start; flex-wrap: wrap; }
            .trigger-footer .btn { margin-left: 49px; }
            .data-table td, .data-table thead th { padding: 10px 11px; }
        }
    </style>
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
                <div class="tabs-header">
                    <button class="tab-btn active" data-tab="books" onclick="switchTab('books');">
                        ▤ All books <span class="tab-count"><?= count($books) ?></span>
                    </button>
                    <button class="tab-btn" data-tab="loans" onclick="switchTab('loans');">
                        ⇄ Checked out <span class="tab-count"><?= count($loans) ?></span>
                    </button>
                    <button class="tab-btn" data-tab="audit" onclick="switchTab('audit');">
                        ☷ Audit trail <span class="tab-count"><?= count($logs) ?></span>
                    </button>
                </div>

                <div class="catalog-toolbar">
                    <label class="search-field" for="bookSearch"><span aria-hidden="true">⌕</span><input id="bookSearch" type="search" placeholder="Search by title, author, ISBN or book ID..."></label>
                    <select id="bookStatusFilter" aria-label="Filter by availability"><option value="all">All statuses</option><option value="available">Available</option><option value="unavailable">Unavailable</option></select>
                    <span class="sort-label">Sorted by book ID ↑</span>
                </div>

                <div class="tab-panel active" id="panel-books">
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
                                <tr data-search="<?= htmlspecialchars(strtolower($book['book_id'].' '.$book['title'].' '.$book['author'].' '.$book['isbn']), ENT_QUOTES) ?>" data-status="<?= $book['available_copies'] > 0 ? 'available' : 'unavailable' ?>">
                                    <td>#<?= $book['book_id'] ?></td>
                                    <td class="book-author-cell"><span class="book-title"><?= htmlspecialchars($book['title']) ?></span><span class="book-author"><?= htmlspecialchars($book['author']) ?></span></td>
                                    <td><code style="background:var(--bg-primary);padding:3px 8px;border-radius:4px;font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($book['isbn']) ?></code></td>
                                    <td>
                                        <strong style="color:var(--text-primary);"><?= $book['available_copies'] ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($book['available_copies'] > 0): ?>
                                            <span class="badge-status badge-available"><span class="badge-dot"></span> Available</span>
                                        <?php else: ?>
                                            <span class="badge-status badge-none"><span class="badge-dot"></span> Unavailable</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="actions-cell">
                                            <?php if ($book['available_copies'] > 0): ?>
                                            <button class="btn btn-success btn-xs" onclick="openBorrowModal(<?= $book['book_id'] ?>, '<?= addslashes(htmlspecialchars($book['title'])) ?>');">
                                                 Borrow
                                            </button>
                                            <?php else: ?>
                                            <button class="btn btn-xs" style="opacity:0.4;cursor:not-allowed;background:var(--bg-primary);color:var(--text-muted);border:1px solid var(--border-color);" disabled>
                                                 Borrow
                                            </button>
                                            <?php endif; ?>
                                            <button class="btn btn-danger btn-xs" onclick="confirmDelete(<?= $book['book_id'] ?>, '<?= addslashes(htmlspecialchars($book['title'])) ?>');">
                                                 Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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

                <div class="tab-panel" id="panel-loans">
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
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($loans as $loan): ?>
                                <tr>
                                    <td>#<?= $loan['loan_id'] ?></td>
                                    <td class="title-cell"><?= htmlspecialchars($loan['title']) ?></td>
                                    <td><?= htmlspecialchars($loan['borrower_name']) ?></td>
                                    <td><?= $loan['borrow_date'] ?></td>
                                    <td>
                                        <span class="badge-status badge-borrowed"><span class="badge-dot"></span> Borrowed</span>
                                    </td>
                                    <td>
                                        <form action="action.php?action=return" method="POST" onsubmit="return confirm('Return this book?');" class="inline-action-form">
                                            <input type="hidden" name="loan_id" value="<?= (int)$loan['loan_id'] ?>">
                                            <button type="submit" class="btn btn-warning btn-xs">↩ Return book</button>
                                        </form>
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

                <div class="tab-panel" id="panel-audit">
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
                                    <td>#<?= $log['log_id'] ?></td>
                                    <td>
                                        <span class="badge-status" style="background:rgba(168,85,247,0.15);color:var(--accent-purple);">
                                            <?= htmlspecialchars($log['action'] ?? $log['action_type'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td style="max-width:400px;word-wrap:break-word;"><?= htmlspecialchars($log['details'] ?? $log['description'] ?? 'N/A') ?></td>
                                    <td style="white-space:nowrap;"><?= $log['timestamp'] ?? $log['created_at'] ?? 'N/A' ?></td>
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

    <div class="modal-overlay" id="addBookModal">
        <div class="modal">
            <div class="modal-header">
                <h3> Add New Book</h3>
                <button class="modal-close" onclick="closeModal('addBookModal');">✕</button>
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

    <div class="modal-overlay" id="borrowModal">
        <div class="modal">
            <div class="modal-header">
                <h3> Borrow Book</h3>
                <button class="modal-close" onclick="closeModal('borrowModal');">✕</button>
            </div>
            <form action="action.php?action=borrow" method="POST">
                <input type="hidden" name="book_id" id="borrow-book-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Selected Book</label>
                        <div id="borrow-book-title" style="padding:11px 16px;background:var(--bg-input);border:1px solid var(--border-color);border-radius:var(--radius-sm);color:var(--accent-blue);font-weight:600;font-size:0.9rem;"></div>
                    </div>
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

    <div class="confirm-overlay" id="confirmDeleteOverlay">
        <div class="confirm-box">
            <h3>Delete Book?</h3>
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

    <button class="mobile-toggle" id="mobileToggle" onclick="toggleSidebar();">☰</button>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
            document.querySelector(`.tab-btn[data-tab="${tabName}"]`).classList.add('active');
            document.getElementById(`panel-${tabName}`).classList.add('active');

            document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
            const navItem = document.querySelector(`.nav-item[data-section="${tabName}"]`);
            if (navItem) navItem.classList.add('active');
            document.getElementById('tabs-container').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
            document.querySelector('.nav-item[data-section="dashboard"]').classList.add('active');
        }

        function openModal(id) {
            const modal = document.getElementById(id);
            modal.style.display = 'flex';
            requestAnimationFrame(() => modal.classList.add('show'));
        }
        function closeModal(id) {
            const modal = document.getElementById(id);
            modal.classList.remove('show');
            setTimeout(() => { modal.style.display = 'none'; }, 300);
        }
        function openBorrowModal(bookId, bookTitle) {
            document.getElementById('borrow-book-id').value = bookId;
            document.getElementById('borrow-book-title').textContent = bookTitle;
            document.getElementById('borrow-name').value = '';
            openModal('borrowModal');
        }

        function confirmDelete(bookId, bookTitle) {
            document.getElementById('confirm-delete-text').textContent =
                `Are you sure you want to delete "${bookTitle}"? The BEFORE DELETE trigger will archive this record to audit logs automatically.`;
            document.getElementById('confirm-delete-book-id').value = bookId;
            document.getElementById('confirmDeleteOverlay').classList.add('show');
        }
        function closeConfirm() {
            document.getElementById('confirmDeleteOverlay').classList.remove('show');
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
        }
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                    setTimeout(() => { this.style.display = 'none'; }, 300);
                }
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.show').forEach(m => closeModal(m.id));
                closeConfirm();
            }
        });

        const bookSearch = document.getElementById('bookSearch');
        const bookStatusFilter = document.getElementById('bookStatusFilter');
        function filterBooks() {
            const query = (bookSearch?.value || '').trim().toLowerCase();
            const status = bookStatusFilter?.value || 'all';
            document.querySelectorAll('#books-table tbody tr').forEach(row => {
                const matchesText = (row.dataset.search || '').includes(query);
                const matchesStatus = status === 'all' || row.dataset.status === status;
                row.hidden = !(matchesText && matchesStatus);
            });
        }
        bookSearch?.addEventListener('input', filterBooks);
        bookStatusFilter?.addEventListener('change', filterBooks);

        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `<span>${message}</span>`;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 4000);
        }

        <?php if ($msg && isset($msgMap[$msg])): ?>
        window.addEventListener('DOMContentLoaded', () => {
            showToast('<?= $msgMap[$msg][0] ?>', '<?= $msgMap[$msg][1] ?>');
            history.replaceState(null, '', window.location.pathname);
        });
        <?php endif; ?>
    </script>
</body>
</html>