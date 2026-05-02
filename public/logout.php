<?php
// public/logout.php
session_start();
session_destroy();

// Redirect to landing page
header('Location: index.php');
exit;
