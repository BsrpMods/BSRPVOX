<?php
session_start();
function requireAdmin() {
    if (empty($_SESSION['admin_id'])) {
        die(json_encode(["success" => false, "error" => "UNAUTHORIZED"]));
    }
}
