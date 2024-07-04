<?php
include_once 'configs.php';
include_once 'auth.php';
checkLogin();

// Use the $client to perform MongoDB operations
$db = $client->selectDatabase('DavDatabase');
$animeCollection = $db->selectCollection('data_anime');
$ratingsCollection = $db->selectCollection('data_ratings');

// Fetch all anime data
$animes = $animeCollection->find();

$animeByYear = [];
$genreCount = [];
$ratingCount = [];

// Function to clean rating string
function clean_rating($rating)
{
  $rating_parts = explode(' ', $rating);
  return $rating_parts[0];
}

// Process data
foreach ($animes as $anime) {
  if (isset($anime['aired_from']) && strtotime($anime['aired_from']) !== false) {
    $year = (new DateTime($anime['aired_from']))->format('Y');
    if ($year > 2018) {
      if (!isset($animeByYear[$year])) {
        $animeByYear[$year] = 0;
      }
      $animeByYear[$year]++;
    }
  }

  if (isset($anime['genres'])) {
    $genres = json_decode(str_replace("'", '"', $anime['genres']));
    foreach ($genres as $genre) {
      if (!isset($genreCount[$genre])) {
        $genreCount[$genre] = 0;
      }
      $genreCount[$genre]++;
    }
  }

  if (isset($anime['rating'])) {
    $rating = clean_rating($anime['rating']);
    if (!isset($ratingCount[$rating])) {
      $ratingCount[$rating] = 0;
    }
    $ratingCount[$rating]++;
  }

  if (isset($anime['premiered_season'])) {
    $season = strtolower($anime['premiered_season']);
    if (!isset($seasonCount[$season])) {
      $seasonCount[$season] = 0;
    }
    $seasonCount[$season]++;
  }
}

// Find the top three most liked anime
$mostLikedAnimeCursor = $ratingsCollection->aggregate([
  [
    '$group' => [
      '_id' => '$anime_id',
      'count' => ['$sum' => 1]
    ]
  ],
  [
    '$sort' => ['count' => -1]
  ],
  [
    '$limit' => 3
  ]
]);

$mostLikedAnimes = iterator_to_array($mostLikedAnimeCursor);

$topAnimes = [];
foreach ($mostLikedAnimes as $likedAnime) {
  $animeDetails = $animeCollection->findOne(['_id' => $likedAnime['_id']]);
  if ($animeDetails && isset($animeDetails['genres'])) {
    $animeDetails['genres'] = json_decode(str_replace("'", '"', $animeDetails['genres']));
    $topAnimes[] = [
      'title' => $animeDetails['title'],
      'genres' => $animeDetails['genres'],
      'score' => $animeDetails['score'],
      'count' => $likedAnime['count']
    ];
  }
}

// Find the top three most liked genres
$allLikedGenres = [];
$allLikedAnimesCursor = $ratingsCollection->find();

foreach ($allLikedAnimesCursor as $likedAnime) {
  $anime_id = $likedAnime['anime_id'];
  $anime = $animeCollection->findOne(['_id' => $anime_id]);
  if ($anime && isset($anime['genres'])) {
    $genres = json_decode(str_replace("'", '"', $anime['genres']));
    foreach ($genres as $genre) {
      if (!isset($allLikedGenres[$genre])) {
        $allLikedGenres[$genre] = 0;
      }
      $allLikedGenres[$genre]++;
    }
  }
}

arsort($allLikedGenres);
$topGenres = array_slice($allLikedGenres, 0, 3, true);


$animeData = [];
foreach ($animeByYear as $year => $count) {
  $animeData[] = ['year' => $year, 'count' => $count];
}

$genreData = [];
$otherCount = 0;
foreach ($genreCount as $genre => $count) {
  if ($count < 1000) {
    $otherCount += $count;
  } else {
    $genreData[] = ['genre' => $genre, 'count' => $count];
  }
}
if ($otherCount > 0) {
  $genreData[] = ['genre' => 'Other', 'count' => $otherCount];
}

