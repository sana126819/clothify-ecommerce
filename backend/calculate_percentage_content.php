<?php
require 'db.php';

// -------------------------------
// 1. Fetch products
// -------------------------------
$res = $conn->query("SELECT product_id, main_category_id, sub_category_id, color, size, description, product_name FROM products");
if (!$res) die("Error fetching products: " . $conn->error);
$products = $res->fetch_all(MYSQLI_ASSOC);
if (!$products) die("No products found.");

// -------------------------------
// 2. Preprocess descriptions + attributes into tokens
// -------------------------------
$corpus = [];
foreach ($products as $p) {
    $tokens = [];

    // Name + description tokens
    $text = strtolower(($p['product_name'] ?? '') . ' ' . ($p['description'] ?? ''));
    $text = preg_replace("/[^a-z0-9\s]/", "", $text);
    $tokens = array_merge($tokens, array_filter(explode(" ", $text)));

    // Add structured features as tokens
    if (!empty($p['main_category_id'])) $tokens[] = 'main_' . $p['main_category_id'];
    if (!empty($p['sub_category_id'])) $tokens[] = 'sub_' . $p['sub_category_id'];
    if (!empty($p['color'])) $tokens[] = 'color_' . $p['color'];
    if (!empty($p['size'])) $tokens[] = 'size_' . $p['size'];

    $corpus[$p['product_id']] = $tokens;
}

// -------------------------------
// 3. Compute TF-IDF vectors
// -------------------------------
$doc_count = count($corpus);
$term_doc = [];
foreach ($corpus as $tokens) {
    foreach (array_unique($tokens) as $t) {
        $term_doc[$t] = ($term_doc[$t] ?? 0) + 1;
    }
}

$tfidf_vectors = [];
foreach ($corpus as $product_id => $tokens) {
    $tf = array_count_values($tokens);
    $vec = [];
    $token_count = count($tokens);
    if ($token_count === 0) continue;

    foreach ($tf as $term => $f) {
        $vec[$term] = ($f / $token_count) * log($doc_count / ($term_doc[$term] ?? 1));
    }
    $tfidf_vectors[$product_id] = $vec;
}

// -------------------------------
// 4. Cosine similarity function
// -------------------------------
function cosine_similarity($vecA, $vecB) {
    $dot = 0; $normA = 0; $normB = 0;
    foreach ($vecA as $k => $v) {
        $dot += $v * ($vecB[$k] ?? 0);
        $normA += $v * $v;
    }
    foreach ($vecB as $v) $normB += $v * $v;
    return ($normA == 0 || $normB == 0) ? 0 : $dot / (sqrt($normA) * sqrt($normB));
}

// -------------------------------
// 5. Structured similarity function (0..1)
// -------------------------------
function structured_similarity($a, $b) {
    $score = 0;
    $total = 4; // main_category, sub_category, color, size
    if ($a['main_category_id'] == $b['main_category_id']) $score++;
    if ($a['sub_category_id'] == $b['sub_category_id']) $score++;
    if ($a['color'] == $b['color']) $score++;
    if ($a['size'] == $b['size']) $score++;
    return $score / $total;
}

// -------------------------------
// 6. Hybrid top-N percentage
// -------------------------------
$topN = 3;
$percentages = [];
$weight_structured = 0.5; // can tune
$weight_content = 0.5;    // can tune

foreach ($products as $p) {
    $product_id = $p['product_id'];
    if (!isset($tfidf_vectors[$product_id])) continue;

    // Compute similarity scores with all other products
    $scores = [];
    foreach ($products as $other) {
        $other_id = $other['product_id'];
        if ($other_id == $product_id || !isset($tfidf_vectors[$other_id])) continue;

        $struct_score = structured_similarity($p, $other);
        $content_score = cosine_similarity($tfidf_vectors[$product_id], $tfidf_vectors[$other_id]);
        $hybrid_score = $weight_structured * $struct_score + $weight_content * $content_score;

        $scores[$other_id] = $hybrid_score;
    }

    // Top-N hybrid matches
    arsort($scores);
    $top_products = array_slice(array_keys($scores), 0, $topN);

    // Compute average hybrid match percentage
    $percentage = 0;
    foreach ($top_products as $tp_id) {
        $tp = array_values(array_filter($products, fn($x) => $x['product_id'] == $tp_id))[0];
        $struct_score = structured_similarity($p, $tp);
        $content_score = cosine_similarity($tfidf_vectors[$product_id], $tfidf_vectors[$tp_id]);
        $percentage += ($weight_structured * $struct_score + $weight_content * $content_score) * 100;
    }
    $percentage /= $topN;

    $percentages[] = $percentage;
    echo "Product $product_id top $topN hybrid match percentage: $percentage%\n";
}

// Overall average hybrid percentage
$overall = array_sum($percentages) / count($percentages);
echo "\nOverall average hybrid percentage: $overall%\n";
?>
