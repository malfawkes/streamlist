<?php
// seed_from_tmdb.php v5 — adds genre capture. Re-runnable. Movies only.

set_time_limit(0);

require_once 'database/configHidden.php';
require_once 'database/db.php';

function tmdbFetch(string $url): ?array
{
    $json = @file_get_contents($url);
    return $json === false ? null : json_decode($json, true);
}

// 🆕 1. Genre dictionary — once, ~19 rows (Action, Comedy, Horror…)
 $genresData = tmdbFetch('https://api.themoviedb.org/3/genre/movie/list?api_key=' . TMDB_API_KEY);
 $stmtGenre = $pdo->prepare('INSERT IGNORE INTO genres (id, name) VALUES (:id, :name)');
foreach ($genresData['genres'] ?? [] as $g) {
    $stmtGenre->bindValue(':id', $g['id'], PDO::PARAM_INT);
    $stmtGenre->bindValue(':name', $g['name']);
    $stmtGenre->execute();
}

 $pagesPerList = 20;

 $sources = [];
foreach (range(1, $pagesPerList) as $p) {
    $sources[] = 'https://api.themoviedb.org/3/movie/popular?api_key='   . TMDB_API_KEY . '&page=' . $p;
}
foreach (range(1, $pagesPerList) as $p) {
    $sources[] = 'https://api.themoviedb.org/3/movie/top_rated?api_key=' . TMDB_API_KEY . '&page=' . $p;
}
 $sources[] = 'https://api.themoviedb.org/3/trending/movie/week?api_key=' . TMDB_API_KEY;
foreach (range(1, 5) as $p) {
    $sources[] = 'https://api.themoviedb.org/3/movie/upcoming?api_key='   . TMDB_API_KEY . '&page=' . $p;
    $sources[] = 'https://api.themoviedb.org/3/movie/now_playing?api_key='. TMDB_API_KEY . '&page=' . $p;
}
 $genreSlices = [
    ['with_genres=28', 4], ['with_genres=35', 4], ['with_genres=27', 4],
    ['with_genres=10749', 3], ['with_genres=878', 4], ['with_genres=16', 3],
];
foreach ($genreSlices as [$filter, $pages]) {
    foreach (range(1, $pages) as $p) {
        $sources[] = 'https://api.themoviedb.org/3/discover/movie?api_key='
                   . TMDB_API_KEY . '&' . $filter . '&page=' . $p;
    }
}

 $stmt = $pdo->prepare(
    'INSERT IGNORE INTO movies
        (tmdb_id, title, poster_path, release_date, is_premium, overview, rating, backdrop_path)
     VALUES
        (:tmdb_id, :title, :poster_path, :release_date, :is_premium, :overview, :rating, :backdrop_path)'
);
 $stmtTrailer = $pdo->prepare('UPDATE movies SET trailer_key = :key WHERE tmdb_id = :tmdb_id');
// 🆕 2. The genre junction writer
 $stmtMovieGenre = $pdo->prepare(
    'INSERT IGNORE INTO movie_genres (movie_id, genre_id) VALUES (:movie_id, :genre_id)'
);

 $inserted = 0;
 $index    = 0;

foreach ($sources as $url) {
    $data = tmdbFetch($url);
    if (!isset($data['results'])) { continue; }

    foreach ($data['results'] as $movie) {
        $index++;

        $stmt->bindValue(':tmdb_id',       $movie['id'], PDO::PARAM_INT);
        $stmt->bindValue(':title',         $movie['title']);
        $stmt->bindValue(':poster_path',   $movie['poster_path'] ?: null);
        $stmt->bindValue(':release_date',  $movie['release_date'] ?: null);
        $stmt->bindValue(':is_premium',    ($index % 5 === 0) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':overview',      $movie['overview'] ?: null);
        // 🆕 3. 0.0 means "no votes yet" → store null (?: turns 0 into null)
        $stmt->bindValue(':rating',        $movie['vote_average'] ?: null);
        $stmt->bindValue(':backdrop_path', $movie['backdrop_path'] ?: null);
        $stmt->execute();

        if ($stmt->rowCount() === 1) {
            $inserted++;

            // 🆕 4. our internal id — lastInsertId(), the registration trick!
            // (movie_genres references movies.id, NOT tmdb_id)
            $ourId = (int) $pdo->lastInsertId();

            // 🆕 5. link this movie to each of its genres
            foreach ($movie['genre_ids'] ?? [] as $gid) {
                $stmtMovieGenre->bindValue(':movie_id', $ourId, PDO::PARAM_INT);
                $stmtMovieGenre->bindValue(':genre_id', $gid, PDO::PARAM_INT);
                $stmtMovieGenre->execute();
            }

            $videos = tmdbFetch('https://api.themoviedb.org/3/movie/'
                              . $movie['id'] . '/videos?api_key=' . TMDB_API_KEY);
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
    flush(); @ob_flush();
}

echo "<h2>Seeding complete</h2>";
echo "<p>{$inserted} new movies with genres imported.</p>";
echo "<p><a href='movies.php'>Go to movies →</a></p>";