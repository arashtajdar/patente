<?php
session_start();
unset($_SESSION['quiz']);
header('Location: index.php');
exit;


