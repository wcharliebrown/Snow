<?php
/**
 * Logout script
 */

// Log the user out
logoutUser();

// Redirect to home page
header('Location: /');
exit;
?>