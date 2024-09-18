<?php

header('Content-Type: application/json');

/**
 * Fetch data from a given URL using cURL.
 *
 * @param string $url
 * @return string
 */
function fetchData(string $url): string {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $data = curl_exec($ch);
    curl_close($ch);
    
    if ($data === false) {
        throw new Exception('Failed to fetch data from URL: ' . $url);
    }
    
    return $data;
}

/**
 * Extract video details and captions from YouTube.
 *
 * @param string $videoID
 * @param string $lang
 * @return array
 */
function getVideoDetails(string $videoID, string $lang = 'en'): array {
    $url = "https://www.youtube.com/watch?v=$videoID";
    
    try {
        $data = fetchData($url);
    } catch (Exception $e) {
        return [
            'error' => 'Unable to fetch video details.',
            'message' => $e->getMessage(),
        ];
    }

    // Handle video not found case
    if (strpos($data, '404 Not Found') !== false) {
        return [
            'videoTitle' => 'Video not found',
            'transcript' => 'Video not found',
        ];
    }

    // Extract video title
    $videoTitle = extractVideoTitle($data);

    // Extract caption tracks and fetch transcript if available
    $transcript = extractTranscript($data, $lang);

    return [
        'videoTitle' => $videoTitle,
        'transcript' => $transcript,
    ];
}

/**
 * Extract the video title from the YouTube HTML page.
 *
 * @param string $data
 * @return string
 */
function extractVideoTitle(string $data): string {
    if (preg_match('/<title>(.*?)<\/title>/', $data, $titleMatches)) {
        $title = html_entity_decode($titleMatches[1], ENT_QUOTES);
        return str_replace(' - YouTube', '', $title);
    }

    return 'Unknown Title';
}

/**
 * Extract and return the transcript in the specified language, if available.
 *
 * @param string $data
 * @param string $lang
 * @return string
 */
function extractTranscript(string $data, string $lang): string {
    if (!preg_match('/"captionTracks":(\[.*?\])/', $data, $captionMatches)) {
        return 'No captions available.';
    }

    $captionTracks = json_decode($captionMatches[1], true);
    $subtitleTrack = findSubtitleTrack($captionTracks, $lang);

    if (!$subtitleTrack || !isset($subtitleTrack['baseUrl'])) {
        return 'Captions not available for the selected language.';
    }

    try {
        $transcriptData = fetchData($subtitleTrack['baseUrl']);
        return formatTranscript($transcriptData);
    } catch (Exception $e) {
        return 'Error fetching transcript.';
    }
}

/**
 * Find the appropriate subtitle track based on language.
 *
 * @param array $captionTracks
 * @param string $lang
 * @return array|null
 */
function findSubtitleTrack(array $captionTracks, string $lang): ?array {
    foreach ($captionTracks as $track) {
        if (strpos($track['vssId'], ".$lang") !== false || strpos($track['vssId'], "a.$lang") !== false) {
            return $track;
        }
    }

    return null;
}

/**
 * Format raw transcript XML data into readable text.
 *
 * @param string $transcriptData
 * @return string
 */
function formatTranscript(string $transcriptData): string {
    $transcriptData = str_replace(['<?xml version="1.0" encoding="utf-8" ?><transcript>', '</transcript>'], '', $transcriptData);
    $lines = explode('</text>', $transcriptData);
    $transcript = '';

    foreach ($lines as $line) {
        $text = preg_replace('/<[^>]+>/', '', $line);
        if (trim($text)) {
            $transcript .= htmlspecialchars_decode($text) . "\n";
        }
    }
    
    return trim($transcript);
}

// Main process
$videoID = $_REQUEST['video_id'] ?? null;
if (!$videoID) {
    echo json_encode(['error' => 'Missing video_id parameter'], JSON_PRETTY_PRINT);
    exit;
}

$videoDetails = getVideoDetails($videoID);
echo json_encode($videoDetails, JSON_PRETTY_PRINT);