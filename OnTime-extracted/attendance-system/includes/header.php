<?php
// $pageTitle should be set before including this file
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Attendance System') ?> - OnTime: A Web-Based Attendance Management System for AICS</title>
<link rel="stylesheet" href="<?= htmlspecialchars(appUrl('assets/css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
