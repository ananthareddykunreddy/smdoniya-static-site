<?php
$to = "cafbixio5@gmail.com";
$subject = "New appointment request from website";

$full_name = trim($_POST["full_name"] ?? "");
$email = trim($_POST["email"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$service = trim($_POST["service"] ?? "");
$message = trim($_POST["message"] ?? "");

if ($full_name === "" || $email === "" || $phone === "" || $message === "") {
    http_response_code(400);
    echo "Missing required fields.";
    exit;
}

$body = "Full name: $full_name\n";
$body .= "Email: $email\n";
$body .= "Phone: $phone\n";
$body .= "Service: $service\n";
$body .= "Message:\n$message\n";

$headers = "From: $full_name <$email>\r\n";
$headers .= "Reply-To: $email\r\n";

if (mail($to, $subject, $body, $headers)) {
    echo "Thank you. Your request has been sent.";
} else {
    http_response_code(500);
    echo "Error sending email. Please try again later.";
}
?>
