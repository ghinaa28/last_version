<?php
session_start();
include "connection.php";

// Check if user is logged in as company
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['company_id'];

// Get company information
$stmt = $conn->prepare("SELECT * FROM companies WHERE company_id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();

if (!$company) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Handle message status update and email replies
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $message_id = $_POST['message_id'] ?? '';
        $action = $_POST['action'] ?? '';
        
        if ($message_id && in_array($action, ['read', 'replied'])) {
            if ($action === 'read') {
                $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE message_id = ? AND company_id = ?");
                $stmt->bind_param("ii", $message_id, $company_id);
            } elseif ($action === 'replied') {
                $stmt = $conn->prepare("UPDATE messages SET is_read = 1, replied = 1 WHERE message_id = ? AND company_id = ?");
                $stmt->bind_param("ii", $message_id, $company_id);
            }
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $success_message = "Message status updated successfully!";
            } else {
                $error_message = "Failed to update message status.";
            }
        }
    } elseif (isset($_POST['send_reply'])) {
        // Handle email reply
        $message_id = $_POST['message_id'] ?? '';
        $reply_subject = trim($_POST['reply_subject'] ?? '');
        $reply_message = trim($_POST['reply_message'] ?? '');
        $student_email = $_POST['student_email'] ?? '';
        $student_name = $_POST['student_name'] ?? '';
        
        $errors = [];
        
        if (empty($reply_subject)) {
            $errors[] = "Reply subject is required";
        }
        
        if (empty($reply_message)) {
            $errors[] = "Reply message is required";
        } elseif (strlen($reply_message) < 10) {
            $errors[] = "Reply message must be at least 10 characters long";
        }
        
        if (empty($errors)) {
            // Send email
            $company_name = $company['company_name'];
            $company_email = $company['email'];
            
            $email_subject = "Re: " . $reply_subject;
            $email_body = "Dear {$student_name},

Thank you for your interest in our internship opportunities and for reaching out to us.

{$reply_message}

We appreciate your interest in joining our team and look forward to potentially working with you.

Best regards,
{$company_name}
{$company_email}

---
This email was sent through the Internship Management System.
If you have any questions, please feel free to contact us directly.";
            
            $headers = "From: {$company_email}\r\n";
            $headers .= "Reply-To: {$company_email}\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
            if (mail($student_email, $email_subject, $email_body, $headers)) {
                // Mark message as replied
                $stmt = $conn->prepare("UPDATE messages SET is_read = 1, replied = 1 WHERE message_id = ? AND company_id = ?");
                $stmt->bind_param("ii", $message_id, $company_id);
                $stmt->execute();
                
                $success_message = "Reply sent successfully to {$student_name}!";
            } else {
                $error_message = "Failed to send email reply. Please try again.";
            }
        } else {
            $error_message = implode("<br>", $errors);
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for messages
$sql = "SELECT m.*, 
        CONCAT(s.first_name, ' ', s.last_name) as full_name, s.email, s.phone, s.university, s.department,
        i.title as internship_title, i.department as internship_department
        FROM messages m
        JOIN students s ON m.student_id = s.student_id
        LEFT JOIN internships i ON m.internship_id = i.internship_id
        WHERE m.company_id = ? AND m.sender_type = 'student'";

$params = [$company_id];
$param_types = "i";

if (!empty($status_filter)) {
    if ($status_filter === 'unread') {
        $sql .= " AND m.is_read = 0";
    } elseif ($status_filter === 'read') {
        $sql .= " AND m.is_read = 1 AND m.replied = 0";
    } elseif ($status_filter === 'replied') {
        $sql .= " AND m.replied = 1";
    }
}

if (!empty($search)) {
    $sql .= " AND (CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR s.email LIKE ? OR m.subject LIKE ? OR m.message_content LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ssss";
}

$sql .= " ORDER BY m.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$messages = $stmt->get_result();

// Get message statistics
$stats_sql = "SELECT 
    COUNT(*) as total_messages,
    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_count,
    SUM(CASE WHEN is_read = 1 AND replied = 0 THEN 1 ELSE 0 END) as read_count,
    SUM(CASE WHEN replied = 1 THEN 1 ELSE 0 END) as replied_count
    FROM messages 
    WHERE company_id = ? AND sender_type = 'student'";

$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collaboration Requests - Company Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand: #0ea5a8;
            --brand-2: #22d3ee;
            --ink: #0b1f3a;
            --muted: #475569;
            --panel: #ffffff;
            --line: #e5e7eb;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
            --radius: 12px;
            --radius-lg: 16px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Inter", sans-serif;
            background: linear-gradient(135deg, rgba(14,165,168,0.05), rgba(34,211,238,0.05));
            color: var(--ink);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .breadcrumb a {
            color: var(--brand);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .breadcrumb a:hover {
            color: var(--brand-2);
        }

        h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: var(--muted);
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--panel);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--line);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--brand), var(--brand-2));
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 900;
            color: var(--brand);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        .filters {
            background: var(--panel);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--line);
            margin-bottom: 2rem;
        }

        .filters h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .filter-input,
        .filter-select {
            padding: 0.75rem;
            border: 2px solid var(--line);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }

        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: var(--brand);
        }

        .messages-section {
            background: var(--panel);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--line);
            overflow: hidden;
        }

        .messages-header {
            background: linear-gradient(135deg, rgba(14,165,168,0.1), rgba(34,211,238,0.1));
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--line);
        }

        .messages-header h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--ink);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .message-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .message-item {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--line);
            transition: background-color 0.2s ease;
            cursor: pointer;
        }

        .message-item:hover {
            background: rgba(14,165,168,0.05);
        }

        .message-item.unread {
            background: rgba(14,165,168,0.08);
            border-left: 4px solid var(--brand);
        }

        .message-item.replied {
            background: rgba(16,185,129,0.08);
            border-left: 4px solid var(--success);
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .message-info h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .message-meta {
            color: var(--muted);
            font-size: 0.9rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .message-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-unread {
            background: rgba(239,68,68,0.1);
            color: var(--error);
        }

        .status-read {
            background: rgba(16,185,129,0.1);
            color: var(--success);
        }

        .status-replied {
            background: rgba(14,165,168,0.1);
            color: var(--brand);
        }

        .message-content {
            margin-bottom: 1rem;
        }

        .message-subject {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .message-text {
            color: var(--muted);
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .message-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius);
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .btn-primary {
            background: var(--brand);
            color: white;
        }

        .btn-primary:hover {
            background: var(--brand-2);
        }

        .btn-secondary {
            background: var(--muted);
            color: white;
        }

        .btn-secondary:hover {
            background: var(--ink);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .no-messages {
            text-align: center;
            padding: 3rem;
            color: var(--muted);
        }

        .no-messages i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--line);
        }

        .alert {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(16,185,129,0.1);
            color: var(--success);
            border: 1px solid rgba(16,185,129,0.2);
        }

        .alert-error {
            background: rgba(239,68,68,0.1);
            color: var(--error);
            border: 1px solid rgba(239,68,68,0.2);
        }

        /* Modal Styles */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--panel);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--line);
            background: linear-gradient(135deg, rgba(14,165,168,0.1), rgba(34,211,238,0.1));
        }

        .modal-header h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--ink);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .close {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--muted);
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .close:hover {
            color: var(--ink);
        }

        .modal form {
            padding: 2rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--line);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--brand);
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        .original-message-display {
            background: rgba(14,165,168,0.05);
            border: 1px solid rgba(14,165,168,0.2);
            border-radius: var(--radius);
            padding: 1rem;
            font-size: 0.9rem;
            color: var(--muted);
            max-height: 150px;
            overflow-y: auto;
            white-space: pre-wrap;
            line-height: 1.5;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .message-header {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .message-actions {
                flex-wrap: wrap;
            }

            .modal-content {
                width: 95%;
                margin: 1rem;
            }

            .modal-header,
            .modal form {
                padding: 1rem;
            }

            .modal-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="company_dashboard.php">Company Portal</a>
                <i class="fas fa-chevron-right"></i>
                <span>Collaboration Requests</span>
            </div>
            <h1>Collaboration Requests</h1>
            <p class="subtitle">Messages and requests from students interested in your internships</p>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_messages']; ?></div>
                <div class="stat-label">Total Messages</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['unread_count']; ?></div>
                <div class="stat-label">Unread</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['read_count']; ?></div>
                <div class="stat-label">Read</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['replied_count']; ?></div>
                <div class="stat-label">Replied</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <h3>
                <i class="fas fa-filter"></i>
                Filter Messages
            </h3>
            <form method="GET" action="collaboration_request.php">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Search by student name, email, or message content..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="filter-select">
                            <option value="">All Statuses</option>
                            <option value="unread" <?php echo $status_filter === 'unread' ? 'selected' : ''; ?>>Unread</option>
                            <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>>Read</option>
                            <option value="replied" <?php echo $status_filter === 'replied' ? 'selected' : ''; ?>>Replied</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Messages -->
        <div class="messages-section">
            <div class="messages-header">
                <h3>
                    <i class="fas fa-envelope"></i>
                    Student Messages
                </h3>
            </div>
            
            <div class="message-list">
                <?php if ($messages->num_rows > 0): ?>
                    <?php while ($msg = $messages->fetch_assoc()): ?>
                        <div class="message-item <?php echo $msg['is_read'] == 0 ? 'unread' : ($msg['replied'] == 1 ? 'replied' : 'read'); ?>">
                            <div class="message-header">
                                <div class="message-info">
                                    <h4>
                                        <?php if ($msg['replied'] == 1): ?>
                                            <i class="fas fa-reply" style="color: var(--success); margin-right: 0.5rem;" title="Replied"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($msg['full_name']); ?>
                                    </h4>
                                    <div class="message-meta">
                                        <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($msg['email']); ?></span>
                                        <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($msg['phone']); ?></span>
                                        <span><i class="fas fa-university"></i> <?php echo htmlspecialchars($msg['university']); ?></span>
                                        <span><i class="fas fa-book"></i> <?php echo htmlspecialchars($msg['department']); ?></span>
                                        <?php if ($msg['internship_title']): ?>
                                            <span><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($msg['internship_title']); ?></span>
                                        <?php endif; ?>
                                        <span><i class="fas fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></span>
                                    </div>
                                </div>
                                <span class="status-badge status-<?php echo $msg['is_read'] == 0 ? 'unread' : ($msg['replied'] == 1 ? 'replied' : 'read'); ?>">
                                    <?php 
                                    if ($msg['is_read'] == 0) {
                                        echo 'Unread';
                                    } elseif ($msg['replied'] == 1) {
                                        echo 'Replied';
                                    } else {
                                        echo 'Read';
                                    }
                                    ?>
                                </span>
                            </div>
                            
                            <div class="message-content">
                                <div class="message-subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                                <div class="message-text"><?php echo htmlspecialchars($msg['message_content']); ?></div>
                            </div>
                            
                            <div class="message-actions">
                                <?php if ($msg['is_read'] == 0): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="message_id" value="<?php echo $msg['message_id']; ?>">
                                        <input type="hidden" name="action" value="read">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-eye"></i>
                                            Mark as Read
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if ($msg['replied'] == 0): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="message_id" value="<?php echo $msg['message_id']; ?>">
                                        <input type="hidden" name="action" value="replied">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-reply"></i>
                                            Mark as Replied
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <button class="btn btn-secondary" onclick="showReplyForm(<?php echo $msg['message_id']; ?>, '<?php echo htmlspecialchars($msg['email']); ?>', '<?php echo htmlspecialchars($msg['full_name']); ?>', '<?php echo htmlspecialchars($msg['subject']); ?>', '<?php echo htmlspecialchars(addslashes($msg['message_content'])); ?>')">
                                    <i class="fas fa-envelope"></i>
                                    Reply via Email
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-messages">
                        <i class="fas fa-inbox"></i>
                        <h3>No Messages Yet</h3>
                        <p>You haven't received any messages from students yet. Students will be able to contact you after they apply for your internships.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Reply Form Modal -->
    <div id="replyModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-reply"></i> Reply to Student</h3>
                <span class="close" onclick="closeReplyForm()">&times;</span>
            </div>
            <form method="POST" action="collaboration_request.php">
                <input type="hidden" name="message_id" id="reply_message_id">
                <input type="hidden" name="student_email" id="reply_student_email">
                <input type="hidden" name="student_name" id="reply_student_name">
                
                <div class="form-group">
                    <label>To:</label>
                    <input type="text" id="reply_to_display" readonly class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="reply_subject">Subject *</label>
                    <input type="text" name="reply_subject" id="reply_subject" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Original Message:</label>
                    <div id="original_message" class="original-message-display"></div>
                </div>
                
                <div class="form-group">
                    <label for="reply_message">Your Reply *</label>
                    <textarea name="reply_message" id="reply_message" class="form-control" rows="6" required placeholder="Write your reply message here..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeReplyForm()">Cancel</button>
                    <button type="submit" name="send_reply" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        Send Reply
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showReplyForm(messageId, studentEmail, studentName, originalSubject, originalMessage) {
            document.getElementById('reply_message_id').value = messageId;
            document.getElementById('reply_student_email').value = studentEmail;
            document.getElementById('reply_student_name').value = studentName;
            document.getElementById('reply_to_display').value = `${studentName} <${studentEmail}>`;
            document.getElementById('reply_subject').value = `Re: ${originalSubject}`;
            document.getElementById('original_message').textContent = originalMessage;
            document.getElementById('reply_message').value = '';
            document.getElementById('replyModal').style.display = 'block';
        }

        function closeReplyForm() {
            document.getElementById('replyModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('replyModal');
            if (event.target === modal) {
                closeReplyForm();
            }
        }

        // Auto-refresh page every 30 seconds to check for new messages
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
