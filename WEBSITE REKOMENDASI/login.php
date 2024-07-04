<?php
session_start();
include_once 'configs.php'; // Include the MongoDB connection configuration

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $username = $_POST['username'];
  $password = $_POST['password'];

  // Connect to MongoDB
  $db = $client->selectDatabase('DavDatabase');
  $collection = $db->selectCollection('user_data');

  // Fetch user data from MongoDB
  $user = $collection->findOne(['username' => $username]);

  if ($user && password_verify($password, $user['password'])) {
    // Password is correct
    $_SESSION['username'] = $username;
    $_SESSION['user_id'] = $user['user_id'];
    header('Location: index.php');
    exit();
  } else {
    $message = 'Invalid username or password';
  }
}
?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login</title>
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
                <h1 class="mb-3">Welcome Back!</h1>
                <p>Please login to your account</p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="card-body p-4">
                <h3 class="card-title text-center mb-4">Login</h3>
                <?php if ($message) : ?>
                  <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($message); ?>
                  </div>
                <?php endif; ?>
                <form method="POST" action="login.php">
                  <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                  </div>
                  <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                  </div>
                  <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary">Login</button>
                  </div>
                </form>
                <div class="text-center">
                  <p>New User? <a href="register.php">Signup</a></p>
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