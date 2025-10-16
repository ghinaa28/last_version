<?php
require_once 'connection.php';

// Get training type from request
$training_type = isset($_GET['training_type']) ? $conn->real_escape_string($_GET['training_type']) : '';

if (empty($training_type)) {
    echo json_encode(['error' => 'Training type is required']);
    exit();
}

// Get equipment recommendations for the training type
$recommendations_sql = "SELECT er.*, ec.category_name, ec.icon_class, ec.category_description
                        FROM equipment_recommendations er
                        JOIN equipment_categories ec ON er.category_id = ec.category_id
                        WHERE er.training_type = ? AND ec.status = 'active'
                        ORDER BY er.priority_level ASC, er.is_required DESC";

$stmt = $conn->prepare($recommendations_sql);
$stmt->bind_param("s", $training_type);
$stmt->execute();
$result = $stmt->get_result();

$recommendations = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Get equipment items for this category
        $items_sql = "SELECT ei.*, ec.category_name, ec.icon_class
                      FROM equipment_items ei
                      JOIN equipment_categories ec ON ei.category_id = ec.category_id
                      WHERE ei.category_id = ? AND ei.is_custom = FALSE AND ei.status = 'active'
                      ORDER BY ei.item_name";
        
        $items_stmt = $conn->prepare($items_sql);
        $items_stmt->bind_param("i", $row['category_id']);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        
        $items = [];
        if ($items_result && $items_result->num_rows > 0) {
            while ($item = $items_result->fetch_assoc()) {
                $items[] = $item;
            }
        }
        
        $recommendations[] = [
            'category_id' => $row['category_id'],
            'category_name' => $row['category_name'],
            'category_description' => $row['category_description'],
            'icon_class' => $row['icon_class'],
            'priority_level' => $row['priority_level'],
            'is_required' => $row['is_required'],
            'items' => $items
        ];
    }
}

// If no specific recommendations found, get general recommendations
if (empty($recommendations)) {
    $general_sql = "SELECT ec.*, 
                           (SELECT COUNT(*) FROM equipment_items ei WHERE ei.category_id = ec.category_id AND ei.is_custom = FALSE AND ei.status = 'active') as item_count
                    FROM equipment_categories ec
                    WHERE ec.status = 'active'
                    ORDER BY ec.category_name";
    
    $general_result = $conn->query($general_sql);
    if ($general_result && $general_result->num_rows > 0) {
        while ($row = $general_result->fetch_assoc()) {
            if ($row['item_count'] > 0) {
                // Get equipment items for this category
                $items_sql = "SELECT ei.*, ec.category_name, ec.icon_class
                              FROM equipment_items ei
                              JOIN equipment_categories ec ON ei.category_id = ec.category_id
                              WHERE ei.category_id = ? AND ei.is_custom = FALSE AND ei.status = 'active'
                              ORDER BY ei.item_name";
                
                $items_stmt = $conn->prepare($items_sql);
                $items_stmt->bind_param("i", $row['category_id']);
                $items_stmt->execute();
                $items_result = $items_stmt->get_result();
                
                $items = [];
                if ($items_result && $items_result->num_rows > 0) {
                    while ($item = $items_result->fetch_assoc()) {
                        $items[] = $item;
                    }
                }
                
                $recommendations[] = [
                    'category_id' => $row['category_id'],
                    'category_name' => $row['category_name'],
                    'category_description' => $row['category_description'],
                    'icon_class' => $row['icon_class'],
                    'priority_level' => 3, // General recommendation
                    'is_required' => false,
                    'items' => $items
                ];
            }
        }
    }
}

header('Content-Type: application/json');
echo json_encode([
    'training_type' => $training_type,
    'recommendations' => $recommendations
]);

$conn->close();
?>
