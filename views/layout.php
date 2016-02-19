<!DOCTYPE html>
<html lang="en">
<head>
  <title><?= $this->e($title) ?></title>
  <link href="/assets/style.css" rel="stylesheet">
  <link href="/semantic-ui/semantic.min.css" rel="stylesheet">
  <script src="/assets/jquery-1.11.3.min.js"></script>
  <script src="/semantic-ui/semantic.min.js"></script>
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
