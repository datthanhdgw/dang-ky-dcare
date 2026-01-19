<?php
session_start();
session_destroy();
$centerId = $_GET['center'] ?? null;
header('Location: login.php?center='.$centerId);
exit;
