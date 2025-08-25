<?php
session_start();
unset($_SESSION['quiz']);
header('Location: test.php');
exit;


