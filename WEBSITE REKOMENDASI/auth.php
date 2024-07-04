<?php
function checkLogin()
{
  session_start();
  if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
  }
}