$ratingData = [];
foreach ($ratingCount as $rating => $count) {
  if ($rating != 'NAN') {
    $ratingData[] = ['rating' => $rating, 'count' => $count];
  }
}

$seasonData = [];
foreach ($seasonCount as $season => $count) {
  if (ucfirst($season) != 'Nan') {
    $seasonData[] = ['season' => ucfirst($season), 'count' => $count];
  }
}

// Convert data to JSON for JavaScript consumption
$animeDataJson = json_encode($animeData);
$genreDataJson = json_encode($genreData);
$ratingDataJson = json_encode($ratingData);
$seasonDataJson = json_encode($seasonData);
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
  <style>
    .chart-container {
      margin: 20px 0;
    }

    .x-axis text,
    .y-axis text {
      font-size: 12px;
    }

    .x-axis path,
    .x-axis line,
    .y-axis path,
    .y-axis line {
      stroke: #ddd;
    }

    .bar {
      fill: steelblue;
    }

    .dot {
      fill: steelblue;
    }

    .tooltip {
      position: absolute;
      text-align: center;
      padding: 6px;
      font-size: 12px;
      background: white;
      border: 1px solid #ddd;
      pointer-events: none;
    }
  </style>
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
          <div class="col-lg-12 chart-container">
            <h4>Total Anime per Tahun</h4>
            <div id="line_chart"></div>
          </div>
          <div class="col-lg-6 chart-container">
            <h4>Distribusi Anime per Genre</h4>
            <div id="pie_chart"></div>
          </div>
          <div class="col-lg-6 chart-container">
            <h4>Anime Release Count by Season</h4>
            <div id="season_chart"></div>
          </div>
          <div class="col-lg-12 chart-container">
            <h4>Anime Rating Distribution</h4>
            <div id="bar_chart"></div>
          </div>

          <div class="col-lg-6">
            <h4>Top 3 Most Liked Anime</h4>
            <?php foreach ($topAnimes as $anime) : ?>
              <p><strong>Title:</strong> <?= $anime['title']; ?></p>
              <p><strong>Genres:</strong> <?= implode(', ', $anime['genres']); ?></p>
              <p><strong>Score:</strong> <?= $anime['score']; ?></p>
              <p><strong>Number of People Liking:</strong> <?= $anime['count']; ?></p>
              <hr>
            <?php endforeach; ?>
          </div>
          <div class="col-lg-6">
            <h4>Top 3 Most Liked Genres</h4>
            <?php foreach ($topGenres as $genre => $count) : ?>
              <p><strong>Genre:</strong> <?= $genre; ?></p>
              <p><strong>Count:</strong> <?= $count; ?></p>
              <hr>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>
  </div>
  <script src="js/script.js"></script>
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const animeData = <?= $animeDataJson; ?>;
      const genreData = <?= $genreDataJson; ?>;
      const ratingsData = <?= $ratingDataJson; ?>;
      const seasonData = <?= $seasonDataJson; ?>;

      // Tooltip
      const tooltip = d3.select("body").append("div")
        .attr("class", "tooltip")
        .style("opacity", 0);

      // Line Chart - Total Anime per Tahun
      const lineMargin = {
        top: 20,
        right: 30,
        bottom: 30,
        left: 40
      };
      const lineWidth = 1000 - lineMargin.left - lineMargin.right;
      const lineHeight = 500 - lineMargin.top - lineMargin.bottom;

      const svgLine = d3.select("#line_chart").append("svg")
        .attr("width", lineWidth + lineMargin.left + lineMargin.right)
        .attr("height", lineHeight + lineMargin.top + lineMargin.bottom)
        .append("g")
        .attr("transform", `translate(${lineMargin.left},${lineMargin.top})`);

      const x = d3.scalePoint()
        .domain(animeData.map(d => d.year))
        .range([0, lineWidth]);

      const y = d3.scaleLinear()
        .domain([0, d3.max(animeData, d => d.count)])
        .nice()
        .range([lineHeight, 0]);

      const line = d3.line()
        .x(d => x(d.year))
        .y(d => y(d.count));

      svgLine.append("g")
        .attr("class", "x-axis")
        .attr("transform", `translate(0,${lineHeight})`)
        .call(d3.axisBottom(x));

      svgLine.append("g")
        .attr("class", "y-axis")
        .call(d3.axisLeft(y));

      svgLine.append("path")
        .datum(animeData)
        .attr("fill", "none")
        .attr("stroke", "steelblue")
        .attr("stroke-width", 1.5)
        .attr("d", line);

      svgLine.selectAll(".dot")
        .data(animeData)
        .enter().append("circle")
        .attr("class", "dot")
        .attr("cx", d => x(d.year))
        .attr("cy", d => y(d.count))
        .attr("r", 5)
        .on("mouseover", function(event, d) {
          tooltip.transition().duration(200).style("opacity", .9);
          tooltip.html(`Year: ${d.year}<br/>Count: ${d.count}`)
            .style("left", (event.pageX + 5) + "px")
            .style("top", (event.pageY - 28) + "px");
        })
        .on("mouseout", function() {
          tooltip.transition().duration(500).style("opacity", 0);
        });

      // Pie Chart - Distribusi Anime per Genre
      const pieWidth = 500,
        pieHeight = 500,
        pieRadius = Math.min(pieWidth, pieHeight) / 2;

      const svgPie = d3.select("#pie_chart").append("svg")
        .attr("width", pieWidth)
        .attr("height", pieHeight)
        .append("g")
        .attr("transform", `translate(${pieWidth / 2},${pieHeight / 2})`);

      const pie = d3.pie().value(d => d.count);

      const arc = d3.arc().innerRadius(0).outerRadius(pieRadius - 1);

      const arcLabel = d3.arc().innerRadius(pieRadius - 40).outerRadius(pieRadius - 40);

      const arcs = pie(genreData);

      svgPie.selectAll("path")
        .data(arcs)
        .enter().append("path")
        .attr("fill", (d, i) => d3.schemeCategory10[i % 10])
        .attr("stroke", "white")
        .attr("d", arc)
        .on("mouseover", function(event, d) {
          tooltip.transition().duration(200).style("opacity", .9);
          tooltip.html(`${d.data.genre}<br/>Count: ${d.data.count}`)
            .style("left", (event.pageX + 5) + "px")
            .style("top", (event.pageY - 28) + "px");
        })
        .on("mouseout", function() {
          tooltip.transition().duration(500).style("opacity", 0);
        });

      svgPie.selectAll("text")
        .data(arcs)
        .enter().append("text")
        .attr("transform", d => `translate(${arcLabel.centroid(d)})`)
        .attr("dy", "0.35em")
        .attr("text-anchor", "middle")
        .attr("font-size", "10px")
        .text(d => `${d.data.genre} (${d.data.count})`);

      // Bar Chart - Anime Rating Distribution
      const barMargin = {
        top: 20,
        right: 30,
        bottom: 30,
        left: 40
      };
      const barWidth = 1000 - barMargin.left - barMargin.right;
      const barHeight = 500 - barMargin.top - barMargin.bottom;

      const svgBar = d3.select("#bar_chart").append("svg")
        .attr("width", barWidth + barMargin.left + barMargin.right)
        .attr("height", barHeight + barMargin.top + barMargin.bottom)
        .append("g")
        .attr("transform", `translate(${barMargin.left},${barMargin.top})`);

      // const ratingsData = [{
      //     rating: "G",
      //     count: 10
      //   },
      //   {
      //     rating: "PG",
      //     count: 20
      //   },
      //   {
      //     rating: "PG-13",
      //     count: 30
      //   },
      //   {
      //     rating: "R",
      //     count: 25
      //   },
      //   {
      //     rating: "NC-17",
      //     count: 15
      //   }
      // ];

      const xBar = d3.scaleBand()
        .domain(ratingsData.map(d => d.rating))
        .range([0, barWidth])
        .padding(0.1);

      const yBar = d3.scaleLinear()
        .domain([0, d3.max(ratingsData, d => d.count)])
        .nice()
        .range([barHeight, 0]);

      svgBar.append("g")
        .attr("class", "x-axis")
        .attr("transform", `translate(0,${barHeight})`)
        .call(d3.axisBottom(xBar));

      svgBar.append("g")
        .attr("class", "y-axis")
        .call(d3.axisLeft(yBar));

      svgBar.selectAll(".bar")
        .data(ratingsData)
        .enter().append("rect")
        .attr("class", "bar")
        .attr("x", d => xBar(d.rating))
        .attr("y", d => yBar(d.count))
        .attr("width", xBar.bandwidth())
        .attr("height", d => barHeight - yBar(d.count))
        .on("mouseover", function(event, d) {
          tooltip.transition().duration(200).style("opacity", .9);
          tooltip.html(`Rating: ${d.rating}<br/>Count: ${d.count}`)
            .style("left", (event.pageX + 5) + "px")
            .style("top", (event.pageY - 28) + "px");
        })
        .on("mouseout", function() {
          tooltip.transition().duration(500).style("opacity", 0);
        });

      // Season Chart - Anime Release Count by Season
      // const seasonData = [{
      //     season: "Winter",
      //     count: 25
      //   },
      //   {
      //     season: "Spring",
      //     count: 30
      //   },
      //   {
      //     season: "Summer",
      //     count: 20
      //   },
      //   {
      //     season: "Fall",
      //     count: 25
      //   }
      // ];

      const seasonMargin = {
        top: 20,
        right: 30,
        bottom: 30,
        left: 40
      };
      const seasonWidth = 500 - seasonMargin.left - seasonMargin.right;
      const seasonHeight = 500 - seasonMargin.top - seasonMargin.bottom;

      const svgSeason = d3.select("#season_chart").append("svg")
        .attr("width", seasonWidth + seasonMargin.left + seasonMargin.right)
        .attr("height", seasonHeight + seasonMargin.top + seasonMargin.bottom)
        .append("g")
        .attr("transform", `translate(${seasonMargin.left},${seasonMargin.top})`);

      const xSeason = d3.scaleBand()
        .domain(seasonData.map(d => d.season))
        .range([0, seasonWidth])
        .padding(0.1);

      const ySeason = d3.scaleLinear()
        .domain([0, d3.max(seasonData, d => d.count)])
        .nice()
        .range([seasonHeight, 0]);

      svgSeason.append("g")
        .attr("class", "x-axis")
        .attr("transform", `translate(0,${seasonHeight})`)
        .call(d3.axisBottom(xSeason));

      svgSeason.append("g")
        .attr("class", "y-axis")
        .call(d3.axisLeft(ySeason));

      svgSeason.selectAll(".bar")
        .data(seasonData)
        .enter().append("rect")
        .attr("class", "bar")
        .attr("x", d => xSeason(d.season))
        .attr("y", d => ySeason(d.count))
        .attr("width", xSeason.bandwidth())
        .attr("height", d => seasonHeight - ySeason(d.count))
        .on("mouseover", function(event, d) {
          tooltip.transition().duration(200).style("opacity", .9);
          tooltip.html(`Rating: ${d.rating}<br/>Count: ${d.count}`)
            .style("left", (event.pageX + 5) + "px")
            .style("top", (event.pageY - 28) + "px");
        })
        .on("mouseout", function() {
          tooltip.transition().duration(500).style("opacity", 0);
        });
    });
  </script>
</body>

</html>