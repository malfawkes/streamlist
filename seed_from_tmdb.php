<?php
// seed_from_tmdb.php v2 — multiple TMDB sources + full details.
// Re-runnable: INSERT IGNORE skips movies you already have.

require_once 'database/configHidden.php';
require_once 'database/db.php';

// Five pages from three lists. Overlapping movies are expected —
// the tmdb_id UNIQUE + INSERT IGNORE dedupe them automatically.
 $sources = [
    'https://api.themoviedb.org/3/trending/movie/week?api_key=' . TMDB_API_KEY,
    'https://api.themoviedb.org/3/movie/popular?api_key='   . TMDB_API_KEY . '&page=1',
    'https://api.themoviedb.org/3/movie/popular?api_key='   . TMDB_API_KEY . '&page=2',
    'https://api.themoviedb.org/3/movie/top_rated?api_key=' . TMDB_API_KEY . '&page=1',
    'https://api.themoviedb.org/3/movie/top_rated?api_key=' . TMDB_API_KEY . '&page=2',
];

 $stmt = $pdo->prepare(
    'INSERT IGNORE INTO movies
        (tmdb_id, title, poster_path, release_date, is_premium, overview, rating, backdrop_path)
     VALUES
        (:tmdb_id, :title, :poster_path, :release_date, :is_premium, :overview, :rating, :backdrop_path)'
);

 $inserted = 0;
 $index    = 0;

foreach ($sources as $url) {
    $json = @file_get_contents($url);

    if ($json === false) {
        // One failed endpoint shouldn't kill the whole run — skip it
        echo "<p style='color:orange'>Warning: could not fetch one source — skipped.</p>";
        continue;
    }

    $data = json_decode($json, true);
    if (!isset($data['results'])) {
        continue;
    }

    foreach ($data['results'] as $movie) {
        $index++;

        $stmt->bindValue(':tmdb_id',       $movie['id'], PDO::PARAM_INT);
        $stmt->bindValue(':title',         $movie['title']);
        $stmt->bindValue(':poster_path',   $movie['poster_path'] ?: null);
        $stmt->bindValue(':release_date',  $movie['release_date'] ?: null);
        $stmt->bindValue(':is_premium',    ($index % 5 === 0) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':overview',      $movie['overview'] ?: null);
        // vote_average arrives as e.g. 7.8 — cast to float, null if absent
        $stmt->bindValue(':rating',   $movie['vote_average'] !== null
                                        ? (float) $movie['vote_average'] : null);
        $stmt->bindValue(':backdrop_path', $movie['backdrop_path'] ?: null);

        $stmt->execute();
        $inserted += $stmt->rowCount();
    }
}

echo "<h2>Seeding complete</h2>";
echo "<p>{$inserted} new movie(s) imported — {$index} total processed (overlaps skipped).</p>";
echo "<p><a href='movies.php'>Go to movies →</a></p>";