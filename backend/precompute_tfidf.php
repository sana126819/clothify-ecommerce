<?php
require 'db.php';

$res = $conn->query("SELECT product_id, description FROM products");
if (!$res) {
    die("Error fetching products: " . $conn->error);
}
$products = $res->fetch_all(MYSQLI_ASSOC);
if (empty($products)) {
    die("No products found to process.");
}

$corpus = [];
foreach ($products as $p) {
    $text = strtolower($p['description'] ?? '');
    $text = preg_replace("/[^a-z0-9\s]/", "", $text);
    $tokens = array_filter(explode(" ", $text));
    $corpus[$p['product_id']] = $tokens;
}

$doc_count = count($corpus);
$term_doc = [];
foreach ($corpus as $tokens) {
    foreach (array_unique($tokens) as $t) {
        $term_doc[$t] = ($term_doc[$t] ?? 0) + 1;
    }
}

foreach ($corpus as $product_id => $tokens) {
    $tf = array_count_values($tokens);
    $vec = [];
    $token_count = count($tokens);
    if ($token_count === 0) continue; 

    foreach ($tf as $term => $f) {
        $vec[$term] = ($f / $token_count) * log($doc_count / ($term_doc[$term] ?? 1));
    }

    $tfidf_json = json_encode($vec, JSON_UNESCAPED_UNICODE);

    $stmt = $conn->prepare("
        INSERT INTO product_tfidf (product_id, tfidf_json) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE tfidf_json=?, updated_at=NOW()
    ");
    $stmt->bind_param("iss", $product_id, $tfidf_json, $tfidf_json);
    $stmt->execute();
    $stmt->close();
}

echo "TF-IDF precomputed for all products successfully.";
?>
