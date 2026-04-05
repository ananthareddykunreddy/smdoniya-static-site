<?php
declare(strict_types=1);

require __DIR__ . "/config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    admin_json(405, ["ok" => false, "error" => "Method not allowed"]);
}

$data = admin_json_body();
$email = strtolower(trim((string)($data["email"] ?? "")));
$password = (string)($data["password"] ?? "");

if ($email === "" || $password === "") {
    admin_json(400, ["ok" => false, "error" => "Missing required fields"]);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    admin_json(400, ["ok" => false, "error" => "Invalid email"]);
}

$db = admin_db_connection();
admin_bootstrap_tables($db);
admin_bootstrap_default_user($db);

$stmt = $db->prepare("SELECT id, full_name, email, password_hash FROM admin_users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row || !password_verify($password, (string)$row["password_hash"])) {
    $db->close();
    admin_json(401, ["ok" => false, "error" => "Invalid email or password"]);
}

$token = admin_issue_session($db, (int)$row["id"]);
$db->close();

admin_json(200, [
    "ok" => true,
    "token" => $token,
    "admin" => [
        "id" => (int)$row["id"],
        "full_name" => $row["full_name"],
        "email" => $row["email"],
    ],
]);
