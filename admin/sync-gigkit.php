<?php
/**
 * Gigkit → jeWelste Event Sync
 * Laadt events van Gigkit iCal feed en synchroniseert naar events.json
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Gigkit iCal URL
$GIGKIT_FEED = 'https://gigkit.nl/api/cal?token=6074e66c-f73b-41d2-ad5f-220732bc3c77';

// Bepaal pad - probeer beide mogelijkheden
$possible_paths = [
    __DIR__ . '/../data/events.json',           // Local: /jewelste/admin/../data/events.json
    getcwd() . '/data/events.json',             // GitHub Actions working dir
];

$EVENTS_FILE = null;
foreach ($possible_paths as $path) {
    $dir = dirname($path);
    if (@mkdir($dir, 0755, true) || is_dir($dir)) {
        $EVENTS_FILE = $path;
        echo "✅ Using events file: $EVENTS_FILE\n";
        break;
    }
}

if (!$EVENTS_FILE) {
    echo "❌ Could not determine events file path\n";
    exit(1);
}

/**
 * Unescape iCal text (handle \n, \,, etc)
 */
function unescape_ical($text) {
    // In iCal format: \\ → backslash, \n → newline, \, → comma, \; → semicolon
    // Process in order to avoid double-unescaping
    $text = str_replace('\\\\', "\x00", $text);      // Temp placeholder for \\
    $text = str_replace('\n', "\n", $text);          // \n → newline
    $text = str_replace('\,', ',', $text);           // \, → comma
    $text = str_replace('\;', ';', $text);           // \; → semicolon
    $text = str_replace("\x00", '\\', $text);        // Restore backslash
    return $text;
}

/**
 * Parse iCal
 */
function parse_ical($content) {
    $events = [];
    $lines = explode("\n", $content);
    $in_event = false;
    $event = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === 'BEGIN:VEVENT') {
            $in_event = true;
            $event = [];
        } elseif ($line === 'END:VEVENT') {
            if (!empty($event)) $events[] = $event;
            $in_event = false;
        } elseif ($in_event && strpos($line, ':') !== false) {
            [$key, $value] = explode(':', $line, 2);
            $key = explode(';', $key)[0];
            $event[$key] = unescape_ical($value);
        }
    }
    return $events;
}

/**
 * Convert to jeWelste format
 */
function convert_event($e) {
    // Filter: CONFIRMED only (skip TENTATIVE), future dates only
    if (($e['STATUS'] ?? 'CONFIRMED') === 'TENTATIVE') return null;

    $start = $e['DTSTART'] ?? '';
    if (strpos($start, ':') !== false) {
        $start = substr($start, strpos($start, ':') + 1);
    }

    if (!preg_match('/^(\d{4})(\d{2})(\d{2})(?:T(\d{2})(\d{2}))?/', $start, $m)) {
        return null;
    }

    $date = $m[1] . '-' . $m[2] . '-' . $m[3];

    // Filter: future dates only
    if (strtotime($date) < strtotime(date('Y-m-d'))) {
        return null;
    }

    return [
        'id' => $date,
        'uid' => $e['UID'] ?? null,
        'title' => $e['SUMMARY'] ?? 'Event',
        'date' => $date,
        'startTime' => isset($m[4]) ? $m[4] . ':' . $m[5] : '',
        'endTime' => '',
        'location' => $e['LOCATION'] ?? '',
        'address' => '',
        'type' => 'openbaar',
        'class' => $e['CLASS'] ?? 'PUBLIC',
        'published' => false
    ];
}

try {
    echo "📡 Fetching Gigkit feed...\n";
    $ical = @file_get_contents($GIGKIT_FEED, false, stream_context_create([
        'http' => ['timeout' => 10, 'user_agent' => 'jeWelste/1.0'],
        'ssl' => ['verify_peer' => false]
    ]));

    if (!$ical || strpos($ical, 'BEGIN:VCALENDAR') === false) {
        throw new Exception('Invalid iCal feed');
    }

    echo "📝 Parsing events...\n";
    $ical_events = parse_ical($ical);
    echo "   Found: " . count($ical_events) . " total\n";

    $gigkit_events = [];
    foreach ($ical_events as $e) {
        $event = convert_event($e);
        if ($event) $gigkit_events[] = $event;
    }

    echo "   Filtered: " . count($gigkit_events) . " (no TENTATIVE + future only, incl. PRIVATE)\n";

    // Load existing events to preserve publishing status
    $existing_events = [];
    if (file_exists($EVENTS_FILE)) {
        $existing_data = json_decode(file_get_contents($EVENTS_FILE), true);
        if ($existing_data && isset($existing_data['events'])) {
            foreach ($existing_data['events'] as $e) {
                $existing_events[$e['uid'] ?? $e['id']] = $e;
            }
        }
    }

    // Merge: match by UID first, then by date+title, respect published status
    $merged_events = [];
    $updated_count = 0;
    $published_count = 0;
    $merged_keys = [];

    foreach ($gigkit_events as $event) {
        $existing = null;
        $existing_key = null;

        // Try to match by UID first
        if ($event['uid']) {
            foreach ($existing_events as $key => $e) {
                if (($e['uid'] ?? null) === $event['uid']) {
                    $existing = $e;
                    $existing_key = $key;
                    break;
                }
            }
        }

        // If no UID match, try to match by date + title
        if (!$existing) {
            foreach ($existing_events as $key => $e) {
                if ($e['date'] === $event['date'] && $e['title'] === $event['title']) {
                    $existing = $e;
                    $existing_key = $key;
                    break;
                }
            }
        }

        // Update or add event
        if ($existing) {
            // Protect published events OR manually created events (no UID)
            if (($existing['published'] ?? false) || !($existing['uid'] ?? null)) {
                echo "   📌 Keeping: " . $event['title'] . " (published or manual)\n";
                $merged_events[] = $existing;
                if ($existing['published'] ?? false) $published_count++;
            } else {
                // Update unpublished Gigkit events
                $updated_count++;
                echo "   🔄 Updating: " . $event['title'] . "\n";
                $merged_events[] = $event;
            }
        } else {
            // New event from Gigkit
            $merged_events[] = $event;
        }

        if ($existing_key) {
            $merged_keys[] = $existing_key;
        }
    }

    // Add manually created events that weren't matched with Gigkit
    foreach ($existing_events as $key => $event) {
        if (!in_array($key, $merged_keys, true)) {
            $merged_events[] = $event;
        }
    }

    usort($merged_events, fn($a, $b) => strtotime($a['date']) - strtotime($b['date']));

    echo "💾 Writing to: $EVENTS_FILE\n";

    $json = json_encode(['events' => $merged_events], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (!file_put_contents($EVENTS_FILE, $json)) {
        throw new Exception("Write failed to $EVENTS_FILE");
    }

    echo "✅ Sync complete!\n";
    echo "   Total: " . count($merged_events) . " events\n";
    echo "   From Gigkit: " . count($gigkit_events) . "\n";
    echo "   Updated: $updated_count\n";
    echo "   Published (protected): $published_count\n";
    exit(0);

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
