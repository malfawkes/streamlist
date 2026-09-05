<?php
// genres.php — Browse by Genre: every genre links to its filtered movie list

require_once 'includes/auth.php';
requireLogin();

require_once 'database/db.php';

// All genres + their movie counts (same GROUP BY as the chips)
 $genres = $pdo->query(
    'SELECT g.id, g.name, COUNT(mg.movie_id) AS movie_count
     FROM genres g
     INNER JOIN movie_genres mg ON mg.genre_id = g.id
     GROUP BY g.id, g.name
     ORDER BY g.name'
)->fetchAll(PDO::FETCH_ASSOC);

// Per genre: its 4 highest-rated movies (posters make the cards visual).
// One small query per genre — ~19 genres, trivial for the DB at this scale.
 $samples = [];
 $stmtSamples = $pdo->prepare(
    'SELECT m.id, m.title, m.poster_path
     FROM movies m
     INNER JOIN movie_genres mg ON mg.movie_id = m.id
     WHERE mg.genre_id = :gid
     ORDER BY m.rating DESC
     LIMIT 4'
);
foreach ($genres as $g) {
    $stmtSamples->bindValue(':gid', $g['id'], PDO::PARAM_INT);
    $stmtSamples->execute();
    $samples[(int) $g['id']] = $stmtSamples->fetchAll(PDO::FETCH_ASSOC);
}

include 'includes/header.php';
?>

<section class="wrap">

    <h1>Browse by Genre</h1>
    <p>Pick a mood each genre links straight to its movies.</p>

    <?php if (empty($genres)): ?>
        <p>No genres loaded yet run the seed script.</p>
    <?php else: ?>

    <div class="genre-cards">
        <?php foreach ($genres as $g): ?>
            <div class="card genre-card">
                <h2><?= htmlspecialchars($g['name']) ?></h2>
                <p><?= (int) $g['movie_count'] ?> movies</p>

                <div class="genre-posters">
                    <?php foreach ($samples[(int) $g['id']] as $s): ?>
                        <img src="<?= $s['poster_path']
                                ? 'https://image.tmdb.org/t/p/w92' . htmlspecialchars($s['poster_path'])
                                : 'assets/img/canvas.png' ?>"
                            alt="<?= htmlspecialchars($s['title']) ?>">
                    <?php endforeach; ?>
                </div>

                <a href="movies.php?genre=<?= (int) $g['id'] ?>" class="btn btn-primary">
                    See <?= htmlspecialchars($g['name']) ?> movies
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</section>
<?php include 'includes/footer.php'; ?>