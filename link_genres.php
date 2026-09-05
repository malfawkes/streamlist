<?php
// link_genres.php — backfills movie_genres for existing movies.
// Re-run safe: movies that already have links are skipped.

set_time_limit(0);   // ~847 API calls ahead

require_once 'database/configHidden.php';
require_once 'database/db.php';

// ── Stage 1: genre dictionary (harmless if already filled) ────────
 $data = json_decode(@file_get_contents(
    'https://api.themoviedb.org/3/genre/movie/list?api_key=' . TMDB_API_KEY
), true);

 $stmtG = $pdo->prepare('INSERT IGNORE INTO genres (id, name) VALUES (:id, :name)');
foreach ($data['genres'] ?? [] as $g) {
    $stmtG->bindValue(':id', $g['id'], PDO::PARAM_INT);
    $stmtG->bindValue(':name', $g['name']);
    $stmtG->execute();
}
echo '<p>Genre dictionary loaded.</p>';

// ── Stage 2: only movies with ZERO links so far ───────────────────
// NOT EXISTS = "there is no row in movie_genres for this movie"
 $moviesList = $pdo->query(
    'SELECT m.id, m.tmdb_id
     FROM movies m
     WHERE NOT EXISTS (SELECT 1 FROM movie_genres mg WHERE mg.movie_id = m.id)'
)->fetchAll(PDO::FETCH_ASSOC);

 $total = count($moviesList);
echo "<p>Movies needing links: {$total}</p>";

 $stmtLink = $pdo->prepare(
    'INSERT IGNORE INTO movie_genres (movie_id, genre_id) VALUES (:movie_id, :genre_id)'
);

 $done = 0;
 $links = 0;

foreach ($moviesList as $m) {
    $raw = @file_get_contents(
        'https://api.themoviedb.org/3/movie/' . $m['tmdb_id'] . '?api_key=' . TMDB_API_KEY
    );
    if ($raw === false) {
        continue;   // one failed fetch → skip that movie, never kill the run
    }

    $details = json_decode($raw, true);
    foreach ($details['genres'] ?? [] as $g) {
        $stmtLink->bindValue(':movie_id', $m['id'], PDO::PARAM_INT);
        $stmtLink->bindValue(':genre_id', $g['id'], PDO::PARAM_INT);
        $stmtLink->execute();
        $links += $stmtLink->rowCount();   // ← the CORRECT statement this time
    }

    $done++;
    if ($done % 50 === 0) {
        echo "<p>{$done}/{$total} processed — {$links} links so far…</p>";
        flush(); @ob_flush();
    }
}

echo "<h2>Done</h2>";
echo "<p>{$done} movies processed, {$links} genre links created.</p>";
echo "<p><a href='movies.php'>Go to movies →</a></p>";