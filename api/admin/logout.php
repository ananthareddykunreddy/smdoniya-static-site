<?php
declare(strict_types=1);

require __DIR__ . "/config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    admin_json(405, ["ok" => false, "error" => "Method not allowed"]);
}

$token = admin_bearer_token();
if ($token === "") {
    admin_json(401, ["ok" => false, "error" => "Missing token"]);
}

$token_hash = hash("sha256", $token);
$db = admin_db_connection();
admin_bootstrap_tables($db);

$stmt = $db->prepare("DELETE FROM admin_sessions WHERE token_hash = ?");
$stmt->bind_param("s", $token_hash);
$stmt->execute();
$stmt->close();
$db->close();

admin_json(200, ["ok" => true]);
