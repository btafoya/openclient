<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= esc($title ?? 'openclient') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-gray-100 min-h-screen flex">

    <?= $this->include('layouts/partials/sidebar') ?>

    <div class="flex flex-col flex-1 min-h-screen">
        <?= $this->include('layouts/partials/header') ?>

        <main class="p-6 flex-1">
            <?= $this->renderSection('content') ?>
        </main>

        <?= $this->include('layouts/partials/footer') ?>
    </div>

</body>
</html>
