<?php
include 'config.php';
session_start();

// Set your site hostname
$hostname = $hostname ?? "http://localhost/E-commerce"; // replace with your actual site URL

$title = 'Payment Unsuccessful';
$response = '<div class="panel-body">
               <i class="fa fa-times-circle text-danger"></i>
               <h3>Payment Unsuccessful</h3>
               <a href="'.$hostname.'" class="btn btn-md btn-primary">Continue Shopping</a>
             </div>';

// Check if Paystack sent the reference
if (isset($_GET['reference'])) {
    $reference = $_GET['reference'];

    // Paystack Secret Key
    $paystack_secret = "sk_test_5ee577d576e335158c9260ae257fd54785dc6611"; // replace with your secret key

    // Verify transaction with Paystack
    $url = "https://api.paystack.co/transaction/verify/" . $reference;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $paystack_secret"
    ]);
    $result = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($result, true);

    // Debug: uncomment to see raw Paystack response
    // echo '<pre>'; print_r($result); exit;

    // Check if payment was successful
    if ($result && isset($result['status']) && $result['status'] && $result['data']['status'] === 'success') {
        $title = 'Payment Successful';
        $response = '<div class="panel-body">
                       <i class="fa fa-check-circle text-success"></i>
                       <h3>Payment Successful</h3>
                       <p>Your Product Will be Delivered within 4 to 7 days.</p>
                       <a href="'.$hostname.'" class="btn btn-md btn-primary">Continue Shopping</a>
                     </div>';

        // Reduce purchased quantity from products
        $db = new Database();
        $db->select('order_products','product_id,product_qty',null,"pay_req_id = '{$reference}'",null,null);
        $orders = $db->getResult();

        if (!empty($orders)) {
            $products = array_filter(explode(',', $orders[0]['product_id']));
            $qty = array_filter(explode(',', $orders[0]['product_qty']));
            for ($i = 0; $i < count($products); $i++) {
                $db->sql("UPDATE products SET qty = qty - '{$qty[$i]}' WHERE product_id = '{$products[$i]}'");
            }
        }

        // Clear cart cookies
        if (isset($_COOKIE['user_cart'])) {
            setcookie('cart_count','',time() - 3600,'/');
            setcookie('user_cart','',time() - 3600,'/');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/font-awesome.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="payment-response">
        <div class="container">
            <div class="row">
                <div class="col-md-offset-3 col-md-6">
                    <div class="panel panel-default">
                        <?php echo $response; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
