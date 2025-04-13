<!-- For Password generation   -->

<?php
$password = "123"; // Example password
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

echo $hashedPassword;
?>



