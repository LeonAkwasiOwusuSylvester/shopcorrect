<?php
require_once __DIR__ . "/../../app/config/db.php";
header('Content-Type: application/json');

// Security check
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Updated Bouncer: Allow Super Admin, Country Agent, and Support
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['supadmin', 'country_agent', 'support'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'No ID provided']);
    exit;
}

$id = $_GET['id'];
$userRole = $_SESSION['role'];
$managedCountry = $_SESSION['managed_country'] ?? null;

// 2. Fetch Order Details
$orderQuery = "
    SELECT 
        o.*, 
        COALESCE(o.discount_amount, 0.00) as discount_amount,
        u.email as user_email 
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    WHERE o.id = ?
";
$queryParams = [$id];

// Backend Security: Country Agents can ONLY fetch data for their own country's orders
if ($userRole === 'country_agent') {
    $orderQuery .= " AND o.shipping_country = ?";
    $queryParams[] = $managedCountry;
}

$stmt = $pdo->prepare($orderQuery);
$stmt->execute($queryParams);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo json_encode(['error' => 'Order not found or unauthorized']);
    exit;
}

// 3. Fetch Items
$stmtItems = $pdo->prepare("
    SELECT 
        oi.*, 
        COALESCE(oi.discount_amount, 0.00) as discount_amount,
        COALESCE(p.name, 'Product no longer available') as product_name, 
        p.image 
    FROM order_items oi 
    LEFT JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// 4. Return Data
echo json_encode([
    'order' => $order,
    'items' => $items
]);