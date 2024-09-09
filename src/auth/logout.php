<?php
session_start();
session_unset();
session_destroy();
header("Location: /lab1/index.php");
exit();
?>