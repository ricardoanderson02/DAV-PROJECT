<?php
include_once 'configs.php';
include_once 'auth.php';
checkLogin();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
// Get the current user's ID
$user_id = $_SESSION['user_id'];

// Fetch recommendations from the first API (GET request)
$ch1 = curl_init();
curl_setopt($ch1, CURLOPT_URL, "http://127.0.0.1:5001/recommend?user_id=" . $user_id);
curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
$response1 = curl_exec($ch1);
curl_close($ch1);


// Decode the JSON response
$recommendations1 = json_decode($response1, true);

// Fetch recommendations from the second API (POST request)
$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, "http://127.0.0.1:5002/userRecommendations");
curl_setopt($ch2, CURLOPT_POST, 1);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
$data = json_encode(array('user_id' => $user_id));
curl_setopt($ch2, CURLOPT_POSTFIELDS, $data);
$response2 = curl_exec($ch2);
curl_close($ch2);

// Decode the JSON response
$recommendations2 = json_decode($response2, true);


?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Anime AI</title>
  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Custom CSS -->
  <link rel="stylesheet" href="css/style.css">
  <!-- Box Icon -->
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
</head>
<body>
  <div class="container-fluid d-flex flex-column flex-md-row p-0">
    <nav class="sidebar">
      <header>
        <div class="image-text">
          <span class="image">
            <img src="img/logo.png" alt="">
          </span>
          <div class="text logo-text">
            <span class="name">Anime AI</span>
            <span class="profession"><?= $_SESSION['username']; ?></span>
          </div>
        </div>
        <i class='bx bx-chevron-right toggle'></i>
      </header>
      <div class="menu-bar">
        <div class="menu">
          <li class="search-box">
            <form action="telusuri.php" method="GET" class="d-flex">
              <input type="text" name="search" class="form-control" placeholder="Search..." value="">
              <button type="submit" class="btn btn-primary ms-2">Search</button>
            </form>
          </li>
          <ul class="menu-links">
            <li class="nav-link">
              <a href="<?= BASE_URL; ?>/" class="active">
                <i class='bx bx-home icon'></i>
                <span class="text nav-text">Home</span>
              </a>
            </li>
            <li class="nav-link">
              <a href="<?= BASE_URL; ?>/telusuri.php">
                <i class='bx bx-globe icon'></i>
                <span class="text nav-text">Telusuri</span>
              </a>
            </li>
            <li class="nav-link">
              <a href="<?= BASE_URL; ?>/analitik.php">
                <i class='bx bx-pie-chart-alt icon'></i>
                <span class="text nav-text">Analitik & Trend</span>
              </a>
            </li>
            <li class="nav-link">
              <a href="<?= BASE_URL; ?>/favorit.php">
                <i class='bx bxs-heart-circle icon'></i>
                <span class="text nav-text">Favoritku</span>
              </a>
            </li>
          </ul>
        </div>
        <div class="bottom-content">
          <li>
            <a href="<?= BASE_URL ?>/logout.php">
              <i class='bx bx-log-out icon'></i>
              <span class="text nav-text">Logout</span>
            </a>
          </li>
          <li class="mode">
            <div class="sun-moon">
              <i class='bx bx-moon icon moon'></i>
              <i class='bx bx-sun icon sun'></i>
            </div>
            <span class="mode-text text">Dark mode</span>
            <div class="toggle-switch">
              <span class="switch"></span>
            </div>
          </li>
        </div>
      </div>
    </nav>
    <section class="home flex-grow-1 p-3">
      <div class="container">
        <div class="row mt-4">
          <h4>Reccommendation from other maybe same like you?</h4>
        </div>
        <div class="container">
            <div class="row mt-4" id="recommendations">
              <?php if (!empty($recommendations1)): ?>
                <?php foreach ($recommendations1 as $doc) : ?>
                  <div class="col-lg-3 d-flex align-items-stretch">
                    <div class="card mt-2" style="width: 18rem;">
                      <img src="<?= $doc['main_picture']; ?>" class="card-img-top" style="max-height:150px; object-fit: cover;" alt="...">
                      <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?= htmlspecialchars($doc['title']); ?></h5>
                        <p class="card-text"><?= htmlspecialchars(mb_strlen($doc['synopsis']) > 100 ? mb_substr($doc['synopsis'], 0, 100) . '...' : $doc['synopsis']); ?></p>
                        <a href="<?= BASE_URL; ?>/detail.php?mal_id=<?= $doc['mal_id'] ?>" class="btn btn-primary mt-auto">Baca Lebih Lanjut</a>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <p>No recommendations available.</p>
              <?php endif; ?>
            </div>

        <div class="row mt-4">
          <h4>Recommendations from all the anime you like</h4>

          <div class="container">
            <div class="row mt-4" id="recommendations">
              <?php if (!empty($recommendations2)): ?>
                <?php foreach ($recommendations2 as $doc) : ?>
                  <div class="col-lg-3 d-flex align-items-stretch">
                    <div class="card mt-2" style="width: 18rem;">
                      <img src="<?= $doc['main_picture']; ?>" class="card-img-top" style="max-height:150px; object-fit: cover;" alt="...">
                      <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?= htmlspecialchars($doc['title']); ?></h5>
                        <p class="card-text"><?= htmlspecialchars(mb_strlen($doc['synopsis']) > 100 ? mb_substr($doc['synopsis'], 0, 100) . '...' : $doc['synopsis']); ?></p>
                        <a href="<?= BASE_URL; ?>/detail.php?mal_id=<?= $doc['mal_id'] ?>" class="btn btn-primary mt-auto">Baca Lebih Lanjut</a>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <p>No recommendations available.</p>
              <?php endif; ?>
            </div>
            <nav aria-label="Page navigation example">
              <ul class="pagination justify-content-center mt-4">
              </ul>
            </nav>
          </div>
        </div>
      </div>
    </section>
  </div>
</body>
</html>
