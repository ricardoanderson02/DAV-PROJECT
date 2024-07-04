<?php
include_once 'configs.php';
include_once 'auth.php';
checkLogin();
// Set a higher execution time limit
set_time_limit(60);

// Use the $client to perform MongoDB operations
$db = $client->selectDatabase('DavDatabase');
$animeCollection = $db->selectCollection('data_anime_limited');

// Fetch all anime data
$animes = $animeCollection->find();

$animeByYear = [];
foreach ($animes as $anime) {
  if (isset($anime['aired_from']) && strtotime($anime['aired_from']) !== false) {
    $year = (new DateTime($anime['aired_from']))->format('Y');
    if ($year > 2013) {
      if (!isset($animeByYear[$year])) {
        $animeByYear[$year] = 0;
      }
      $animeByYear[$year]++;
    }
  }
}

$animeData = [];
foreach ($animeByYear as $year => $count) {
  $animeData[] = ['year' => $year, 'count' => $count];
}

// Sort data by year
usort($animeData, function ($a, $b) {
  return $a['year'] - $b['year'];
});
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
  <script src="https://d3js.org/d3.v7.min.js"></script>
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
              <a href="<?= BASE_URL; ?>/telusuri.php">
                <i class='bx bx-globe icon'></i>
                <span class="text nav-text">Telusuri</span>
              </a>
            </li>
            <li class="nav-link">
              <a href="<?= BASE_URL; ?>/analitik.php" class="active">
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
          <h4>Analitik dan Trend Anime</h4>
          <div id="container"></div>
        </div>
      </div>
    </section>
  </div>
  <script src="js/script.js"></script>
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const animeData = <?= json_encode($animeData); ?>;

      const margin = {
        top: 20,
        right: 30,
        bottom: 30,
        left: 40
      };
      const width = 960 - margin.left - margin.right;
      const height = 500 - margin.top - margin.bottom;

      const svg = d3.select("#container").append("svg")
        .attr("width", width + margin.left + margin.right)
        .attr("height", height + margin.top + margin.bottom)
        .append("g")
        .attr("transform", `translate(${margin.left},${margin.top})`);

      const x = d3.scalePoint()
        .domain(animeData.map(d => d.year))
        .range([0, width]);

      const y = d3.scaleLinear()
        .domain([0, d3.max(animeData, d => d.count)])
        .nice()
        .range([height, 0]);

      const line = d3.line()
        .x(d => x(d.year))
        .y(d => y(d.count));

      svg.append("g")
        .attr("class", "x-axis")
        .attr("transform", `translate(0,${height})`)
        .call(d3.axisBottom(x));

      svg.append("g")
        .attr("class", "y-axis")
        .call(d3.axisLeft(y));

      svg.append("path")
        .datum(animeData)
        .attr("fill", "none")
        .attr("stroke", "steelblue")
        .attr("stroke-width", 1.5)
        .attr("d", line);

      svg.selectAll(".dot")
        .data(animeData)
        .enter().append("circle")
        .attr("class", "dot")
        .attr("cx", d => x(d.year))
        .attr("cy", d => y(d.count))
        .attr("r", 5)
        .attr("fill", "steelblue");
    });
  </script>
</body>

</html>