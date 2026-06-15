<?php
session_start();

// Check of gebruiker ingelogd is
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: /admin/login.php');
    exit;
}

$user_email = $_SESSION['user_email'];
$events_file = '../data/events.json';

// Events laden
$events = [];
if (file_exists($events_file)) {
    $json = file_get_contents($events_file);
    $data = json_decode($json, true);
    $events = $data['events'] ?? [];
}

// Sorteren op datum (oplopend)
usort($events, function($a, $b) {
    return strtotime($a['date']) - strtotime($b['date']);
});

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $event_id = $_POST['event_id'] ?? null;

    if ($action === 'delete') {
        $events = array_filter($events, function($e) use ($event_id) {
            return $e['id'] !== $event_id;
        });
        $data = ['events' => array_values($events)];
        file_put_contents($events_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        header('Location: /admin/dashboard.php');
        exit;
    }

    if ($action === 'toggle-publish') {
        foreach ($events as &$e) {
            if ($e['id'] === $event_id) {
                $e['published'] = !($e['published'] ?? false);
                break;
            }
        }
        $data = ['events' => $events];
        file_put_contents($events_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        header('Location: /admin/dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>jeWelste Admin — Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Open Sans', sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        nav {
            background: #161616;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        nav h1 {
            font-size: 18px;
            font-weight: 700;
        }
        nav a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            padding: 8px 16px;
            background: rgba(255,255,255,.1);
            border-radius: 4px;
            transition: background .2s;
        }
        nav a:hover {
            background: rgba(255,255,255,.2);
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }
        .header h2 {
            font-size: 28px;
        }
        .btn-new {
            background: #FFE800;
            color: #161616;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background .2s;
        }
        .btn-new:hover {
            background: #FFD700;
        }
        .events-list {
            display: grid;
            gap: 16px;
        }
        .event-card {
            background: white;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,.1);
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 24px;
            align-items: center;
        }
        .event-info h3 {
            font-size: 18px;
            margin-bottom: 8px;
        }
        .event-date {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }
        .event-meta {
            font-size: 13px;
            color: #999;
        }
        .event-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 8px;
            margin-top: 8px;
        }
        .badge-openbaar {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .badge-besloten {
            background: #f3e5f5;
            color: #6a1b9a;
        }
        .badge-published {
            background: #c8e6c9;
            color: #1b5e20;
        }
        .badge-concept {
            background: #fff3e0;
            color: #e65100;
        }
        .event-card.unpublished {
            opacity: 0.7;
            border-left: 4px solid #ff9800;
        }
        .event-actions {
            display: flex;
            gap: 8px;
        }
        .btn-edit, .btn-delete, .btn-publish, .btn-unpublish {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: opacity .2s;
        }
        .btn-edit {
            background: #2196F3;
            color: white;
        }
        .btn-publish {
            background: #4CAF50;
            color: white;
        }
        .btn-unpublish {
            background: #FF9800;
            color: white;
        }
        .btn-delete {
            background: #f44336;
            color: white;
        }
        .btn-edit:hover, .btn-delete:hover, .btn-publish:hover, .btn-unpublish:hover {
            opacity: 0.8;
        }
        .empty {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty p {
            margin-bottom: 20px;
        }
        @media (max-width: 600px) {
            .event-card {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav>
        <h1>jeWelste Admin</h1>
        <div>
            <span style="margin-right: 16px; font-size: 13px;">Ingelogd als: <?php echo htmlspecialchars($user_email); ?></span>
            <a href="?logout">Uitloggen</a>
        </div>
    </nav>

    <div class="container">
        <div class="header">
            <h2>Optredens</h2>
            <a href="/admin/event-editor.php" class="btn-new">+ Nieuw optreden</a>
        </div>

        <?php if (isset($_GET['logout'])): ?>
            <?php session_destroy(); header('Location: /admin/login.php'); exit; ?>
        <?php endif; ?>

        <?php if (empty($events)): ?>
            <div class="empty">
                <p>Geen optredens gepland.</p>
                <a href="/admin/event-editor.php" class="btn-new">Voeg een optreden toe</a>
            </div>
        <?php else: ?>
            <div class="events-list">
                <?php foreach ($events as $event): ?>
                    <div class="event-card <?php echo !($event['published'] ?? false) ? 'unpublished' : ''; ?>">
                        <div class="event-info">
                            <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                            <div class="event-date">
                                📅 <?php
                                    $date = DateTime::createFromFormat('Y-m-d', $event['date']);
                                    echo $date ? $date->format('d M Y') : $event['date'];
                                ?>
                            </div>
                            <?php if (!empty($event['startTime'])): ?>
                                <div class="event-meta">
                                    🕐 <?php echo htmlspecialchars($event['startTime']); ?>
                                    <?php if (!empty($event['endTime'])): ?>
                                        – <?php echo htmlspecialchars($event['endTime']); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($event['location'])): ?>
                                <div class="event-meta">
                                    📍 <?php echo htmlspecialchars($event['location']); ?><?php echo !empty($event['address']) ? ', ' . htmlspecialchars($event['address']) : ''; ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <span class="event-badge badge-<?php echo $event['type'] === 'openbaar' ? 'openbaar' : 'besloten'; ?>">
                                    <?php echo $event['type'] === 'openbaar' ? 'Openbaar' : 'Besloten'; ?>
                                </span>
                                <span class="event-badge badge-<?php echo ($event['published'] ?? false) ? 'published' : 'concept'; ?>">
                                    <?php echo ($event['published'] ?? false) ? '✅ Live' : '⏸ Concept'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="event-actions">
                            <a href="/admin/event-editor.php?id=<?php echo urlencode($event['id']); ?>" class="btn-edit">Bewerk</a>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle-publish">
                                <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($event['id']); ?>">
                                <button type="submit" class="btn-<?php echo ($event['published'] ?? false) ? 'unpublish' : 'publish'; ?>">
                                    <?php echo ($event['published'] ?? false) ? '🔓 Verbergen' : '✅ Publiceren'; ?>
                                </button>
                            </form>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Verwijderen?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($event['id']); ?>">
                                <button type="submit" class="btn-delete">Verwijder</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
