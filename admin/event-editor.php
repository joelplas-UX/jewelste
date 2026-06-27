<?php
session_start();

// Check inlog
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: /admin/login.php');
    exit;
}

$events_file = '../data/events.json';
$event = null;
$is_new = true;

// Event laden als edit
if (isset($_GET['id'])) {
    $event_id = $_GET['id'];
    if (file_exists($events_file)) {
        $json = file_get_contents($events_file);
        $data = json_decode($json, true);
        $events = $data['events'] ?? [];
        foreach ($events as $e) {
            if ($e['id'] === $event_id) {
                $event = $e;
                $is_new = false;
                break;
            }
        }
    }
}

// Form opslaan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_event = [
        'id' => $_POST['id'],
        'title' => $_POST['title'],
        'date' => $_POST['date'],
        'startTime' => $_POST['startTime'] ?? '',
        'endTime' => $_POST['endTime'] ?? '',
        'location' => $_POST['location'] ?? '',
        'address' => $_POST['address'] ?? '',
        'type' => $_POST['type'] ?? 'openbaar',
        'published' => isset($_POST['published']) ? (bool)$_POST['published'] : ($event['published'] ?? false)
    ];

    // Behoud UID bij update van Gigkit event
    if (!$is_new && isset($event['uid'])) {
        $new_event['uid'] = $event['uid'];
    }

    // Events laden
    $events = [];
    if (file_exists($events_file)) {
        $json = file_get_contents($events_file);
        $data = json_decode($json, true);
        $events = $data['events'] ?? [];
    }

    // Update of toevoegen
    if ($is_new) {
        $events[] = $new_event;
    } else {
        foreach ($events as &$e) {
            if ($e['id'] === $event['id']) {
                $e = $new_event;
                break;
            }
        }
    }

    // Opslaan
    $data = ['events' => $events];
    file_put_contents($events_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    header('Location: /admin/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>jeWelste Admin — <?php echo $is_new ? 'Nieuw' : 'Bewerk'; ?> Optreden</title>
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
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .header {
            margin-bottom: 32px;
        }
        .header h2 {
            font-size: 28px;
            margin-bottom: 8px;
        }
        .header p {
            color: #666;
            font-size: 14px;
        }
        .form-group {
            background: white;
            padding: 20px;
            margin-bottom: 16px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,.1);
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        input, textarea, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: 'Open Sans', sans-serif;
        }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #FFE800;
            box-shadow: 0 0 0 2px rgba(255,232,0,.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 32px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: opacity .2s;
        }
        .btn-save {
            background: #FFE800;
            color: #161616;
            flex: 1;
        }
        .btn-cancel {
            background: #ddd;
            color: #333;
        }
        .btn:hover {
            opacity: 0.8;
        }
        .small-text {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <nav>
        <h1>jeWelste Admin</h1>
        <a href="/admin/dashboard.php">← Terug</a>
    </nav>

    <div class="container">
        <div class="header">
            <h2><?php echo $is_new ? 'Nieuw Optreden' : 'Bewerk Optreden'; ?></h2>
            <p><?php echo $is_new ? 'Voeg een nieuw optreden toe' : 'Pas het optreden aan'; ?></p>
        </div>

        <form method="POST">
            <div class="form-group">
                <label for="title">Naam Optreden *</label>
                <input type="text" id="title" name="title" placeholder="Bijv. 'Café de Burcht'" value="<?php echo htmlspecialchars($event['title'] ?? ''); ?>" required>
                <div class="small-text">Bijv. naam van de zaal of 'Besloten optreden'</div>
            </div>

            <div class="form-group">
                <label for="date">Datum *</label>
                <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($event['date'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <div class="form-row">
                    <div>
                        <label for="startTime">Begintijd</label>
                        <input type="time" id="startTime" name="startTime" value="<?php echo htmlspecialchars($event['startTime'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="endTime">Eindtijd</label>
                        <input type="time" id="endTime" name="endTime" value="<?php echo htmlspecialchars($event['endTime'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="location">Locatie</label>
                <input type="text" id="location" name="location" placeholder="Naam van de zaal" value="<?php echo htmlspecialchars($event['location'] ?? ''); ?>">
                <div class="small-text">Laat leeg bij besloten optredens</div>
            </div>

            <div class="form-group">
                <label for="address">Adres / Stad</label>
                <input type="text" id="address" name="address" placeholder="Bijv. Leiden of Schenkelweg 6, Zoeterwoude" value="<?php echo htmlspecialchars($event['address'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="type">Type optreden *</label>
                <select id="type" name="type" required>
                    <option value="openbaar" <?php echo ($event['type'] ?? 'openbaar') === 'openbaar' ? 'selected' : ''; ?>>🟢 Openbaar</option>
                    <option value="besloten" <?php echo ($event['type'] ?? '') === 'besloten' ? 'selected' : ''; ?>>🔒 Besloten</option>
                    <option value="geannuleerd" <?php echo ($event['type'] ?? '') === 'geannuleerd' ? 'selected' : ''; ?>>❌ Geannuleerd</option>
                </select>
            </div>

            <div class="form-group">
                <label for="id">Unieke ID *</label>
                <input type="text" id="id" name="id" placeholder="Bijv. 2026-04-15" value="<?php echo htmlspecialchars($event['id'] ?? ''); ?>" required readonly style="background: #f5f5f5;">
                <div class="small-text">Gebruik de datum als ID (wordt automatisch ingevuld)</div>
            </div>

            <div class="form-group" style="display: flex; align-items: center; gap: 12px;">
                <input type="checkbox" id="published" name="published" value="1" <?php echo ($event['published'] ?? false) ? 'checked' : ''; ?> style="width: 18px; height: 18px; cursor: pointer;">
                <label for="published" style="margin: 0; cursor: pointer; font-weight: 600;">
                    <?php if ($event['published'] ?? false): ?>
                        ✅ Live op website
                    <?php else: ?>
                        ⏸ Concept (nog niet zichtbaar)
                    <?php endif; ?>
                </label>
            </div>

            <script>
                // Auto-fill ID op basis van datum
                document.getElementById('date').addEventListener('change', function() {
                    document.getElementById('id').value = this.value;
                });
                // Bij laden: zet de ID gelijk aan de datum (voor nieuwe events)
                if (!document.getElementById('id').value && document.getElementById('date').value) {
                    document.getElementById('id').value = document.getElementById('date').value;
                }

                // Update label als published checkbox verandert
                const publishedCheckbox = document.getElementById('published');
                const publishedLabel = publishedCheckbox.nextElementSibling;
                publishedCheckbox.addEventListener('change', function() {
                    publishedLabel.innerHTML = this.checked
                        ? '✅ Live op website'
                        : '⏸ Concept (nog niet zichtbaar)';
                });
            </script>

            <div class="form-actions">
                <button type="submit" class="btn btn-save">💾 Opslaan</button>
                <a href="/admin/dashboard.php" class="btn btn-cancel">Annuleren</a>
            </div>
        </form>
    </div>
</body>
</html>
