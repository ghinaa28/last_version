<?php
session_start();
require_once 'connection.php';

// Check if user is logged in as company
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['company_id'];
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_custom_equipment':
                $category_id = $conn->real_escape_string($_POST['category_id']);
                $item_name = $conn->real_escape_string($_POST['item_name']);
                $item_description = $conn->real_escape_string($_POST['item_description']);
                $standard_price = floatval($_POST['standard_price']);
                $unit_type = $conn->real_escape_string($_POST['unit_type']);
                
                $sql = "INSERT INTO equipment_items (category_id, item_name, description, standard_price, unit_type, is_custom, company_id) 
                        VALUES ('$category_id', '$item_name', '$item_description', '$standard_price', '$unit_type', TRUE, '$company_id')";
                
                if ($conn->query($sql)) {
                    $success = "Custom equipment added successfully!";
                } else {
                    $error = "Error adding custom equipment: " . $conn->error;
                }
                break;
                
            case 'edit_equipment':
                $item_id = intval($_POST['item_id']);
                $category_id = $conn->real_escape_string($_POST['category_id']);
                $item_name = $conn->real_escape_string($_POST['item_name']);
                $item_description = $conn->real_escape_string($_POST['item_description']);
                $standard_price = floatval($_POST['standard_price']);
                $unit_type = $conn->real_escape_string($_POST['unit_type']);
                
                $sql = "UPDATE equipment_items 
                        SET category_id = '$category_id', item_name = '$item_name', description = '$item_description', 
                            standard_price = '$standard_price', unit_type = '$unit_type'
                        WHERE item_id = '$item_id' AND company_id = '$company_id' AND is_custom = TRUE";
                
                if ($conn->query($sql)) {
                    $success = "Equipment updated successfully!";
                } else {
                    $error = "Error updating equipment: " . $conn->error;
                }
                break;
                
            case 'delete_equipment':
                $item_id = intval($_POST['item_id']);
                
                $sql = "UPDATE equipment_items 
                        SET status = 'inactive' 
                        WHERE item_id = '$item_id' AND company_id = '$company_id' AND is_custom = TRUE";
                
                if ($conn->query($sql)) {
                    $success = "Equipment deleted successfully!";
                } else {
                    $error = "Error deleting equipment: " . $conn->error;
                }
                break;
                
            case 'create_package':
                $package_name = $conn->real_escape_string($_POST['package_name']);
                $package_description = $conn->real_escape_string($_POST['package_description']);
                $discount_percentage = floatval($_POST['discount_percentage']);
                $selected_items = $_POST['selected_items'] ?? [];
                
                $conn->begin_transaction();
                try {
                    // Calculate total price
                    $total_price = 0;
                    foreach ($selected_items as $item_id => $data) {
                        $quantity = intval($data['quantity']);
                        $custom_price = floatval($data['custom_price']);
                        $item_price = $custom_price > 0 ? $custom_price : 0;
                        $total_price += $item_price * $quantity;
                    }
                    
                    // Apply discount
                    $discounted_price = $total_price * (1 - $discount_percentage / 100);
                    
                    // Insert package
                    $package_sql = "INSERT INTO equipment_packages (package_name, package_description, package_type, company_id, total_price, discount_percentage) 
                                   VALUES ('$package_name', '$package_description', 'custom', '$company_id', '$discounted_price', '$discount_percentage')";
                    
                    if (!$conn->query($package_sql)) {
                        throw new Exception("Error creating package: " . $conn->error);
                    }
                    
                    $package_id = $conn->insert_id;
                    
                    // Insert package items
                    foreach ($selected_items as $item_id => $data) {
                        $quantity = intval($data['quantity']);
                        $custom_price = floatval($data['custom_price']);
                        
                        $item_sql = "INSERT INTO package_items (package_id, item_id, quantity, custom_price) 
                                    VALUES ('$package_id', '$item_id', '$quantity', '$custom_price')";
                        
                        if (!$conn->query($item_sql)) {
                            throw new Exception("Error adding items to package: " . $conn->error);
                        }
                    }
                    
                    $conn->commit();
                    $success = "Equipment package created successfully!";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Error creating package: " . $e->getMessage();
                }
                break;
                
            case 'edit_package':
                $package_id = intval($_POST['package_id']);
                $package_name = $conn->real_escape_string($_POST['package_name']);
                $package_description = $conn->real_escape_string($_POST['package_description']);
                $discount_percentage = floatval($_POST['discount_percentage']);
                $selected_items = $_POST['selected_items'] ?? [];
                
                $conn->begin_transaction();
                try {
                    // Update package details
                    $package_sql = "UPDATE equipment_packages 
                                   SET package_name = '$package_name', package_description = '$package_description', 
                                       discount_percentage = '$discount_percentage'
                                   WHERE package_id = '$package_id' AND company_id = '$company_id'";
                    
                    if (!$conn->query($package_sql)) {
                        throw new Exception("Error updating package: " . $conn->error);
                    }
                    
                    // Delete existing package items
                    $delete_items_sql = "DELETE FROM package_items WHERE package_id = '$package_id'";
                    if (!$conn->query($delete_items_sql)) {
                        throw new Exception("Error clearing package items: " . $conn->error);
                    }
                    
                    // Insert new package items
                    foreach ($selected_items as $item_id => $data) {
                        $quantity = intval($data['quantity']);
                        $custom_price = floatval($data['custom_price']);
                        
                        $item_sql = "INSERT INTO package_items (package_id, item_id, quantity, custom_price) 
                                    VALUES ('$package_id', '$item_id', '$quantity', '$custom_price')";
                        
                        if (!$conn->query($item_sql)) {
                            throw new Exception("Error adding items to package: " . $conn->error);
                        }
                    }
                    
                    $conn->commit();
                    $success = "Equipment package updated successfully!";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Error updating package: " . $e->getMessage();
                }
                break;
                
            case 'delete_package':
                $package_id = intval($_POST['package_id']);
                
                $conn->begin_transaction();
                try {
                    // Delete package items first
                    $delete_items_sql = "DELETE FROM package_items WHERE package_id = '$package_id'";
                    if (!$conn->query($delete_items_sql)) {
                        throw new Exception("Error deleting package items: " . $conn->error);
                    }
                    
                    // Delete package
                    $delete_package_sql = "DELETE FROM equipment_packages 
                                          WHERE package_id = '$package_id' AND company_id = '$company_id'";
                    if (!$conn->query($delete_package_sql)) {
                        throw new Exception("Error deleting package: " . $conn->error);
                    }
                    
                    $conn->commit();
                    $success = "Equipment package deleted successfully!";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Error deleting package: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get equipment categories
$categories_sql = "SELECT * FROM equipment_categories WHERE status = 'active' ORDER BY category_name";
$categories_result = $conn->query($categories_sql);
$categories = [];
if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Get predefined equipment items
$predefined_sql = "SELECT ei.*, ec.category_name, ec.icon_class 
                   FROM equipment_items ei 
                   JOIN equipment_categories ec ON ei.category_id = ec.category_id 
                   WHERE ei.is_custom = FALSE AND ei.status = 'active' 
                   ORDER BY ec.category_name, ei.item_name";
$predefined_result = $conn->query($predefined_sql);
$predefined_items = [];
if ($predefined_result && $predefined_result->num_rows > 0) {
    while ($row = $predefined_result->fetch_assoc()) {
        $predefined_items[] = $row;
    }
}

// Get custom equipment items for this company
$custom_sql = "SELECT ei.*, ec.category_name, ec.icon_class 
               FROM equipment_items ei 
               JOIN equipment_categories ec ON ei.category_id = ec.category_id 
               WHERE ei.is_custom = TRUE AND ei.company_id = '$company_id' AND ei.status = 'active' 
               ORDER BY ec.category_name, ei.item_name";
$custom_result = $conn->query($custom_sql);
$custom_items = [];
if ($custom_result) {
    if ($custom_result->num_rows > 0) {
        while ($row = $custom_result->fetch_assoc()) {
            $custom_items[] = $row;
        }
    }
} else {
    // Log error for debugging
    error_log("Custom equipment query error: " . $conn->error);
}

// Get equipment packages
$packages_sql = "SELECT ep.*, 
                        (SELECT COUNT(*) FROM package_items pi WHERE pi.package_id = ep.package_id) as item_count
                 FROM equipment_packages ep 
                 WHERE (ep.company_id = '$company_id' OR ep.package_type = 'predefined') 
                 AND ep.status = 'active' 
                 ORDER BY ep.package_type, ep.package_name";
$packages_result = $conn->query($packages_sql);
$packages = [];
if ($packages_result && $packages_result->num_rows > 0) {
    while ($row = $packages_result->fetch_assoc()) {
        $packages[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Equipment - GradConnect</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --brand: #0ea5a8;
            --brand-2: #22d3ee;
            --ink: #0b1f3a;
            --muted: #475569;
            --panel: #ffffff;
            --bg-primary: #f6f8fb;
            --bg-secondary: #f1f5f9;
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --text-white: #ffffff;
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --transition: all 0.2s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            min-height: 100vh;
            color: var(--ink);
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
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-light);
        }

        .header h1 {
            color: var(--ink);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 2rem;
            font-weight: 800;
        }

        .header p {
            color: var(--muted);
            font-size: 1.1rem;
        }

        .tabs {
            display: flex;
            background: var(--panel);
            border-radius: var(--radius-lg);
            padding: 0.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
        }

        .tab {
            flex: 1;
            padding: 1rem 2rem;
            text-align: center;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
            color: var(--muted);
        }

        .tab.active {
            background: var(--brand);
            color: var(--text-white);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .tab:hover:not(.active) {
            background: var(--bg-secondary);
            color: var(--ink);
        }

        .tab-content {
            display: none;
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-light);
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--ink);
        }

        .form-label.required::after {
            content: " *";
            color: var(--error);
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--bg-primary);
            color: var(--ink);
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: var(--radius-lg);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--brand);
            color: var(--text-white);
        }

        .btn-primary:hover {
            background: var(--brand-2);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--muted);
            border: 2px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--border);
            color: var(--ink);
        }

        .btn-danger {
            background: var(--error);
            color: var(--text-white);
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-success {
            background: var(--success);
            color: var(--text-white);
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .equipment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .equipment-card {
            background: var(--panel);
            border: 2px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .equipment-card:hover {
            border-color: var(--brand);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .equipment-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            position: relative;
        }

        .equipment-actions {
            display: flex;
            gap: 0.5rem;
            margin-left: auto;
        }

        .btn-icon {
            width: 2rem;
            height: 2rem;
            border: none;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.875rem;
        }

        .edit-btn {
            background: var(--brand);
            color: var(--text-white);
        }

        .edit-btn:hover {
            background: #0d8a8d;
        }

        .delete-btn {
            background: var(--error);
            color: var(--text-white);
        }

        .delete-btn:hover {
            background: #dc2626;
        }

        .equipment-icon {
            width: 50px;
            height: 50px;
            background: var(--brand);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-white);
            font-size: 1.5rem;
        }

        .equipment-info h3 {
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .equipment-category {
            color: var(--muted);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .custom-badge {
            background: var(--brand);
            color: var(--text-white);
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .equipment-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--success);
            margin-bottom: 0.5rem;
        }

        .equipment-description {
            color: var(--ink);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .package-card {
            background: var(--bg-secondary);
            border: 2px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .package-card:hover {
            border-color: var(--success);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .package-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .package-info-section {
            flex: 1;
        }

        .package-actions {
            display: flex;
            gap: 0.5rem;
        }

        .package-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: #2d3748;
        }

        .package-type {
            background: #48bb78;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .package-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: #48bb78;
            margin-bottom: 0.5rem;
        }

        .package-info {
            color: #718096;
            font-size: 0.9rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }


        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .package-builder {
            background: #f7fafc;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .item-selector {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .item-option {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .item-option:hover {
            border-color: #667eea;
        }

        .item-option.selected {
            border-color: #48bb78;
            background: #f0fff4;
        }

        .item-option input[type="checkbox"] {
            margin-right: 0.5rem;
        }

        .quantity-input {
            width: 80px;
            padding: 0.25rem 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            text-align: center;
        }

        .price-input {
            width: 100px;
            padding: 0.25rem 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            text-align: center;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #718096;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e0;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .equipment-grid {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .tab {
                padding: 0.75rem 1rem;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
        }
        
        /* Success and Error Messages */
        .alert {
            padding: 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #059669;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #dc2626;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--muted);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: var(--ink);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-tools"></i>
                Smart Equipment Management
            </h1>
            <p>Manage your equipment inventory, create custom packages, and set up smart recommendations</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="tabs">
            <div class="tab active" onclick="showTab('equipment')">
                <i class="fas fa-tools"></i> Equipment Library
            </div>
            <div class="tab" onclick="showTab('custom')">
                <i class="fas fa-plus-circle"></i> Add Custom Equipment
            </div>
            <div class="tab" onclick="showTab('packages')">
                <i class="fas fa-box"></i> Equipment Packages
            </div>
            <div class="tab" onclick="showTab('create-package')">
                <i class="fas fa-cogs"></i> Create Package
            </div>
        </div>

        <!-- Equipment Library Tab -->
        <div id="equipment" class="tab-content active">
            <h2>Equipment Library</h2>
            <p>Browse and manage all available equipment items</p>
            
            <?php 
            // Combine predefined and custom equipment
            $all_equipment = array_merge($predefined_items, $custom_items);
            
            // Debug information (remove in production)
            if (isset($_GET['debug'])) {
                echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
                echo "<strong>Debug Info:</strong><br>";
                echo "Company ID: " . htmlspecialchars($company_id) . "<br>";
                echo "Predefined items: " . count($predefined_items) . "<br>";
                echo "Custom items: " . count($custom_items) . "<br>";
                echo "Total items: " . count($all_equipment) . "<br>";
                echo "</div>";
            }
            ?>
            
            <?php if (empty($all_equipment)): ?>
                <div class="empty-state">
                    <i class="fas fa-tools"></i>
                    <h3>No Equipment Found</h3>
                    <p>No equipment items are available at the moment.</p>
                </div>
            <?php else: ?>
                <div class="equipment-grid">
                    <?php foreach ($all_equipment as $item): ?>
                        <div class="equipment-card">
                            <div class="equipment-header">
                                <div class="equipment-icon">
                                    <i class="<?php echo $item['icon_class']; ?>"></i>
                                </div>
                                <div class="equipment-info">
                                    <h3><?php echo htmlspecialchars($item['item_name']); ?></h3>
                                    <div class="equipment-category">
                                        <?php echo htmlspecialchars($item['category_name']); ?>
                                        <?php if ($item['is_custom']): ?>
                                            <span class="custom-badge">Custom</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($item['is_custom']): ?>
                                    <div class="equipment-actions">
                                        <button class="btn-icon edit-btn" onclick="editEquipment(<?php echo $item['item_id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-icon delete-btn" onclick="deleteEquipment(<?php echo $item['item_id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="equipment-price">
                                $<?php echo number_format($item['standard_price'], 2); ?>
                                <span style="font-size: 0.8rem; color: #718096;">per <?php echo str_replace('_', ' ', $item['unit_type']); ?></span>
                            </div>
                            <div class="equipment-description">
                                <?php echo htmlspecialchars($item['description'] ?? 'No description available'); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Add Custom Equipment Tab -->
        <div id="custom" class="tab-content">
            <h2>Add Custom Equipment</h2>
            <p>Add your own equipment items to the system</p>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_custom_equipment">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Category</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['category_id']; ?>">
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Equipment Name</label>
                        <input type="text" name="item_name" class="form-input" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="item_description" class="form-textarea" placeholder="Describe the equipment and its features"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Standard Price</label>
                        <input type="number" name="standard_price" class="form-input" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Unit Type</label>
                        <select name="unit_type" class="form-select" required>
                            <option value="">Select Unit</option>
                            <option value="per_hour">Per Hour</option>
                            <option value="per_day">Per Day</option>
                            <option value="per_week">Per Week</option>
                            <option value="per_month">Per Month</option>
                            <option value="one_time">One Time</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add Custom Equipment
                </button>
            </form>
        </div>

        <!-- Equipment Packages Tab -->
        <div id="packages" class="tab-content">
            <h2>Equipment Packages</h2>
            <p>Browse available equipment packages</p>
            
            <?php if (empty($packages)): ?>
                <div class="empty-state">
                    <i class="fas fa-box"></i>
                    <h3>No Packages Found</h3>
                    <p>No equipment packages are available at the moment.</p>
                </div>
            <?php else: ?>
                <div class="equipment-grid">
                    <?php foreach ($packages as $package): ?>
                        <div class="package-card">
                            <div class="package-header">
                                <div class="package-info-section">
                                    <div class="package-name"><?php echo htmlspecialchars($package['package_name']); ?></div>
                                    <div class="package-type"><?php echo ucfirst($package['package_type']); ?></div>
                                </div>
                                <?php if ($package['package_type'] === 'custom'): ?>
                                    <div class="package-actions">
                                        <button class="btn-icon edit-btn" onclick="editPackage(<?php echo $package['package_id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-icon delete-btn" onclick="deletePackage(<?php echo $package['package_id']; ?>, '<?php echo htmlspecialchars($package['package_name']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="package-price">
                                $<?php echo number_format($package['total_price'], 2); ?>
                                <?php if ($package['discount_percentage'] > 0): ?>
                                    <span style="font-size: 0.8rem; color: #e53e3e;">
                                        (<?php echo $package['discount_percentage']; ?>% off)
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="package-info">
                                <p><?php echo htmlspecialchars($package['package_description']); ?></p>
                                <p><strong><?php echo $package['item_count']; ?></strong> items included</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Create Package Tab -->
        <div id="create-package" class="tab-content">
            <h2>Create Equipment Package</h2>
            <p>Build your own custom equipment package</p>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_package">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Package Name</label>
                        <input type="text" name="package_name" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Discount Percentage</label>
                        <input type="number" name="discount_percentage" class="form-input" step="0.01" min="0" max="100" value="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Package Description</label>
                    <textarea name="package_description" class="form-textarea" placeholder="Describe what this package includes and its benefits"></textarea>
                </div>
                
                <div class="package-builder">
                    <h3>Select Equipment Items</h3>
                    <p>Choose items to include in your package and set quantities and prices</p>
                    
                    <div class="item-selector">
                        <?php 
                        $all_items = array_merge($predefined_items, $custom_items);
                        foreach ($all_items as $item): 
                        ?>
                            <div class="item-option" data-item-id="<?php echo $item['item_id']; ?>">
                                <input type="checkbox" name="selected_items[<?php echo $item['item_id']; ?>][selected]" value="1" onchange="toggleItemSelection(this)">
                                <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                <div style="font-size: 0.9rem; color: #718096; margin: 0.5rem 0;">
                                    <?php echo htmlspecialchars($item['category_name']); ?> • 
                                    $<?php echo number_format($item['standard_price'], 2); ?> per <?php echo str_replace('_', ' ', $item['unit_type']); ?>
                                </div>
                                <div style="display: none;" class="item-details">
                                    <div style="display: flex; gap: 1rem; align-items: center; margin-top: 0.5rem;">
                                        <label style="font-size: 0.8rem;">Qty:</label>
                                        <input type="number" name="selected_items[<?php echo $item['item_id']; ?>][quantity]" class="quantity-input" min="1" value="1">
                                        <label style="font-size: 0.8rem;">Price:</label>
                                        <input type="number" name="selected_items[<?php echo $item['item_id']; ?>][custom_price]" class="price-input" step="0.01" min="0" value="<?php echo $item['standard_price']; ?>">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Create Package
                </button>
            </form>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        function toggleItemSelection(checkbox) {
            const itemOption = checkbox.closest('.item-option');
            const itemDetails = itemOption.querySelector('.item-details');
            
            if (checkbox.checked) {
                itemOption.classList.add('selected');
                itemDetails.style.display = 'block';
            } else {
                itemOption.classList.remove('selected');
                itemDetails.style.display = 'none';
            }
        }

        function editEquipment(itemId) {
            // For now, redirect to a simple edit form
            // In a full implementation, you'd show a modal or redirect to an edit page
            const newName = prompt('Enter new equipment name:');
            if (newName && newName.trim() !== '') {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="edit_equipment">
                    <input type="hidden" name="item_id" value="${itemId}">
                    <input type="hidden" name="item_name" value="${newName.trim()}">
                    <input type="hidden" name="category_id" value="1">
                    <input type="hidden" name="item_description" value="Updated equipment">
                    <input type="hidden" name="standard_price" value="0">
                    <input type="hidden" name="unit_type" value="piece">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteEquipment(itemId, itemName) {
            if (confirm(`Are you sure you want to delete "${itemName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_equipment">
                    <input type="hidden" name="item_id" value="${itemId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function editPackage(packageId) {
            // For now, redirect to a simple edit form
            const newName = prompt('Enter new package name:');
            if (newName && newName.trim() !== '') {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="edit_package">
                    <input type="hidden" name="package_id" value="${packageId}">
                    <input type="hidden" name="package_name" value="${newName.trim()}">
                    <input type="hidden" name="package_description" value="Updated package">
                    <input type="hidden" name="discount_percentage" value="0">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deletePackage(packageId, packageName) {
            if (confirm(`Are you sure you want to delete package "${packageName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_package">
                    <input type="hidden" name="package_id" value="${packageId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>

