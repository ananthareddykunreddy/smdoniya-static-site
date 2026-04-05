<?php
declare(strict_types=1);

require __DIR__ . "/config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    auth_json(405, ["ok" => false, "error" => "Method not allowed"]);
}

$data = auth_json_body();
$email = strtolower(trim((string)($data["email"] ?? "")));
$password = (string)($data["password"] ?? "");

if ($email === "" || $password === "") {
    auth_json(400, ["ok" => false, "error" => "Missing required fields"]);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    auth_json(400, ["ok" => false, "error" => "Invalid email"]);
}

$db = auth_db_connection();
auth_bootstrap_tables($db);

$stmt = $db->prepare(
    "SELECT id, full_name, email, phone, preferred_language, created_at, password_hash
     FROM client_users
     WHERE email = ?
     LIMIT 1"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user_row = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$user_row || !password_verify($password, (string)$user_row["password_hash"])) {
    $db->close();
    auth_json(401, ["ok" => false, "error" => "Invalid email or password"]);
}

$token = auth_issue_session($db, (int)$user_row["id"]);
$db->close();

unset($user_row["password_hash"]);

auth_json(200, [
    "ok" => true,
    "token" => $token,
    "user" => auth_user_payload($user_row),
]);
