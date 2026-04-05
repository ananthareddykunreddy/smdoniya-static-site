<?php
declare(strict_types=1);

require __DIR__ . "/config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    auth_json(405, ["ok" => false, "error" => "Method not allowed"]);
}

$token = auth_bearer_token();
if ($token === "") {
    auth_json(401, ["ok" => false, "error" => "Missing token"]);
}

$token_hash = hash("sha256", $token);

$db = auth_db_connection();
auth_bootstrap_tables($db);
$stmt = $db->prepare("DELETE FROM client_sessions WHERE token_hash = ?");
$stmt->bind_param("s", $token_hash);
$stmt->execute();
$stmt->close();
$db->close();

auth_json(200, ["ok" => true]);
