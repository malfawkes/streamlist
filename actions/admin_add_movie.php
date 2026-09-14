<?php
// actions/admin_add_movie.php — add a product: import a movie from TMDB by ID.
// Populates ALL related tables: movies, movie_genres, trailer, movie_credits.

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

// Fetch the movie's details
 $raw = @file_get_contents('https://api.themoviedb.org/3/movie/'
                        . $tmdbId . '?api_key=' . TMDB_API_KEY);
if ($raw === false) {
    header('Location: ../admin.php?status=error&message='
         . urlencode('Could not reach TMDB — try again.'));
    exit;
}
 $data = json_decode($raw, true);
if (!isset($data['id'])) {
    header('Location: ../admin.php?status=error&message='
         . urlencode('TMDB has no movie with that ID.'));
    exit;
}

try {
    // Plain INSERT — duplicate tmdb_id throws 1062 → friendly catch
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
    $stmt->bindValue(':rating',   $data['vote_average'] ?: null);
    $stmt->bindValue(':backdrop', $data['backdrop_path'] ?: null);
    $stmt->execute();

    $ourId = (int) $pdo->lastInsertId();   // bridge: TMDB id → our internal id

    // Genres (detail endpoint sends full objects)
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

    // Trailer
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

    // Credits: cast + director + screenplay
    // Same extraction as fill_credits, pointed at OUR new row.
    $credits = json_decode(@file_get_contents(
        'https://api.themoviedb.org/3/movie/' . $tmdbId . '/credits?api_key=' . TMDB_API_KEY
    ), true);

    $stmtC = $pdo->prepare(
        'INSERT IGNORE INTO movie_credits
            (movie_id, person_id, name, job, character_name, profile_path, credit_order)
         VALUES
            (:movie_id, :person_id, :name, :job, :character, :profile, :ord)'
    );

    $creditsAdded = 0;

    // Top 8 billed cast
    foreach (array_slice($credits['cast'] ?? [], 0, 8) as $i => $c) {
        $stmtC->bindValue(':movie_id', $ourId, PDO::PARAM_INT);
        $stmtC->bindValue(':person_id', $c['id'], PDO::PARAM_INT);
        $stmtC->bindValue(':name', $c['name']);
        $stmtC->bindValue(':job', 'Cast');
        $stmtC->bindValue(':character', $c['character'] ?: null);
        $stmtC->bindValue(':profile', $c['profile_path'] ?: null);
        $stmtC->bindValue(':ord', $i, PDO::PARAM_INT);
        $stmtC->execute();
        $creditsAdded += $stmtC->rowCount();
    }

    // Director + Screenplay from crew
    foreach ($credits['crew'] ?? [] as $c) {
        if ($c['job'] === 'Director' || $c['job'] === 'Screenplay') {
            $stmtC->bindValue(':movie_id', $ourId, PDO::PARAM_INT);
            $stmtC->bindValue(':person_id', $c['id'], PDO::PARAM_INT);
            $stmtC->bindValue(':name', $c['name']);
            $stmtC->bindValue(':job', $c['job']);
            $stmtC->bindValue(':character', null);
            $stmtC->bindValue(':profile', $c['profile_path'] ?: null);
            $stmtC->bindValue(':ord', 0, PDO::PARAM_INT);
            $stmtC->execute();
            $creditsAdded += $stmtC->rowCount();
        }
    }

    header('Location: ../admin.php?status=movie-added&title='
         . urlencode($data['title']) . '&credits=' . $creditsAdded);
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