<?php
session_start();
include "connection.php";

// Check if user is logged in as company
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['company_id'];
$place_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$place_id) {
    header("Location: browse_places.php");
    exit();
}

// Get place details
$place_sql = "SELECT 
    p.*,
    c.company_name,
    c.logo_path
    FROM places p
    JOIN companies c ON p.company_id = c.company_id
    WHERE p.place_id = ? AND p.status = 'active'";

$stmt = $conn->prepare($place_sql);
$stmt->bind_param("i", $place_id);
$stmt->execute();
$place = $stmt->get_result()->fetch_assoc();

if (!$place) {
    header("Location: browse_places.php");
    exit();
}

// Check if this is the user's own place
if ($place['company_id'] == $company_id) {
    header("Location: place_details.php?id=" . $place_id);
    exit();
}

$success_message = "";
$error_message = "";

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $booking_type = $conn->real_escape_string($_POST['booking_type']);
        $start_date = $conn->real_escape_string($_POST['start_date']);
        $end_date = $conn->real_escape_string($_POST['end_date']);
        $start_time = $conn->real_escape_string($_POST['start_time']);
        $end_time = $conn->real_escape_string($_POST['end_time']);
        $contact_person = $conn->real_escape_string($_POST['contact_person']);
        $contact_email = $conn->real_escape_string($_POST['contact_email']);
        $contact_phone = $conn->real_escape_string($_POST['contact_phone']);
        $special_requirements = $conn->real_escape_string($_POST['special_requirements']);
        $booking_notes = $conn->real_escape_string($_POST['booking_notes']);
        
        // Calculate total cost based on booking type
        $total_cost = 0;
        switch ($booking_type) {
            case 'hourly':
                $total_cost = $place['hourly_rate'];
                break;
            case 'daily':
                $total_cost = $place['daily_rate'];
                break;
            case 'weekly':
                $total_cost = $place['weekly_rate'];
                break;
            case 'monthly':
                $total_cost = $place['monthly_rate'];
                break;
        }
        
        // Insert booking
        $booking_stmt = $conn->prepare("INSERT INTO place_bookings (place_id, company_id, booking_type, start_date, end_date, start_time, end_time, total_cost, contact_person, contact_email, contact_phone, special_requirements, booking_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $booking_stmt->bind_param("iisssssdsssss", $place_id, $company_id, $booking_type, $start_date, $end_date, $start_time, $end_time, $total_cost, $contact_person, $contact_email, $contact_phone, $special_requirements, $booking_notes);
        
        if ($booking_stmt->execute()) {
            $success_message = "Booking request submitted successfully! The place owner will review and confirm your booking.";
        } else {
            $error_message = "Error submitting booking: " . $conn->error;
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Place - <?php echo htmlspecialchars($place['place_name']); ?></title>
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
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
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

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        .place-summary {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
            height: fit-content;
        }

        .place-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            background: var(--bg-secondary);
        }

        .place-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .place-type {
            color: var(--muted);
            font-size: 1rem;
            margin-bottom: 1rem;
            text-transform: capitalize;
        }

        .place-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--muted);
        }

        .pricing-info {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .pricing-title {
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 1rem;
        }

        .price-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .price-label {
            color: var(--muted);
        }

        .price-value {
            font-weight: 600;
            color: var(--brand);
        }

        .booking-form {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--line);
        }

        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .form-label.required::after {
            content: " *";
            color: var(--error);
        }

        .form-input, .form-select, .form-textarea {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--bg-primary);
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
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

        .cost-summary {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .cost-title {
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 1rem;
        }

        .cost-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .total-cost {
            border-top: 2px solid var(--line);
            padding-top: 1rem;
            margin-top: 1rem;
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--brand);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="breadcrumb">
            <a href="company_dashboard.php">Company Portal</a>
            <i class="fas fa-chevron-right"></i>
            <a href="browse_places.php">Browse Places</a>
            <i class="fas fa-chevron-right"></i>
            <a href="place_details.php?id=<?php echo $place_id; ?>"><?php echo htmlspecialchars($place['place_name']); ?></a>
            <i class="fas fa-chevron-right"></i>
            <span>Book Place</span>
        </div>

        <h1 class="page-title">Book This Place</h1>
        <p class="page-subtitle">Submit a booking request for <?php echo htmlspecialchars($place['place_name']); ?></p>

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

        <div class="content-grid">
            <div class="place-summary">
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
                
                <h2 class="place-name"><?php echo htmlspecialchars($place['place_name']); ?></h2>
                <p class="place-type"><?php echo str_replace('_', ' ', $place['place_type']); ?></p>
                
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
                        <i class="fas fa-building"></i>
                        <span><?php echo htmlspecialchars($place['company_name']); ?></span>
                    </div>
                </div>

                <div class="pricing-info">
                    <h3 class="pricing-title">Pricing</h3>
                    <?php if ($place['hourly_rate'] > 0): ?>
                        <div class="price-item">
                            <span class="price-label">Hourly Rate</span>
                            <span class="price-value">$<?php echo number_format($place['hourly_rate'], 2); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($place['daily_rate'] > 0): ?>
                        <div class="price-item">
                            <span class="price-label">Daily Rate</span>
                            <span class="price-value">$<?php echo number_format($place['daily_rate'], 2); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($place['weekly_rate'] > 0): ?>
                        <div class="price-item">
                            <span class="price-label">Weekly Rate</span>
                            <span class="price-value">$<?php echo number_format($place['weekly_rate'], 2); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($place['monthly_rate'] > 0): ?>
                        <div class="price-item">
                            <span class="price-label">Monthly Rate</span>
                            <span class="price-value">$<?php echo number_format($place['monthly_rate'], 2); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="booking-form">
                <form method="POST" id="bookingForm">
                    <!-- Booking Details -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-calendar-alt"></i>
                            Booking Details
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label required">Booking Type</label>
                                <select name="booking_type" class="form-select" required onchange="updateCost()">
                                    <option value="">Select Type</option>
                                    <?php if ($place['hourly_rate'] > 0): ?>
                                        <option value="hourly" data-rate="<?php echo $place['hourly_rate']; ?>">Hourly</option>
                                    <?php endif; ?>
                                    <?php if ($place['daily_rate'] > 0): ?>
                                        <option value="daily" data-rate="<?php echo $place['daily_rate']; ?>">Daily</option>
                                    <?php endif; ?>
                                    <?php if ($place['weekly_rate'] > 0): ?>
                                        <option value="weekly" data-rate="<?php echo $place['weekly_rate']; ?>">Weekly</option>
                                    <?php endif; ?>
                                    <?php if ($place['monthly_rate'] > 0): ?>
                                        <option value="monthly" data-rate="<?php echo $place['monthly_rate']; ?>">Monthly</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label required">Start Date</label>
                                <input type="date" name="start_date" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label required">End Date</label>
                                <input type="date" name="end_date" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label required">Start Time</label>
                                <input type="time" name="start_time" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label required">End Time</label>
                                <input type="time" name="end_time" class="form-input" required>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-user"></i>
                            Contact Information
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label required">Contact Person</label>
                                <input type="text" name="contact_person" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label required">Email Address</label>
                                <input type="email" name="contact_email" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="contact_phone" class="form-input">
                            </div>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Additional Information
                        </h3>
                        <div class="form-group full-width">
                            <label class="form-label">Special Requirements</label>
                            <textarea name="special_requirements" class="form-textarea" placeholder="Any special requirements or requests..."></textarea>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">Booking Notes</label>
                            <textarea name="booking_notes" class="form-textarea" placeholder="Additional notes for the place owner..."></textarea>
                        </div>
                    </div>

                    <!-- Cost Summary -->
                    <div class="cost-summary">
                        <h3 class="cost-title">Cost Summary</h3>
                        <div class="cost-item">
                            <span>Base Rate</span>
                            <span id="base-cost">$0.00</span>
                        </div>
                        <div class="cost-item total-cost">
                            <span>Total Cost</span>
                            <span id="total-cost">$0.00</span>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <a href="place_details.php?id=<?php echo $place_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Back to Details
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i>
                            Submit Booking Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function updateCost() {
            const bookingType = document.querySelector('select[name="booking_type"]');
            const baseCostElement = document.getElementById('base-cost');
            const totalCostElement = document.getElementById('total-cost');
            
            if (bookingType.value) {
                const selectedOption = bookingType.options[bookingType.selectedIndex];
                const rate = parseFloat(selectedOption.getAttribute('data-rate'));
                baseCostElement.textContent = '$' + rate.toFixed(2);
                totalCostElement.textContent = '$' + rate.toFixed(2);
            } else {
                baseCostElement.textContent = '$0.00';
                totalCostElement.textContent = '$0.00';
            }
        }

        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('input[name="start_date"]').min = today;
        document.querySelector('input[name="end_date"]').min = today;

        // Update end date minimum when start date changes
        document.querySelector('input[name="start_date"]').addEventListener('change', function() {
            document.querySelector('input[name="end_date"]').min = this.value;
        });
    </script>
</body>
</html>




