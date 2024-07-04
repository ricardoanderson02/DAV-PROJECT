<?php
include_once 'configs.php';
include_once 'auth.php';
checkLogin();

// Connect to MongoDB and select your database
$db = $client->selectDatabase('DavDatabase');
$animeCollection = $db->selectCollection('data_anime_limited');

// Fetch data from MongoDB
$animes = $animeCollection->find();

$animeByYear = [];
$genreData = [];

foreach ($animes as $anime) {
  if (isset($anime['aired_from']) && strtotime($anime['aired_from']) !== false) {
    $year = date('Y', strtotime($anime['aired_from']));
    $animeByYear[$year] = ($animeByYear[$year] ?? 0) + 1;
  }

  if (isset($anime['genres']) && is_array($anime['genres'])) {
    foreach ($anime['genres'] as $genre) {
      $genreData[$genre] = ($genreData[$genre] ?? 0) + 1;
    }
  }
}

$lineChartData = array_map(function ($year, $count) {
  return ['year' => $year, 'count' => $count];
}, array_keys($animeByYear), $animeByYear);

$pieChartData = array_map(function ($genre, $count) {
  return ['genre' => $genre, 'count' => $count];
}, array_keys($genreData), $genreData);

// Sort data by year for the line chart
usort($lineChartData, function ($a, $b) {
  return $a['year'] <=> $b['year'];
});

// Convert data to JSON for JavaScript consumption
$lineChartDataJson = json_encode($lineChartData);
$pieChartDataJson = json_encode($pieChartData);

?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Anime Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://d3js.org/d3.v7.min.js"></script>
</head>

<body>
  <div class="container">
    <div class="row">
      <div class="col-md-8" id="lineChart"></div>
      <div class="col-md-4" id="pieChart"></div>
      <div class="col-md-6" id="barChart"></div>
      <div class="col-md-6" id="anotherChart"></div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const lineData = JSON.parse('<?= $lineChartDataJson; ?>');
      const pieData = JSON.parse('<?= $pieChartDataJson; ?>');

      // Render Line Chart Example
      const margin = {
          top: 20,
          right: 20,
          bottom: 30,
          left: 50
        },
        width = 600 - margin.left - margin.right,
        height = 400 - margin.top - margin.bottom;

      const svgLine = d3.select('#lineChart').append('svg')
        .attr('width', width + margin.left + margin.right)
        .attr('height', height + margin.top + margin.bottom)
        .append('g')
        .attr('transform', 'translate(' + margin.left + ',' + margin.top + ')');

      const x = d3.scaleBand()
        .domain(lineData.map(d => d.year))
        .range([0, width])
        .padding(0.1);

      const y = d3.scaleLinear()
        .domain([0, d3.max(lineData, d => d.count)])
        .range([height, 0]);

      svgLine.append('g')
        .attr('transform', 'translate(0,' + height + ')')
        .call(d3.axisBottom(x));

      svgLine.append('g')
        .call(d3.axisLeft(y));

      svgLine.append('path')
        .datum(lineData)
        .attr('fill', 'none')
        .attr('stroke', 'steelblue')
        .attr('stroke-width', 1.5)
        .attr('d', d3.line()
          .x(d => x(d.year))
          .y(d => y(d.count)));

      // Render Pie Chart Example
      const radius = Math.min(width, height) / 2;
      const svgPie = d3.select('#pieChart').append('svg')
        .attr('width', width)
        .attr('height', height)
        .append('g')
        .attr('transform', 'translate(' + width / 2 + ',' + height / 2 + ')');

      const pie = d3.pie()
        .value(d => d.count);

      const dataReady = pie(pieData);

      const arc = d3.arc()
        .innerRadius(0)
        .outerRadius(radius);

      svgPie.selectAll('path')
        .data(dataReady)
        .enter()
        .append('path')
        .attr('d', arc)
        .attr('fill', (d, i) => d3.schemeCategory10[i % 10]);

      // Render Bar Chart Example
      const svgBar = d3.select('#barChart').append('svg')
        .attr('width', width)
        .attr('height', height)
        .append('g')
        .attr('transform', 'translate(' + margin.left + ',' + margin.top + ')');

      svgBar.append('g')
        .attr('transform', 'translate(0,' + height + ')')
        .call(d3.axisBottom(x));

      svgBar.append('g')
        .call(d3.axisLeft(y));

      svgBar.selectAll('.bar')
        .data(lineData)
        .enter()
        .append('rect')
        .attr('class', 'bar')
        .attr('x', d => x(d.year))
        .attr('y', d => y(d.count))
        .attr('width', x.bandwidth())
        .attr('height', d => height - y(d.count))
        .attr('fill', 'steelblue');
    });
  </script>
</body>

</html>