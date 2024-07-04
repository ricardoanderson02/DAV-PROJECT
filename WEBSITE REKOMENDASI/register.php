<?php
session_start();
include_once 'configs.php'; // Include the MongoDB connection configuration

$message = '';

function generateUniqueUserId()
{
  // Generate a unique user ID using the current timestamp and a random number
  return time() . rand(1000, 9999);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $username = $_POST['username'];
  $password = $_POST['password'];
  $confirm_password = $_POST['confirm_password'];

  if ($password !== $confirm_password) {
    $message = 'Passwords do not match!';
  } else {
    // Connect to MongoDB
    $db = $client->selectDatabase('DavDatabase');
    $collection = $db->selectCollection('user_data');

    // Check if the username already exists
    $existingUser = $collection->findOne(['username' => $username]);

    if ($existingUser) {
      $message = 'Username already exists!';
    } else {
      // Generate a unique user ID
      $userId = generateUniqueUserId();

      // Hash the password
      $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

      // Insert the new user into MongoDB
      $result = $collection->insertOne([
        'username' => $username,
        'password' => $hashedPassword,
        'user_id' => $userId
      ]);

      if ($result->getInsertedCount() === 1) {
      
        header('Location: login.php');
        exit();
      } else {
        $message = 'Registration failed. Please try again.';
      }
    }
  }
}
?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
  <div class="container">
    <div class="row justify-content-center align-items-center vh-100">
      <div class="col-md-10 col-lg-8 col-xl-6">
        <div class="card">
          <div class="row g-0">
            <div class="col-md-6 d-none d-md-flex align-items-center justify-content-center bg-primary text-white p-5">
              <div class="text-center">
                <h1 class="mb-3">Welcome Aboard!</h1>
                <p>Create a new account here</p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="card-body p-4">
                <h3 class="card-title text-center mb-4">Register</h3>
                <?php if ($message) : ?>
                  <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($message); ?>
                  </div>
                <?php endif; ?>
                <form method="POST" action="register.php">
                  <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                  </div>
                  <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                  </div>
                  <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                  </div>
                  <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary">Register</button>
                  </div>
                </form>
                <div class="text-center">
                  <p>Already have an account? <a href="login.php">Login</a></p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>