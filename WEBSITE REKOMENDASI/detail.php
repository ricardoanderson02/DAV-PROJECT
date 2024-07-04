<?php
include_once 'configs.php';
include_once 'auth.php';
checkLogin();

// Use the $client to perform MongoDB operations
$db = $client->selectDatabase('DavDatabase');
$collection = $db->selectCollection('data_anime');
$ratings_collection = $db->selectCollection('data_ratings');

// Get the ID from the URL
$mal_id = isset($_GET['mal_id']) ? (int)$_GET['mal_id'] : 0;

// Fetch data from MongoDB based on the ID
$anime = $collection->findOne(['mal_id' => $mal_id]);

$user_id = $_SESSION['user_id'];
$rating = $ratings_collection->findOne(['user_id' => $user_id, 'anime_id' => $anime['_id']]);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $score = (int)$_POST['score'];
  $anime_id = $anime['_id'];

  if (isset($_POST['like_action']) && $_POST['like_action'] === 'like') {
    $result = $ratings_collection->insertOne([
      'score' => $score,
      'user_id' => $user_id,
      'anime_id' => $anime_id
    ]);
  } elseif (isset($_POST['like_action']) && $_POST['like_action'] === 'unlike') {
    $result = $ratings_collection->deleteOne([
      'user_id' => $user_id,
      'anime_id' => $anime_id
    ]);
  }

  header('Location: detail.php?mal_id=' . $anime['mal_id']);
  exit();
}
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
              <a href="<?= BASE_URL; ?>/">
                <i class='bx bx-home icon'></i>
                <span class="text nav-text">Home</span>
              </a>
            </li>
            <li class="nav-link">
              <a href="<?= BASE_URL; ?>/telusuri.php" class="active">
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
      <div class="container mt-4">
        <div class="card">
          <img src="<?= $anime['main_picture'] ?>" class="card-img-top img-fluid" alt="<?= $anime['title'] ?>" style="max-height: 400px; object-fit: cover;">
          <div class="card-body">
            <h3 class="card-title"><?= $anime['title'] ?></h3>
            <p class="text-muted">
              <strong>Score:</strong> <?= $anime['score'] ?> |
              <strong>Episodes:</strong> <?= $anime['episodes'] ?> |
              <strong>Type:</strong> <?= $anime['type'] ?>
            </p>
            <p>
              <strong>Genres:</strong> <?= $anime['genres'] ?>
            </p>
            <p>
              <strong>Studios:</strong> <?= $anime['studios'] ?>
            </p>
            <p>
              <strong>Aired:</strong> <?= $anime['aired_from'] ?> <?= $anime['aired_to'] != 'NaN' ? ' to ' . $anime['aired_to'] : '' ?>
            </p>
            <p><strong>Summary:</strong> <?= $anime['synopsis'] ?> </p>

            <?php if ($rating) : ?>
              <form method="POST">
                <input type="hidden" name="like_action" value="unlike">
                <button type="submit" class="btn btn-danger d-block w-100">
                  Remove from Like
                </button>
              </form>
            <?php else : ?>
              <button type="button" class="btn btn-primary d-block w-100" data-bs-toggle="modal" data-bs-target="#ratingModal">
                Beri Rating Anime
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>
  </div>

  <!-- Modal -->
  <div class="modal fade" id="ratingModal" tabindex="-1" aria-labelledby="ratingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="ratingModalLabel">Rate Anime</h5>
        </div>
        <div class="modal-body">
          <form id="ratingForm" method="POST">
            <input type="hidden" name="like_action" value="like">
            <div class="form-group">
              <label for="anime_title">Anime Title</label>
              <input type="text" value="<?= $anime['title'] ?>" class="form-control" id="anime_title" name="anime_title" readonly>
            </div>
            <div class="form-group mt-3">
              <label for="score">Your Score</label>
              <input type="number" class="form-control" name="score" min="1" max="10" required>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Submit Rating</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script src="js/script.js"></script>
</body>

</html>