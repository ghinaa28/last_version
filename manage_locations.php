<?php
session_start();
include "connection.php";

// Check if user is logged in as company
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['company_id'];
$success = "";
$error = "";

// Check for error messages from redirects
if (isset($_GET['error']) && $_GET['error'] === 'no_primary_location') {
    $error = "You need to set up a primary location before posting places. Please add your primary location below.";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_location':
                $location_name = $conn->real_escape_string($_POST['location_name']);
                $location_type = $conn->real_escape_string($_POST['location_type']);
                $address = $conn->real_escape_string($_POST['address']);
                $city = $conn->real_escape_string($_POST['city']);
                $country = $conn->real_escape_string($_POST['country']);
                $postal_code = $conn->real_escape_string($_POST['postal_code']);
                $phone = $conn->real_escape_string($_POST['phone']);
                $email = $conn->real_escape_string($_POST['email']);
                $is_primary = isset($_POST['is_primary']) ? 1 : 0;

                $sql = "INSERT INTO company_locations (company_id, location_name, location_type, address, city, country, postal_code, phone, email, is_primary, status, created_at)
                        VALUES ('$company_id', '$location_name', '$location_type', '$address', '$city', '$country', '$postal_code', '$phone', '$email', '$is_primary', 'active', NOW())";

                if ($conn->query($sql)) {
                    $success = "Location added successfully!";
                } else {
                    $error = "Error adding location: " . $conn->error;
                }
                break;

            case 'update_location':
                $location_id = (int)$_POST['location_id'];
                $location_name = $conn->real_escape_string($_POST['location_name']);
                $location_type = $conn->real_escape_string($_POST['location_type']);
                $address = $conn->real_escape_string($_POST['address']);
                $city = $conn->real_escape_string($_POST['city']);
                $country = $conn->real_escape_string($_POST['country']);
                $postal_code = $conn->real_escape_string($_POST['postal_code']);
                $phone = $conn->real_escape_string($_POST['phone']);
                $email = $conn->real_escape_string($_POST['email']);
                $is_primary = isset($_POST['is_primary']) ? 1 : 0;

                $sql = "UPDATE company_locations SET 
                        location_name = '$location_name',
                        location_type = '$location_type',
                        address = '$address',
                        city = '$city',
                        country = '$country',
                        postal_code = '$postal_code',
                        phone = '$phone',
                        email = '$email',
                        is_primary = '$is_primary',
                        updated_at = NOW()
                        WHERE location_id = '$location_id' AND company_id = '$company_id'";

                if ($conn->query($sql)) {
                    $success = "Location updated successfully!";
                } else {
                    $error = "Error updating location: " . $conn->error;
                }
                break;

            case 'delete_location':
                $location_id = (int)$_POST['location_id'];
                
                // Check if this is the primary location
                $check_sql = "SELECT is_primary FROM company_locations WHERE location_id = '$location_id' AND company_id = '$company_id'";
                $result = $conn->query($check_sql);
                if ($result && $result->num_rows > 0) {
                    $location = $result->fetch_assoc();
                    if ($location['is_primary']) {
                        $error = "Cannot delete primary location. Please set another location as primary first.";
                    } else {
                        $sql = "DELETE FROM company_locations WHERE location_id = '$location_id' AND company_id = '$company_id'";
                        if ($conn->query($sql)) {
                            $success = "Location deleted successfully!";
                        } else {
                            $error = "Error deleting location: " . $conn->error;
                        }
                    }
                } else {
                    $error = "Location not found.";
                }
                break;
        }
    }
}

// Get company locations
$locations_sql = "SELECT * FROM company_locations WHERE company_id = '$company_id' ORDER BY is_primary DESC, created_at ASC";
$locations_result = $conn->query($locations_sql);
$locations = [];
if ($locations_result && $locations_result->num_rows > 0) {
    while ($row = $locations_result->fetch_assoc()) {
        $locations[] = $row;
    }
}

