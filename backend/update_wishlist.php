<?php
include 'db.php';
$pid = intval($_POST['product_id']);
$user_id = $_SESSION['user_id'] ?? null;

if($user_id){
    $res = $conn->query("SELECT * FROM wishlist WHERE user_id=$user_id AND product_id=$pid");
    if($res->num_rows>0){
        $conn->query("DELETE FROM wishlist WHERE user_id=$user_id AND product_id=$pid");
    } else {
        $conn->query("INSERT INTO wishlist(user_id,product_id,added_at) VALUES($user_id,$pid,NOW())");
    }
} else {
    // guest
    if(in_array($pid,$_SESSION['wishlist'])){
        $_SESSION['wishlist'] = array_diff($_SESSION['wishlist'],[$pid]);
    } else {
        $_SESSION['wishlist'][] = $pid;
    }
}
?>
