<?php
/**
 * Gigkit → jeWelste Event Sync
 * Laadt events van Gigkit iCal feed en synchroniseert naar events.json
 */

// Gigkit iCal URL
$GIGKIT_FEED = 'webcal://gigkit.nl/api/cal?token=6074e66c-f73b-41d2-ad5f-220732bc3c77';
$EVENTS_FILE = '../data/events.json';

/**
 * Parse iCal format
 */
function parse_ical($ical_content) {
    $events = [];
    $lines = explode("\n", $ical_content);
    $in_event = false;
    $event = [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === 'BEGIN:VEVENT') {
            $in_event = true;
            $event = [];
        } elseif ($line === 'END:VEVENT') {
            if (!empty($event)) {
                $events[] = $event;
            }
            $in_event = false;
        } elseif ($in_event) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Parse parameters (bijv. DTSTART;TZID=...)
                if (strpos($key, ';') !== false) {
                    [$key] = explode(';', $key);
                }

                $event[$key] = $value;
            }
        }
    }

    return $events;
}

/**
 * Converteer iCal naar jeWelste format
 */
function convert_event($ical_event) {
    // Zet webcal:// naar https:// voor fetch
    $start = $ical_event['DTSTART'] ?? '';
    $summary = $ical_event['SUMMARY'] ?? 'Event';
    $location = $ical_event['LOCATION'] ?? '';
    $description = $ical_event['DESCRIPTION'] ?? '';

    // Parse datum/tijd
    // iCal format: 20260415T200000 of 20260415
    if (preg_match('/^(\d{4})(\d{2})(\d{2})(?:T(\d{2})(\d{2}))?/', $start, $m)) {
        $date = $m[1] . '-' . $m[2] . '-' . $m[3];
        $time = isset($m[4]) ? $m[4] . ':' . $m[5] : '';
    } else {
        return null; // Kan datum niet parsen
    }

    return [
        'id' => $date,
        'title' => $summary,
        'date' => $date,
        'startTime' => $time,
        'endTime' => '', // iCal DTEND zou hier kunnen, maar vereenvoudigd voor nu
        'location' => $location,
        'address' => '',
        'type' => 'openbaar' // Gigkit biedt dit niet, dus alles is openbaar
    ];
}

/**
 * Fetch iCal feed
 */
function fetch_ical($url) {
    // Zet webcal:// om naar https://
    $url = str_replace('webcal://', 'https://', $url);

    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'jeWelste-Sync/1.0'
        ]
    ]);

    $content = @file_get_contents($url, false, $context);
    if ($content === false) {
        throw new Exception("Kon Gigkit feed niet laden: $url");
    }

    return $content;
}

/**
 * Main sync
 */
try {
    // Fetch iCal
    echo "📡 Gigkit feed laden...\n";
    $ical = fetch_ical($GIGKIT_FEED);

    // Parse
    echo "📝 Events parsen...\n";
    $ical_events = parse_ical($ical);
    echo "   Gevonden: " . count($ical_events) . " events\n";

    // Convert
    $events = [];
    foreach ($ical_events as $ical_event) {
        $event = convert_event($ical_event);
        if ($event) {
            $events[] = $event;
        }
    }
    echo "   Converted: " . count($events) . " events\n";

    // Sort op datum (nieuwste eerst)
    usort($events, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    // Save
    echo "💾 Events opslaan naar " . $EVENTS_FILE . "...\n";
    $data = ['events' => $events];
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if (!file_put_contents($EVENTS_FILE, $json)) {
        throw new Exception("Kon " . $EVENTS_FILE . " niet schrijven");
    }

    echo "✅ Sync compleet! " . count($events) . " events opgeslagen.\n";
    exit(0);

} catch (Exception $e) {
    echo "❌ Fout: " . $e->getMessage() . "\n";
    exit(1);
}
?>