// Get company info
$company_sql = "SELECT company_name FROM companies WHERE company_id = '$company_id'";
$company_result = $conn->query($company_sql);
$company = $company_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Locations - Company Portal</title>
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

        .btn-danger {
            background: var(--error);
            color: var(--text-white);
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .locations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .location-card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
            position: relative;
        }

        .location-card.primary {
            border: 2px solid var(--brand);
            background: linear-gradient(135deg, rgba(14, 165, 168, 0.05), rgba(34, 211, 238, 0.05));
        }

        .primary-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--brand);
            color: var(--text-white);
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-lg);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .location-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .location-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .location-type {
            background: var(--bg-secondary);
            color: var(--muted);
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .location-details {
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .detail-item i {
            color: var(--muted);
            margin-top: 0.125rem;
            width: 16px;
        }

        .location-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .add-location-section {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .form-input, .form-select, .form-textarea {
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--bg-primary);
            color: var(--text-dark);
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--brand);
        }

        .message {
            padding: 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            font-weight: 500;
            text-align: center;
            animation: slideInDown 0.4s ease;
        }

        .message.success {
            background: rgba(74, 222, 128, 0.1);
            color: #059669;
            border: 1px solid rgba(74, 222, 128, 0.3);
        }

        .message.error {
            background: rgba(248, 113, 113, 0.1);
            color: #dc2626;
            border: 1px solid rgba(248, 113, 113, 0.3);
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--border-light);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .locations-grid {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .location-actions {
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
                <span>Manage Locations</span>
            </div>
            <h1 class="page-title">Manage Locations</h1>
            <p class="page-subtitle">Manage your company locations and set primary location</p>
        </div>

        <?php if ($success): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Add New Location Form -->
        <div class="add-location-section">
            <h3 class="section-title">
                <i class="fas fa-plus-circle"></i>
                Add New Location
            </h3>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_location">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Location Name</label>
                        <input type="text" name="location_name" class="form-input" placeholder="e.g., Main Office, Branch Office" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Location Type</label>
                        <select name="location_type" class="form-select" required>
                            <option value="">Select location type</option>
                            <option value="head_office">Head Office</option>
                            <option value="branch">Branch</option>
                            <option value="training_center">Training Center</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-textarea" placeholder="Enter complete address" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-input" placeholder="Enter city" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" class="form-input" placeholder="Enter country" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Postal Code</label>
                        <input type="text" name="postal_code" class="form-input" placeholder="Enter postal code">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-input" placeholder="Enter phone number">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" placeholder="Enter email address">
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="is_primary" id="is_primary">
                    <label for="is_primary">Set as primary location</label>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add Location
                </button>
            </form>
        </div>

        <!-- Locations List -->
        <div class="locations-grid">
            <?php if (empty($locations)): ?>
                <div class="empty-state">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>No locations found</h3>
                    <p>Add your first company location using the form above.</p>
                </div>
            <?php else: ?>
                <?php foreach ($locations as $location): ?>
                    <div class="location-card <?php echo $location['is_primary'] ? 'primary' : ''; ?>">
                        <?php if ($location['is_primary']): ?>
                            <div class="primary-badge">Primary</div>
                        <?php endif; ?>
                        
                        <div class="location-header">
                            <div>
                                <h4 class="location-name"><?php echo htmlspecialchars($location['location_name']); ?></h4>
                                <span class="location-type"><?php echo str_replace('_', ' ', $location['location_type']); ?></span>
                            </div>
                        </div>
                        
                        <div class="location-details">
                            <div class="detail-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($location['address']); ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-city"></i>
                                <span><?php echo htmlspecialchars($location['city'] . ', ' . $location['country']); ?></span>
                            </div>
                            <?php if ($location['postal_code']): ?>
                                <div class="detail-item">
                                    <i class="fas fa-mail-bulk"></i>
                                    <span><?php echo htmlspecialchars($location['postal_code']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($location['phone']): ?>
                                <div class="detail-item">
                                    <i class="fas fa-phone"></i>
                                    <span><?php echo htmlspecialchars($location['phone']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($location['email']): ?>
                                <div class="detail-item">
                                    <i class="fas fa-envelope"></i>
                                    <span><?php echo htmlspecialchars($location['email']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="location-actions">
                            <button class="btn btn-secondary btn-sm" onclick="editLocation(<?php echo $location['location_id']; ?>)">
                                <i class="fas fa-edit"></i>
                                Edit
                            </button>
                            <?php if (!$location['is_primary']): ?>
                                <button class="btn btn-danger btn-sm" onclick="deleteLocation(<?php echo $location['location_id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                    Delete
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Location Modal -->
    <div id="editModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Location</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form id="editForm" method="POST" action="">
                <input type="hidden" name="action" value="update_location">
                <input type="hidden" name="location_id" id="edit_location_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Location Name</label>
                        <input type="text" name="location_name" id="edit_location_name" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Location Type</label>
                        <select name="location_type" id="edit_location_type" class="form-select" required>
                            <option value="head_office">Head Office</option>
                            <option value="branch">Branch</option>
                            <option value="training_center">Training Center</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="edit_address" class="form-textarea" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" name="city" id="edit_city" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" id="edit_country" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Postal Code</label>
                        <input type="text" name="postal_code" id="edit_postal_code" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="edit_phone" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-input">
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="is_primary" id="edit_is_primary">
                    <label for="edit_is_primary">Set as primary location</label>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Location</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <button class="close-btn" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this location? This action cannot be undone.</p>
            </div>
            <form id="deleteForm" method="POST" action="">
                <input type="hidden" name="action" value="delete_location">
                <input type="hidden" name="location_id" id="delete_location_id">
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Location</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--line);
        }

        .modal-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--ink);
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--muted);
            padding: 0.5rem;
            border-radius: var(--radius-md);
            transition: var(--transition);
        }

        .close-btn:hover {
            background: var(--bg-secondary);
            color: var(--text-dark);
        }

        .modal-body {
            margin-bottom: 1.5rem;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--line);
        }
    </style>

    <script>
        // Location data for editing
        const locations = <?php echo json_encode($locations); ?>;

        function editLocation(locationId) {
            const location = locations.find(loc => loc.location_id == locationId);
            if (!location) return;

            document.getElementById('edit_location_id').value = location.location_id;
            document.getElementById('edit_location_name').value = location.location_name;
            document.getElementById('edit_location_type').value = location.location_type;
            document.getElementById('edit_address').value = location.address;
            document.getElementById('edit_city').value = location.city;
            document.getElementById('edit_country').value = location.country;
            document.getElementById('edit_postal_code').value = location.postal_code || '';
            document.getElementById('edit_phone').value = location.phone || '';
            document.getElementById('edit_email').value = location.email || '';
            document.getElementById('edit_is_primary').checked = location.is_primary == 1;

            document.getElementById('editModal').style.display = 'flex';
        }

        function deleteLocation(locationId) {
            document.getElementById('delete_location_id').value = locationId;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === editModal) {
                closeModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>
