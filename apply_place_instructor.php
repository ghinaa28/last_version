<?php
session_start();
include "connection.php";

// Check if user is logged in as instructor
if (!isset($_SESSION['instructor_id'])) {
    header("Location: login.php");
    exit();
}

$instructor_id = $_SESSION['instructor_id'];

// Get instructor information
$stmt = $conn->prepare("SELECT * FROM instructors WHERE instructor_id = ?");
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$instructor = $stmt->get_result()->fetch_assoc();

if (!$instructor) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Get place ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: browse_places_instructor.php");
    exit();
}

$place_id = intval($_GET['id']);

// Get place information
$place_sql = "SELECT 
    p.*,
    c.company_name,
    c.logo_path,
    c.email as company_email,
    c.phone as company_phone,
    AVG(pr.rating) as average_rating,
    COUNT(pr.review_id) as total_reviews
    FROM places p
    JOIN companies c ON p.company_id = c.company_id
    LEFT JOIN place_reviews pr ON p.place_id = pr.place_id
    WHERE p.place_id = ? AND p.status = 'active'
    GROUP BY p.place_id";

$stmt = $conn->prepare($place_sql);
$stmt->bind_param("i", $place_id);
$stmt->execute();
$place = $stmt->get_result()->fetch_assoc();

if (!$place) {
    header("Location: browse_places_instructor.php");
    exit();
}

$success_message = "";
$error_message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $application_message = trim($_POST['application_message']);
    $proposed_start_date = $_POST['proposed_start_date'];
    $proposed_end_date = $_POST['proposed_end_date'];
    $proposed_hours = intval($_POST['proposed_hours']);
    $proposed_rate = floatval($_POST['proposed_rate']);
    $additional_requirements = trim($_POST['additional_requirements']);
    $contact_preference = $_POST['contact_preference'];
    $availability = trim($_POST['availability']);

    // Validate required fields
    if (empty($application_message) || empty($proposed_start_date) || empty($proposed_end_date)) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Create instructor_place_applications table if it doesn't exist
        $create_table_sql = "CREATE TABLE IF NOT EXISTS instructor_place_applications (
            application_id INT AUTO_INCREMENT PRIMARY KEY,
            instructor_id INT NOT NULL,
            place_id INT NOT NULL,
            application_message TEXT NOT NULL,
            proposed_start_date DATE NOT NULL,
            proposed_end_date DATE NOT NULL,
            proposed_hours INT DEFAULT NULL,
            proposed_rate DECIMAL(10,2) DEFAULT NULL,
            additional_requirements TEXT DEFAULT NULL,
            contact_preference ENUM('email', 'phone', 'both') DEFAULT 'email',
            availability TEXT DEFAULT NULL,
            status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            reviewed_at TIMESTAMP NULL DEFAULT NULL,
            review_notes TEXT DEFAULT NULL,
            FOREIGN KEY (instructor_id) REFERENCES instructors(instructor_id) ON DELETE CASCADE,
            FOREIGN KEY (place_id) REFERENCES places(place_id) ON DELETE CASCADE,
            UNIQUE KEY unique_application (instructor_id, place_id),
            INDEX idx_instructor_id (instructor_id),
            INDEX idx_place_id (place_id),
            INDEX idx_status (status)
        )";
        
        $conn->query($create_table_sql);

        // Check if instructor has already applied for this place
        $check_sql = "SELECT application_id FROM instructor_place_applications WHERE instructor_id = ? AND place_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ii", $instructor_id, $place_id);
        $stmt->execute();
        $existing_application = $stmt->get_result()->fetch_assoc();

        if ($existing_application) {
            $error_message = "You have already applied for this place.";
        } else {
            // Insert application
            $insert_sql = "INSERT INTO instructor_place_applications (instructor_id, place_id, application_message, proposed_start_date, proposed_end_date, proposed_hours, proposed_rate, additional_requirements, contact_preference, availability) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("iisssiisss", 
                $instructor_id, 
                $place_id, 
                $application_message, 
                $proposed_start_date, 
                $proposed_end_date, 
                $proposed_hours, 
                $proposed_rate, 
                $additional_requirements, 
                $contact_preference, 
                $availability
            );

            if ($stmt->execute()) {
                $success_message = "Your application has been submitted successfully!";
            } else {
                $error_message = "Error submitting application: " . $conn->error;
            }
        }
    }
}

