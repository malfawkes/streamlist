<?php
// actions/admin_add_movie.php — add a product: import a movie from TMDB by ID.
// One field in, real data out: title, poster, backdrop, overview, rating,
// genres, even the trailer — fetched from the detail + videos endpoints.

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

require_once __DIR__ . '/../database/db.php';

if (!isset($_POST['add-movie'])) {
    header('Location: ../admin.php');
    exit;
}

 $tmdbId = filter_var($_POST['tmdb_id'] ?? '', FILTER_VALIDATE_INT);
if ($tmdbId === false || $tmdbId < 1) {
    header('Location: ../admin.php?status=error&message=' . urlencode('Enter a valid TMDB ID.'));
    exit;
}
 $isPremium = isset($_POST['make_premium']) ? 1 : 0;

// ── Fetch the movie's details (the /movie/{id} endpoint) ──────────
 $raw = @file_get_contents('https://api.themoviedb.org/3/movie/'
                        . $tmdbId . '?api_key=' . TMDB_API_KEY);
if ($raw === false) {
    header('Location: ../admin.php?status=error&message='
         . urlencode('Could not reach TMDB — try again.'));
    exit;
}
 $data = json_decode($raw, true);
if (!isset($data['id'])) {
    // unknown ID → TMDB's error body has no 'id' key
    header('Location: ../admin.php?status=error&message='
         . urlencode('TMDB has no movie with that ID.'));
    exit;
}

try {
    // Plain INSERT (no IGNORE) — a duplicate tmdb_id throws 1062,
    // and we WANT the catch to translate it (the duplicate-email pattern)
    $stmt = $pdo->prepare(
        'INSERT INTO movies
            (tmdb_id, title, poster_path, release_date, is_premium, overview, rating, backdrop_path)
         VALUES
            (:tmdb_id, :title, :poster, :date, :premium, :overview, :rating, :backdrop)'
    );
    $stmt->bindValue(':tmdb_id', $data['id'], PDO::PARAM_INT);
    $stmt->bindValue(':title',   $data['title']);
    $stmt->bindValue(':poster',  $data['poster_path'] ?: null);
    $stmt->bindValue(':date',    $data['release_date'] ?: null);
    $stmt->bindValue(':premium', $isPremium, PDO::PARAM_INT);
    $stmt->bindValue(':overview', $data['overview'] ?: null);
    $stmt->bindValue(':rating',   $data['vote_average'] ?: null);   // 0 → null, the lesson
    $stmt->bindValue(':backdrop', $data['backdrop_path'] ?: null);
    $stmt->execute();

    $ourId = (int) $pdo->lastInsertId();   // bridge TMDB id → our internal id

    // ── Genres: the detail endpoint sends full objects [{id, name}] ──
    $stmtG  = $pdo->prepare('INSERT IGNORE INTO genres (id, name) VALUES (:id, :name)');
    $stmtMG = $pdo->prepare('INSERT IGNORE INTO movie_genres (movie_id, genre_id)
                             VALUES (:m, :g)');
    foreach ($data['genres'] ?? [] as $g) {
        $stmtG->bindValue(':id', $g['id'], PDO::PARAM_INT);
        $stmtG->bindValue(':name', $g['name']);
        $stmtG->execute();

        $stmtMG->bindValue(':m', $ourId, PDO::PARAM_INT);
        $stmtMG->bindValue(':g', $g['id'], PDO::PARAM_INT);
        $stmtMG->execute();
    }

    // ── Trailer: one more fetch, the seed's extraction logic, compact ──
    $videos = json_decode(@file_get_contents(
        'https://api.themoviedb.org/3/movie/' . $tmdbId . '/videos?api_key=' . TMDB_API_KEY
    ), true);
    $key = null;
    foreach ($videos['results'] ?? [] as $v) {
        if ($v['site'] === 'YouTube' && $v['type'] === 'Trailer') { $key = $v['key']; break; }
    }
    if ($key !== null) {
        $stmtT = $pdo->prepare('UPDATE movies SET trailer_key = :k WHERE id = :id');
        $stmtT->bindValue(':k', $key);
        $stmtT->bindValue(':id', $ourId, PDO::PARAM_INT);
        $stmtT->execute();
    }

    header('Location: ../admin.php?status=movie-added&title='
         . urlencode($data['title']));
    exit;

} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        $friendly = 'That movie is already in the catalog.';
    } else {
        error_log('Admin add movie error: ' . $e->getMessage());
        $friendly = 'Could not add the movie. Please try again.';
    }
    header('Location: ../admin.php?status=error&message=' . urlencode($friendly));
    exit;
}