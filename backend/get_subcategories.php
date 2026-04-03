<?php
include 'db.php';
header('Content-Type: application/json'); // ensure JSON content

$main_category_id = intval($_GET['main_category_id'] ?? 0);
if ($main_category_id <= 0) {
    echo json_encode([]);
    exit;
}

$result = $conn->prepare("SELECT id, name FROM sub_categories WHERE main_category_id = ?");
$result->bind_param("i", $main_category_id);
$result->execute();
$res = $result->get_result();

$subs = [];
while ($row = $res->fetch_assoc()) {
    $subs[] = ['id' => $row['id'], 'name' => $row['name']];
}

echo json_encode($subs);
