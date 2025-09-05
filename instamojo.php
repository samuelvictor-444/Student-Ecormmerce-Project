<?php 
include 'config.php';
session_start();

// Make sure user is logged in
$user = $_SESSION['username'] ?? 'Guest';
$user_id = $_SESSION['user_id'] ?? null;

// Get site name from DB
$db = new Database();
$db->select('options','site_name',null,null,null,null);
$site_name = $db->getResult();
$site_name = $site_name[0]['site_name'] ?? 'My Site';

// Collect product details from POST
$product_id   = $_POST['product_id'] ?? null;
$product_qty  = $_POST['product_qty'] ?? 1;
$product_total = $_POST['product_total'] ?? 0;

// ✅ Paystack API credentials (use test secret key here)
$paystack_secret = "sk_test_5ee577d576e335158c9260ae257fd54785dc6611"; // replace with your key
$paystack_url    = "https://api.paystack.co/transaction/initialize";

// Prepare payload for Paystack
$callback_url = $hostname.'/success.php'; // Paystack will redirect here after payment
$email = $_SESSION['email'] ?? "customer@example.com"; // Paystack requires email

$fields = [
    'email' => $email,
    'amount' => $product_total * 100, // amount in kobo (1 NGN = 100 kobo)
    'callback_url' => $callback_url,
    'metadata' => [
        'custom_fields' => [
            [
                "display_name" => "Customer Name",
                "variable_name" => "customer_name",
                "value" => $user
            ],
            [
                "display_name" => "Product ID",
                "variable_name" => "product_id",
                "value" => $product_id
            ],
            [
                "display_name" => "Quantity",
                "variable_name" => "product_qty",
                "value" => $product_qty
            ]
        ]
    ]
];

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $paystack_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $paystack_secret",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Execute request
$response = curl_exec($ch);
curl_close($ch);

$response = json_decode($response);

// ✅ Check if Paystack returned a payment link
if ($response && isset($response->data->authorization_url)) {
    $authorization_url = $response->data->authorization_url;
    $reference = $response->data->reference;

    // Save transaction in DB
    $_SESSION['TID'] = $reference;

    $params1 = [
        'item_number' => $product_id,
        'txn_id' => $reference,
        'payment_gross' => $product_total,
        'payment_status' => 'pending', // will update after verification
    ];
    $params2 = [
        'product_id' => $product_id,
        'product_qty' => $product_qty,
        'total_amount' => $product_total,
        'product_user' => $user_id,
        'order_date' => date('Y-m-d'),
        'pay_req_id' => $reference
    ];
    $db->insert('payments',$params1);
    $db->insert('order_products',$params2);
    $db->getResult();

    // Redirect to Paystack payment page
    header("Location: ".$authorization_url);
    exit;
} else {
    echo "Payment initialization failed!<br>";
    echo '<pre>';
    print_r($response);
    echo '</pre>';
    exit;
}
?>
