<?php
session_start();
include "connection.php";

// Check if user is logged in as company
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['company_id'];
$success_message = "";
$error_message = "";

// Handle place status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $place_id = (int)$_POST['place_id'];
    
    // Verify the place belongs to this company
    $verify_stmt = $conn->prepare("SELECT place_id FROM places WHERE place_id = ? AND company_id = ?");
    $verify_stmt->bind_param("ii", $place_id, $company_id);
    $verify_stmt->execute();
    
    if ($verify_stmt->get_result()->num_rows > 0) {
        if ($_POST['action'] == 'update_status') {
            $new_status = $conn->real_escape_string($_POST['status']);
            $update_stmt = $conn->prepare("UPDATE places SET status = ? WHERE place_id = ?");
            $update_stmt->bind_param("si", $new_status, $place_id);
            
            if ($update_stmt->execute()) {
                $success_message = "Place status updated successfully!";
            } else {
                $error_message = "Error updating place status: " . $conn->error;
            }
        } elseif ($_POST['action'] == 'delete') {
            $delete_stmt = $conn->prepare("DELETE FROM places WHERE place_id = ?");
            $delete_stmt->bind_param("i", $place_id);
            
            if ($delete_stmt->execute()) {
                $success_message = "Place deleted successfully!";
            } else {
                $error_message = "Error deleting place: " . $conn->error;
            }
        }
    } else {
        $error_message = "Unauthorized action!";
    }
}

// Get company's places with booking and evaluation data
$places_sql = "SELECT 
    p.*,
    COUNT(pb.booking_id) as total_bookings,
    AVG(pe.rating) as average_rating,
    COUNT(pe.evaluation_id) as total_evaluations
    FROM places p
    LEFT JOIN place_bookings pb ON p.place_id = pb.place_id
    LEFT JOIN place_evaluations pe ON p.place_id = pe.place_id
    WHERE p.company_id = ?
    GROUP BY p.place_id
    ORDER BY p.created_at DESC";

