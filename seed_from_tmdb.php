<?php
// seed_from_tmdb.php v3 — big catalog + trailer keys. ONE-TIME, re-runnable.

set_time_limit(0);   // 🆕 removes PHP's 30-second execution cap — this takes MINUTES now

require_once 'database/configHidden.php';
require_once 'database/db.php';

// 🆕 one helper instead of copy-pasted fetch code (DRY — functions exist for this)
function tmdbFetch(string $url): ?array
{
    $json = @file_get_contents($url);
    return $json === false ? null : json_decode($json, true);
}

// Dial: pages per list. 20 pages × 20 movies ≈ 400/list. Lower to 10 for a faster run.
 $pagesPerList = 20;

// 🆕 sources generated with loops (same concat skill as your form URLs)
 $sources = [];
foreach (range(1, $pagesPerList) as $p) {
    $sources[] = 'https://api.themoviedb.org/3/movie/popular?api_key='   . TMDB_API_KEY . '&page=' . $p;
}
foreach (range(1, $pagesPerList) as $p) {
    $sources[] = 'https://api.themoviedb.org/3/movie/top_rated?api_key=' . TMDB_API_KEY . '&page=' . $p;
}
 $sources[] = 'https://api.themoviedb.org/3/trending/movie/week?api_key=' . TMDB_API_KEY;

// 🆕 /discover = TMDB's filter endpoint — genre variety (28=action, 27=horror)
foreach (range(1, 5) as $p) {
    $sources[] = 'https://api.themoviedb.org/3/discover/movie?api_key=' . TMDB_API_KEY . '&with_genres=28&page=' . $p;
    $sources[] = 'https://api.themoviedb.org/3/discover/movie?api_key=' . TMDB_API_KEY . '&with_genres=27&page=' . $p;
}

 $stmt = $pdo->prepare(
    'INSERT IGNORE INTO movies
        (tmdb_id, title, poster_path, release_date, is_premium, overview, rating, backdrop_path)
     VALUES
        (:tmdb_id, :title, :poster_path, :release_date, :is_premium, :overview, :rating, :backdrop_path)'
);

// 🆕 your FIRST real UPDATE statement — writes trailer_key into a row that already exists
 $stmtTrailer = $pdo->prepare('UPDATE movies SET trailer_key = :key WHERE tmdb_id = :tmdb_id');

 $inserted = 0;
 $index    = 0;

foreach ($sources as $url) {
    $data = tmdbFetch($url);
    if (!isset($data['results'])) { continue; }   // one bad page never kills the run

    foreach ($data['results'] as $movie) {
        $index++;

        $stmt->bindValue(':tmdb_id',       $movie['id'], PDO::PARAM_INT);
        $stmt->bindValue(':title',         $movie['title']);
        $stmt->bindValue(':poster_path',   $movie['poster_path'] ?: null);
        $stmt->bindValue(':release_date',  $movie['release_date'] ?: null);
        $stmt->bindValue(':is_premium',    ($index % 5 === 0) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':overview',      $movie['overview'] ?: null);
        $stmt->bindValue(':rating',        $movie['vote_average'] !== null
                                            ? (float) $movie['vote_average'] : null);
        $stmt->bindValue(':backdrop_path', $movie['backdrop_path'] ?: null);
        $stmt->execute();

        // 🆕 rowCount()===1 → this row is NEW (0 = duplicate skipped by IGNORE).
        // Fetch trailer ONLY for new movies → re-runs never re-fetch ~1000 videos.
        if ($stmt->rowCount() === 1) {
            $inserted++;

            $videos = tmdbFetch('https://api.themoviedb.org/3/movie/'
                              . $movie['id'] . '/videos?api_key=' . TMDB_API_KEY);

            // Pick: first YouTube Trailer; fall back to a Teaser; else none
            $key = null;
            foreach ($videos['results'] ?? [] as $v) {
                if ($v['site'] === 'YouTube' && $v['type'] === 'Trailer') { $key = $v['key']; break; }
            }
            if ($key === null) {
                foreach ($videos['results'] ?? [] as $v) {
                    if ($v['site'] === 'YouTube' && $v['type'] === 'Teaser') { $key = $v['key']; break; }
                }
            }
            if ($key !== null) {
                $stmtTrailer->bindValue(':key', $key);
                $stmtTrailer->bindValue(':tmdb_id', $movie['id'], PDO::PARAM_INT);
                $stmtTrailer->execute();
            }
        }
    }
    echo "<p>List done — {$inserted} imported so far…</p>";
    flush(); @ob_flush();   // 🆕 try to push progress to the browser live
}

echo "<h2>Seeding complete</h2>";
echo "<p>{$inserted} new movies imported ({$index} processed, overlaps skipped).</p>";
echo "<p><a href='movies.php'>Go to movies →</a></p>";