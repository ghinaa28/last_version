<?php
session_start();
require_once 'connection.php';

// Check if user is logged in and is a company
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'company') {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['user_id'];
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
                
                $sql = "INSERT INTO equipment_items (category_id, item_name, item_description, standard_price, unit_type, is_custom, company_id) 
                        VALUES ('$category_id', '$item_name', '$item_description', '$standard_price', '$unit_type', TRUE, '$company_id')";
                
                if ($conn->query($sql)) {
                    $success = "Custom equipment added successfully!";
                } else {
                    $error = "Error adding custom equipment: " . $conn->error;
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
if ($custom_result && $custom_result->num_rows > 0) {
    while ($row = $custom_result->fetch_assoc()) {
        $custom_items[] = $row;
    }
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .header h1 {
            color: #4a5568;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header p {
            color: #718096;
            font-size: 1.1rem;
        }

        .tabs {
            display: flex;
            background: white;
            border-radius: 15px;
            padding: 0.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .tab {
            flex: 1;
            padding: 1rem 2rem;
            text-align: center;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            color: #718096;
        }

        .tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
        }

        .tab-content {
            display: none;
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
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
            color: #4a5568;
        }

        .form-label.required::after {
            content: " *";
            color: #e53e3e;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        .btn-success {
            background: #48bb78;
            color: white;
        }

        .btn-success:hover {
            background: #38a169;
        }

        .equipment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .equipment-card {
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .equipment-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .equipment-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .equipment-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .equipment-info h3 {
            color: #2d3748;
            margin-bottom: 0.25rem;
        }

        .equipment-category {
            color: #718096;
            font-size: 0.9rem;
        }

        .equipment-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: #48bb78;
            margin-bottom: 0.5rem;
        }

        .equipment-description {
            color: #4a5568;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .package-card {
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .package-card:hover {
            border-color: #48bb78;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .package-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
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

        .alert-success {
            background: #f0fff4;
            border: 1px solid #9ae6b4;
            color: #22543d;
        }

        .alert-error {
            background: #fed7d7;
            border: 1px solid #feb2b2;
            color: #742a2a;
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
            <p>Browse and manage predefined equipment items</p>
            
            <?php if (empty($predefined_items)): ?>
                <div class="empty-state">
                    <i class="fas fa-tools"></i>
                    <h3>No Equipment Found</h3>
                    <p>No predefined equipment items are available at the moment.</p>
                </div>
            <?php else: ?>
                <div class="equipment-grid">
                    <?php foreach ($predefined_items as $item): ?>
                        <div class="equipment-card">
                            <div class="equipment-header">
                                <div class="equipment-icon">
                                    <i class="<?php echo $item['icon_class']; ?>"></i>
                                </div>
                                <div class="equipment-info">
                                    <h3><?php echo htmlspecialchars($item['item_name']); ?></h3>
                                    <div class="equipment-category"><?php echo htmlspecialchars($item['category_name']); ?></div>
                                </div>
                            </div>
                            <div class="equipment-price">
                                $<?php echo number_format($item['standard_price'], 2); ?>
                                <span style="font-size: 0.8rem; color: #718096;">per <?php echo str_replace('_', ' ', $item['unit_type']); ?></span>
                            </div>
                            <div class="equipment-description">
                                <?php echo htmlspecialchars($item['item_description']); ?>
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
                                <div class="package-name"><?php echo htmlspecialchars($package['package_name']); ?></div>
                                <div class="package-type"><?php echo ucfirst($package['package_type']); ?></div>
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
    </script>
</body>
</html>