// Parse JSON data
$amenities = json_decode($place['amenities'], true) ?: [];
$images = json_decode($place['images'], true) ?: [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Place - Instructor Portal</title>
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

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .place-info {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
            height: fit-content;
        }

        .place-header {
            margin-bottom: 1.5rem;
        }

        .place-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .place-type {
            color: var(--muted);
            font-size: 0.9rem;
            text-transform: capitalize;
            margin-bottom: 1rem;
        }

        .company-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
        }

        .company-logo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }

        .company-details h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .company-details p {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .place-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .place-description {
            color: var(--text-dark);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .amenities-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .amenity-tag {
            background: rgba(14, 165, 168, 0.1);
            color: var(--brand);
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .application-form {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
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

        .form-input,
        .form-select,
        .form-textarea {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--bg-primary);
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--line);
        }

        .pricing-info {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
        }

        .pricing-title {
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .pricing-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .price-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--brand);
        }

        .price-label {
            color: var(--muted);
            font-size: 0.9rem;
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

            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="instructor_dashboard.php">Instructor Portal</a>
                <i class="fas fa-chevron-right"></i>
                <a href="browse_places_instructor.php">Browse Places</a>
                <i class="fas fa-chevron-right"></i>
                <span>Apply for Place</span>
            </div>
            <h1 class="page-title">Apply for Place</h1>
            <p class="page-subtitle">Submit your application to use this place</p>
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

        <div class="content-grid">
            <div class="place-info">
                <div class="place-header">
                    <h2 class="place-title"><?php echo htmlspecialchars($place['place_name']); ?></h2>
                    <p class="place-type"><?php echo str_replace('_', ' ', ucwords($place['place_type'])); ?></p>
                </div>

                <div class="company-info">
                    <?php if ($place['logo_path']): ?>
                        <img src="<?php echo htmlspecialchars($place['logo_path']); ?>" alt="Company Logo" class="company-logo">
                    <?php else: ?>
                        <div class="company-logo" style="background: var(--brand); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.2rem;">
                            <?php echo strtoupper(substr($place['company_name'], 0, 2)); ?>
                        </div>
                    <?php endif; ?>
                    <div class="company-details">
                        <h4><?php echo htmlspecialchars($place['company_name']); ?></h4>
                        <p><?php echo htmlspecialchars($place['company_email']); ?></p>
                        <?php if ($place['company_phone']): ?>
                            <p><?php echo htmlspecialchars($place['company_phone']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="place-details">
                    <div class="detail-item">
                        <i class="fas fa-users"></i>
                        <span><?php echo $place['capacity']; ?> people</span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo htmlspecialchars($place['city']); ?></span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-star"></i>
                        <span><?php echo $place['average_rating'] ? number_format($place['average_rating'], 1) : 'No ratings'; ?></span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo $place['total_reviews']; ?> reviews</span>
                    </div>
                </div>

                <div class="pricing-info">
                    <div class="pricing-title">Pricing</div>
                    <div class="pricing-details">
                        <div>
                            <div class="price-value">
                                $<?php echo number_format($place['space_type'] === 'short_term' ? $place['hourly_rate'] : $place['weekly_rate'], 2); ?>
                            </div>
                            <div class="price-label">
                                per <?php echo $place['space_type'] === 'short_term' ? 'hour' : 'week'; ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 0.9rem; color: var(--muted);">
                                <?php echo $place['space_type'] === 'short_term' ? 'Short-term' : 'Long-term'; ?> rental
                            </div>
                        </div>
                    </div>
                </div>

                <div class="place-description">
                    <?php echo htmlspecialchars($place['description']); ?>
                </div>

                <?php if (!empty($amenities)): ?>
                    <div>
                        <h4 style="font-weight: 600; color: var(--ink); margin-bottom: 0.5rem;">Amenities</h4>
                        <div class="amenities-list">
                            <?php foreach ($amenities as $amenity): ?>
                                <span class="amenity-tag"><?php echo ucwords(str_replace('_', ' ', $amenity)); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="application-form">
                <h3 style="font-size: 1.5rem; font-weight: 700; color: var(--ink); margin-bottom: 1.5rem;">Application Form</h3>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label required" for="application_message">Application Message</label>
                        <textarea id="application_message" name="application_message" class="form-textarea" 
                                  placeholder="Tell the company why you want to use this place and how you plan to use it" required><?php echo isset($_POST['application_message']) ? htmlspecialchars($_POST['application_message']) : ''; ?></textarea>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required" for="proposed_start_date">Proposed Start Date</label>
                            <input type="date" id="proposed_start_date" name="proposed_start_date" class="form-input" 
                                   value="<?php echo isset($_POST['proposed_start_date']) ? htmlspecialchars($_POST['proposed_start_date']) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label required" for="proposed_end_date">Proposed End Date</label>
                            <input type="date" id="proposed_end_date" name="proposed_end_date" class="form-input" 
                                   value="<?php echo isset($_POST['proposed_end_date']) ? htmlspecialchars($_POST['proposed_end_date']) : ''; ?>" required>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="proposed_hours">Proposed Hours (per week)</label>
                            <input type="number" id="proposed_hours" name="proposed_hours" class="form-input" 
                                   value="<?php echo isset($_POST['proposed_hours']) ? htmlspecialchars($_POST['proposed_hours']) : ''; ?>" 
                                   min="1" max="168" placeholder="e.g., 20">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="proposed_rate">Proposed Rate ($)</label>
                            <input type="number" id="proposed_rate" name="proposed_rate" class="form-input" 
                                   value="<?php echo isset($_POST['proposed_rate']) ? htmlspecialchars($_POST['proposed_rate']) : ''; ?>" 
                                   min="0" step="0.01" placeholder="e.g., 25.00">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="additional_requirements">Additional Requirements</label>
                        <textarea id="additional_requirements" name="additional_requirements" class="form-textarea" 
                                  placeholder="Any special requirements or requests you have for using this place"><?php echo isset($_POST['additional_requirements']) ? htmlspecialchars($_POST['additional_requirements']) : ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="contact_preference">Preferred Contact Method</label>
                        <select id="contact_preference" name="contact_preference" class="form-select">
                            <option value="email" <?php echo (isset($_POST['contact_preference']) && $_POST['contact_preference'] == 'email') ? 'selected' : ''; ?>>Email</option>
                            <option value="phone" <?php echo (isset($_POST['contact_preference']) && $_POST['contact_preference'] == 'phone') ? 'selected' : ''; ?>>Phone</option>
                            <option value="both" <?php echo (isset($_POST['contact_preference']) && $_POST['contact_preference'] == 'both') ? 'selected' : ''; ?>>Both</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="availability">Your Availability</label>
                        <textarea id="availability" name="availability" class="form-textarea" 
                                  placeholder="Describe your availability and preferred times for using this place"><?php echo isset($_POST['availability']) ? htmlspecialchars($_POST['availability']) : ''; ?></textarea>
                    </div>

                    <div class="form-actions">
                        <a href="browse_places_instructor.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            Submit Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('proposed_start_date').setAttribute('min', today);
        document.getElementById('proposed_end_date').setAttribute('min', today);

        // Ensure end date is after start date
        document.getElementById('proposed_start_date').addEventListener('change', function() {
            const startDate = this.value;
            const endDateInput = document.getElementById('proposed_end_date');
            if (startDate) {
                endDateInput.setAttribute('min', startDate);
            }
        });
    </script>
</body>
</html>
