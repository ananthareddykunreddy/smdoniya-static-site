<?php
declare(strict_types=1);

require __DIR__ . "/config.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    admin_json(405, ["ok" => false, "error" => "Method not allowed"]);
}

$db = admin_db_connection();
admin_bootstrap_tables($db);
admin_bootstrap_default_user($db);
$admin = admin_require_user($db);
$db->close();

admin_json(200, ["ok" => true, "admin" => $admin]);