$stmt = $conn->prepare($places_sql);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$places = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get detailed booking and evaluation information for each place
$place_details = [];
foreach ($places as $place) {
    $place_id = $place['place_id'];
    
    // Get bookings for this place
    $bookings_sql = "SELECT 
        pb.*,
        c.company_name,
        c.email as company_email,
        c.phone as company_phone
        FROM place_bookings pb
        JOIN companies c ON pb.company_id = c.company_id
        WHERE pb.place_id = ?
        ORDER BY pb.start_date DESC";
    
    $stmt = $conn->prepare($bookings_sql);
    $stmt->bind_param("i", $place_id);
    $stmt->execute();
    $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get evaluations for this place
    $evaluations_sql = "SELECT 
        pe.*,
        c.company_name,
        c.email as company_email
        FROM place_evaluations pe
        JOIN companies c ON pe.company_id = c.company_id
        WHERE pe.place_id = ?
        ORDER BY pe.created_at DESC";
    
    $stmt = $conn->prepare($evaluations_sql);
    $stmt->bind_param("i", $place_id);
    $stmt->execute();
    $evaluations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $place_details[$place_id] = [
        'place' => $place,
        'bookings' => $bookings,
        'evaluations' => $evaluations
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Places - Company Portal</title>
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
            --success: #4ade80;
            --error: #f87171;
            --warning: #fbbf24;
            --text-dark: #0f172a;
            --text-light: #475569;
            --text-white: #ffffff;
            --bg-primary: #ffffff;
            --bg-secondary: #f6f8fb;
            --border-light: #e5e7eb;
            --border-focus: #0ea5a8;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --radius-2xl: 1.5rem;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Inter", sans-serif;
            background: var(--bg-secondary);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: var(--muted);
        }

        .breadcrumb a {
            color: var(--brand);
            text-decoration: none;
            transition: var(--transition);
        }

        .breadcrumb a:hover {
            color: var(--brand-2);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
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
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--brand);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--muted);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            text-align: center;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            color: var(--text-white);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-dark);
            border: 2px solid var(--border-light);
        }

        .btn-secondary:hover {
            background: var(--brand);
            color: var(--text-white);
            border-color: var(--brand);
        }

        .btn-success {
            background: var(--success);
            color: var(--text-white);
        }

        .btn-warning {
            background: var(--warning);
            color: var(--text-white);
        }

        .btn-danger {
            background: var(--error);
            color: var(--text-white);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(74, 222, 128, 0.1);
            color: #059669;
            border: 1px solid rgba(74, 222, 128, 0.3);
        }

        .alert-error {
            background: rgba(248, 113, 113, 0.1);
            color: #dc2626;
            border: 1px solid rgba(248, 113, 113, 0.3);
        }

        .places-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .place-card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
            transition: var(--transition);
        }

        .place-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .place-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: var(--bg-secondary);
        }

        .place-content {
            padding: 1.5rem;
        }

        .place-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .place-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .place-type {
            color: var(--muted);
            font-size: 0.9rem;
            text-transform: capitalize;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-lg);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: rgba(74, 222, 128, 0.1);
            color: #059669;
            border: 1px solid rgba(74, 222, 128, 0.3);
        }

        .status-inactive {
            background: rgba(251, 191, 36, 0.1);
            color: #d97706;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }

        .status-maintenance {
            background: rgba(248, 113, 113, 0.1);
            color: #dc2626;
            border: 1px solid rgba(248, 113, 113, 0.3);
        }

        .place-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--muted);
        }

        .place-description {
            color: var(--text-dark);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .place-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
        }

        .stat {
            text-align: center;
        }

        .stat-value {
            font-weight: 700;
            color: var(--brand);
            font-size: 1.1rem;
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .place-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--panel);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--muted);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--muted);
            margin-bottom: 2rem;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: var(--panel);
            margin: 15% auto;
            padding: 2rem;
            border-radius: var(--radius-xl);
            width: 90%;
            max-width: 500px;
            box-shadow: var(--shadow-xl);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
        }

        .close {
            color: var(--muted);
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: var(--text-dark);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-size: 1rem;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .modal-body {
            max-height: 60vh;
            overflow-y: auto;
            padding: 1.5rem;
        }

        .btn-info {
            background: #3b82f6;
            color: white;
        }

        .btn-info:hover {
            background: #2563eb;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .places-grid {
                grid-template-columns: 1fr;
            }

            .place-actions {
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
                <span>Manage Places</span>
            </div>
            <h1 class="page-title">Manage Places</h1>
            <p class="page-subtitle">Manage your posted places and track bookings</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($places); ?></div>
                <div class="stat-label">Total Places</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($places, function($p) { return $p['status'] === 'active'; })); ?></div>
                <div class="stat-label">Active Places</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo array_sum(array_column($places, 'total_bookings')); ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo array_sum(array_column($places, 'confirmed_bookings')); ?></div>
                <div class="stat-label">Confirmed Bookings</div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div style="display: flex; gap: 1rem; margin-bottom: 2rem;">
            <a href="post_place.php" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Post New Place
            </a>
            <a href="browse_places.php" class="btn btn-secondary">
                <i class="fas fa-search"></i>
                Browse Places
            </a>
        </div>

        <!-- Places Grid -->
        <?php if (empty($places)): ?>
            <div class="empty-state">
                <i class="fas fa-building"></i>
                <h3>No Places Posted Yet</h3>
                <p>Start by posting your first place to make it available for booking by other companies.</p>
                <a href="post_place.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Post Your First Place
                </a>
            </div>
        <?php else: ?>
            <div class="places-grid">
                <?php foreach ($places as $place): ?>
                    <div class="place-card">
                        <?php 
                        $images = json_decode($place['images'], true);
                        $first_image = !empty($images) ? $images[0] : null;
                        ?>
                        <?php if ($first_image): ?>
                            <img src="<?php echo htmlspecialchars($first_image); ?>" alt="<?php echo htmlspecialchars($place['place_name']); ?>" class="place-image">
                        <?php else: ?>
                            <div class="place-image" style="display: flex; align-items: center; justify-content: center; color: var(--muted);">
                                <i class="fas fa-building" style="font-size: 3rem;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="place-content">
                            <div class="place-header">
                                <div>
                                    <h3 class="place-title"><?php echo htmlspecialchars($place['place_name']); ?></h3>
                                    <p class="place-type"><?php echo str_replace('_', ' ', $place['place_type']); ?></p>
                                </div>
                                <span class="status-badge status-<?php echo $place['status']; ?>">
                                    <?php echo ucfirst($place['status']); ?>
                                </span>
                            </div>

                            <div class="place-info">
                                <div class="info-item">
                                    <i class="fas fa-users"></i>
                                    <span><?php echo $place['capacity']; ?> people</span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($place['city']); ?></span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-dollar-sign"></i>
                                    <span>$<?php echo number_format($place['hourly_rate'], 2); ?>/hr</span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-star"></i>
                                    <span><?php echo $place['average_rating'] ? number_format($place['average_rating'], 1) : 'No ratings'; ?></span>
                                </div>
                            </div>

                            <p class="place-description"><?php echo htmlspecialchars(substr($place['description'], 0, 120)) . (strlen($place['description']) > 120 ? '...' : ''); ?></p>

                            <div class="place-stats">
                                <div class="stat">
                                    <div class="stat-value"><?php echo $place['total_bookings']; ?></div>
                                    <div class="stat-label">Bookings</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value"><?php echo $place['total_evaluations']; ?></div>
                                    <div class="stat-label">Evaluations</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value"><?php echo $place['average_rating'] ? number_format($place['average_rating'], 1) : '0.0'; ?></div>
                                    <div class="stat-label">Rating</div>
                                </div>
                            </div>

                            <div class="place-actions">
                                <a href="place_details.php?id=<?php echo $place['place_id']; ?>" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-eye"></i>
                                    View
                                </a>
                                <button class="btn btn-info btn-sm" onclick="openBookingsModal(<?php echo $place['place_id']; ?>)">
                                    <i class="fas fa-calendar"></i>
                                    Bookings (<?php echo $place['total_bookings']; ?>)
                                </button>
                                <button class="btn btn-success btn-sm" onclick="openEvaluationsModal(<?php echo $place['place_id']; ?>)">
                                    <i class="fas fa-star"></i>
                                    Reviews (<?php echo $place['total_evaluations']; ?>)
                                </button>
                                <button class="btn btn-warning btn-sm" onclick="openStatusModal(<?php echo $place['place_id']; ?>, '<?php echo $place['status']; ?>')">
                                    <i class="fas fa-edit"></i>
                                    Status
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $place['place_id']; ?>, '<?php echo htmlspecialchars($place['place_name']); ?>')">
                                    <i class="fas fa-trash"></i>
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Place Status</h3>
                <span class="close" onclick="closeModal('statusModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="place_id" id="status_place_id">
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="status_select" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bookings Modal -->
    <div id="bookingsModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3 class="modal-title">Place Bookings</h3>
                <span class="close" onclick="closeModal('bookingsModal')">&times;</span>
            </div>
            <div id="bookingsContent" class="modal-body">
                <!-- Bookings will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Evaluations Modal -->
    <div id="evaluationsModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3 class="modal-title">Place Evaluations</h3>
                <span class="close" onclick="closeModal('evaluationsModal')">&times;</span>
            </div>
            <div id="evaluationsContent" class="modal-body">
                <!-- Evaluations will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Delete</h3>
                <span class="close" onclick="closeModal('deleteModal')">&times;</span>
            </div>
            <p>Are you sure you want to delete the place "<span id="delete_place_name"></span>"? This action cannot be undone.</p>
            <form method="POST" style="margin-top: 1.5rem;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="place_id" id="delete_place_id">
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Place</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Place data for JavaScript access
        const placeDetails = <?php echo json_encode($place_details); ?>;

        function openStatusModal(placeId, currentStatus) {
            document.getElementById('status_place_id').value = placeId;
            document.getElementById('status_select').value = currentStatus;
            document.getElementById('statusModal').style.display = 'block';
        }

        function confirmDelete(placeId, placeName) {
            document.getElementById('delete_place_id').value = placeId;
            document.getElementById('delete_place_name').textContent = placeName;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function openBookingsModal(placeId) {
            const placeData = placeDetails[placeId];
            if (!placeData) return;

            const bookings = placeData.bookings;
            const place = placeData.place;
            
            let bookingsHtml = `<h4>${place.place_name} - Bookings</h4>`;
            
            if (bookings.length === 0) {
                bookingsHtml += '<p>No bookings found for this place.</p>';
            } else {
                bookingsHtml += '<div class="bookings-list">';
                bookings.forEach(booking => {
                    const startDate = new Date(booking.start_date).toLocaleDateString();
                    const endDate = booking.end_date ? new Date(booking.end_date).toLocaleDateString() : 'N/A';
                    const startTime = booking.start_time || 'N/A';
                    const endTime = booking.end_time || 'N/A';
                    
                    bookingsHtml += `
                        <div class="booking-item" style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                <h5 style="margin: 0; color: var(--ink);">${booking.company_name}</h5>
                                <span style="background: var(--brand); color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">
                                    ${booking.booking_type.toUpperCase()}
                                </span>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 0.5rem;">
                                <div><strong>Period:</strong> ${startDate}${endDate !== startDate ? ' - ' + endDate : ''}</div>
                                <div><strong>Time:</strong> ${startTime} - ${endTime}</div>
                                <div><strong>Cost:</strong> $${parseFloat(booking.total_cost).toFixed(2)}</div>
                                <div><strong>Contact:</strong> ${booking.contact_person}</div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div><strong>Email:</strong> ${booking.contact_email}</div>
                                <div><strong>Phone:</strong> ${booking.contact_phone}</div>
                            </div>
                            ${booking.special_requirements ? `<div style="margin-top: 0.5rem;"><strong>Special Requirements:</strong> ${booking.special_requirements}</div>` : ''}
                            ${booking.booking_notes ? `<div style="margin-top: 0.5rem;"><strong>Notes:</strong> ${booking.booking_notes}</div>` : ''}
                        </div>
                    `;
                });
                bookingsHtml += '</div>';
            }
            
            document.getElementById('bookingsContent').innerHTML = bookingsHtml;
            document.getElementById('bookingsModal').style.display = 'block';
        }

        function openEvaluationsModal(placeId) {
            const placeData = placeDetails[placeId];
            if (!placeData) return;

            const evaluations = placeData.evaluations;
            const place = placeData.place;
            
            let evaluationsHtml = `<h4>${place.place_name} - Evaluations</h4>`;
            
            if (evaluations.length === 0) {
                evaluationsHtml += '<p>No evaluations found for this place.</p>';
            } else {
                evaluationsHtml += '<div class="evaluations-list">';
                evaluations.forEach(evaluation => {
                    const rating = evaluation.rating;
                    const stars = '★'.repeat(rating) + '☆'.repeat(5 - rating);
                    const date = new Date(evaluation.created_at).toLocaleDateString();
                    
                    evaluationsHtml += `
                        <div class="evaluation-item" style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                <h5 style="margin: 0; color: var(--ink);">${evaluation.company_name}</h5>
                                <div style="text-align: right;">
                                    <div style="color: #fbbf24; font-size: 1.2rem;">${stars}</div>
                                    <div style="font-size: 0.8rem; color: var(--muted);">${date}</div>
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; background: #f8fafc; padding: 0.75rem; border-radius: 6px;">
                                <div><strong>Location Quality:</strong> ${evaluation.location_quality}/5</div>
                                <div><strong>Cleanliness:</strong> ${evaluation.cleanliness}/5</div>
                                <div><strong>Amenities:</strong> ${evaluation.amenities}/5</div>
                                <div><strong>Value for Money:</strong> ${evaluation.value_for_money}/5</div>
                            </div>
                            
                            ${evaluation.evaluation_text ? `<div style="margin-bottom: 0.5rem;"><strong>Feedback:</strong><br><em>"${evaluation.evaluation_text}"</em></div>` : ''}
                            
                            <div style="text-align: center;">
                                <span style="background: ${evaluation.would_recommend ? 'var(--success)' : 'var(--error)'}; color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.8rem;">
                                    ${evaluation.would_recommend ? '✅ Recommended' : '❌ Not Recommended'}
                                </span>
                            </div>
                        </div>
                    `;
                });
                evaluationsHtml += '</div>';
            }
            
            document.getElementById('evaluationsContent').innerHTML = evaluationsHtml;
            document.getElementById('evaluationsModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const statusModal = document.getElementById('statusModal');
            const deleteModal = document.getElementById('deleteModal');
            const bookingsModal = document.getElementById('bookingsModal');
            const evaluationsModal = document.getElementById('evaluationsModal');
            
            if (event.target === statusModal) {
                statusModal.style.display = 'none';
            }
            if (event.target === deleteModal) {
                deleteModal.style.display = 'none';
            }
            if (event.target === bookingsModal) {
                bookingsModal.style.display = 'none';
            }
            if (event.target === evaluationsModal) {
                evaluationsModal.style.display = 'none';
            }
        }
    </script>
</body>
</html>


