<!DOCTYPE html>
<html lang="en">
<head>
  <title><?= $this->e($title) ?></title>
  <link href="/assets/style.css" rel="stylesheet">
  <script src="/assets/script.js"></script>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>

<div id="page-content">
  <?= $this->section('content') ?>
</div>

<footer>
  <a href="https://indiewebcamp.com/Percolator">What is Percolator?</a>
</footer>

</body>
</html>
