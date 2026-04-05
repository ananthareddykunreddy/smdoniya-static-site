<?php
declare(strict_types=1);

require __DIR__ . "/config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    auth_json(405, ["ok" => false, "error" => "Method not allowed"]);
}

$data = auth_json_body();
$full_name = trim((string)($data["full_name"] ?? ""));
$email = strtolower(trim((string)($data["email"] ?? "")));
$phone = trim((string)($data["phone"] ?? ""));
$password = (string)($data["password"] ?? "");
$language = trim((string)($data["preferred_language"] ?? "en"));

if ($full_name === "" || $email === "" || $password === "") {
    auth_json(400, ["ok" => false, "error" => "Missing required fields"]);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    auth_json(400, ["ok" => false, "error" => "Invalid email"]);
}
if (strlen($password) < 8) {
    auth_json(400, ["ok" => false, "error" => "Password must be at least 8 characters"]);
}
if ($language === "") {
    $language = "en";
}

$db = auth_db_connection();
auth_bootstrap_tables($db);

$check = $db->prepare("SELECT id FROM client_users WHERE email = ? LIMIT 1");
$check->bind_param("s", $email);
$check->execute();
$existing = $check->get_result();
if ($existing && $existing->fetch_assoc()) {
    $check->close();
    $db->close();
    auth_json(409, ["ok" => false, "error" => "Email already registered"]);
}
$check->close();

$password_hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $db->prepare(
    "INSERT INTO client_users (full_name, email, phone, password_hash, preferred_language)
     VALUES (?, ?, ?, ?, ?)"
);
$stmt->bind_param("sssss", $full_name, $email, $phone, $password_hash, $language);
$stmt->execute();
$user_id = (int)$stmt->insert_id;
$stmt->close();

$token = auth_issue_session($db, $user_id);

$get = $db->prepare("SELECT id, full_name, email, phone, preferred_language, created_at FROM client_users WHERE id = ? LIMIT 1");
$get->bind_param("i", $user_id);
$get->execute();
$user_result = $get->get_result();
$user_row = $user_result ? $user_result->fetch_assoc() : null;
$get->close();
$db->close();

if (!$user_row) {
    auth_json(500, ["ok" => false, "error" => "Registration succeeded but user payload failed"]);
}

auth_json(201, [
    "ok" => true,
    "token" => $token,
    "user" => auth_user_payload($user_row),
]);
