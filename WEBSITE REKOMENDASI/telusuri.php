<?php
include_once 'configs.php';
include_once 'auth.php';
checkLogin();

// Use the $client to perform MongoDB operations
$db = $client->selectDatabase('DavDatabase');
$collection = $db->selectCollection('data_anime');

// Search functionality
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Pagination setup
$limit = 8;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$skip = ($page - 1) * $limit;

// Build query
$query = [];
if ($search_query) {
  $query = [
    '$or' => [
      ['title' => new MongoDB\BSON\Regex($search_query, 'i')],
      ['genres' => new MongoDB\BSON\Regex($search_query, 'i')],
    ]
  ];
}

$total = $collection->countDocuments($query);
$totalPages = ceil($total / $limit);

// Fetch documents with pagination
$cursor = $collection->find($query, [
  'limit' => $limit,
  'skip' => $skip,
]);

function get_pagination_links($current_page, $total_pages)
{
  $visible_links = 5;
  $links = "";

  $start = max(1, $current_page - floor($visible_links / 2));
  $end = min($total_pages, $current_page + floor($visible_links / 2));

  if ($start > 1) {
    $links .= "<li class='page-item'><a class='page-link' href='?page=1'>1</a></li>";
    if ($start > 2) {
      $links .= "<li class='page-item'><span class='page-link'>...</span></li>";
    }
  }

  for ($i = $start; $i <= $end; $i++) {
    if ($i == $current_page) {
      $links .= "<li class='page-item active'><span class='page-link'>$i</span></li>";
    } else {
      $links .= "<li class='page-item'><a class='page-link' href='?page=$i'>$i</a></li>";
    }
  }

  if ($end < $total_pages) {
    if ($end < $total_pages - 1) {
      $links .= "<li class='page-item'><span class='page-link'>...</span></li>";
    }
    $links .= "<li class='page-item'><a class='page-link' href='?page=$total_pages'>$total_pages</a></li>";
  }

  return $links;
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
              <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search_query); ?>">
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
      <div class="container">
        <div class="row mt-4">
          <?php foreach ($cursor as $doc) : ?>
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
        </div>
        <nav aria-label="Page navigation example">
          <ul class="pagination justify-content-center mt-4">
            <?php if ($page > 1) : ?>
              <li class="page-item">
                <a class="page-link" href="?page=<?= $page - 1; ?>&search=<?= urlencode($search_query); ?>" aria-label="Previous">
                  <span aria-hidden="true">&laquo;</span>
                </a>
              </li>
            <?php endif; ?>

            <?= get_pagination_links($page, $totalPages); ?>

            <?php if ($page < $totalPages) : ?>
              <li class="page-item">
                <a class="page-link" href="?page=<?= $page + 1; ?>&search=<?= urlencode($search_query); ?>" aria-label="Next">
                  <span aria-hidden="true">&raquo;</span>
                </a>
              </li>
            <?php endif; ?>
          </ul>
        </nav>
      </div>
    </section>
  </div>
  <script src="js/script.js"></script>
</body>

</html>