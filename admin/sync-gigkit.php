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
    return str_replace(['\\n', '\\,', '\\;', '\\\\'], ["\n", ',', ';', '\\'], $text);
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
    // Filter: CONFIRMED (skip TENTATIVE), PUBLIC, future only
    if (($e['STATUS'] ?? 'CONFIRMED') === 'TENTATIVE') return null;
    if (($e['CLASS'] ?? 'PUBLIC') !== 'PUBLIC') return null;

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
        'title' => $e['SUMMARY'] ?? 'Event',
        'date' => $date,
        'startTime' => isset($m[4]) ? $m[4] . ':' . $m[5] : '',
        'endTime' => '',
        'location' => $e['LOCATION'] ?? '',
        'address' => '',
        'type' => 'openbaar'
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

    $events = [];
    foreach ($ical_events as $e) {
        $event = convert_event($e);
        if ($event) $events[] = $event;
    }

    usort($events, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));

    echo "   Filtered: " . count($events) . " (no TENTATIVE + future only)\n";
    echo "💾 Writing to: $EVENTS_FILE\n";

    $json = json_encode(['events' => $events], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (!file_put_contents($EVENTS_FILE, $json)) {
        throw new Exception("Write failed to $EVENTS_FILE");
    }

    echo "✅ Sync complete! " . count($events) . " events saved\n";
    exit(0);

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
