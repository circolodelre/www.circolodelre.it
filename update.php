<?php
/**
 *
 */

require_once 'vendor/autoload.php';

use Webmozart\Glob\Glob;

$settings = circolodelre_load_settings();

$csvPath = 'storage/csv/'.$settings['year'];
$jsonPath = 'storage/json/'.$settings['year'];

is_dir($csvPath) or mkdir($csvPath, 0777, true);
is_dir($jsonPath) or mkdir($jsonPath, 0777, true);

$globCsv = str_replace(
    ['${YEAR}', '~'],
    [$settings['year'], $_SERVER['HOME']],
    $settings['tournaments-path'].'/**/*-Standing.csv'
);

foreach (Glob::glob($globCsv) as $file) {
    echo " - $file\n";
    copy($file, $csvPath.'/'.basename($file));
}

$trends = [];
$championship = [];
$standings = circolodelre_load_standings_csv($csvPath, $settings['date-format']);

foreach ($standings as $time => $standing) {
    // general stages championship info
    $championship['stages'][$time] = [
        'time'   => $time,
        'rows'   => [],
    ];

    // loop through standing rows
    foreach ($standing['rows'] as $row0) {
        $row = &$championship['general']['rows'][$row0['key']];
        $row['count']  = isset($row['count']) ? $row['count'] + 1 : 1;
        $row['player'] = $row0['name'];
        $row['title']  = $row0['title'];
        $row[$time]    = $row0['score'];
        $row['score']  = number_format(isset($row['score']) ? $row['score'] + $row0['score'] : $row0['score'], 1);
        $row['bonus']  = number_format($row['count'] > 3 ? $row['count'] + 3 : $row['count'], 1);
        $row['total']  = number_format($row['score'] + $row['bonus'], 1);
        $row['rating'] = 1440 + 40 * $row['score'] - 100 * $row['count'];

        $row = &$championship['stages'][$time]['rows'][$row0['key']];
        $row['player'] = $row0['name'];
        $row['title']  = $row0['title'];
        $row['score']  = $row0['score'];
        $row['buc1']   = $row0['buc1'];
        $row['buct']   = $row0['buct'];

        if (!$standing['last']) {
            $row = &$trends[$row0['key']];
            $row['count']  = isset($row['count']) ? $row['count'] + 1 : 1;
            $row['score']  = number_format(isset($row['score']) ? $row['score'] + $row0['score'] : $row0['score'], 1);
            $row['bonus']  = number_format($row['count'] > 3 ? $row['count'] + 3 : $row['count'], 1);
            $row['total']  = number_format($row['score'] + $row['bonus'], 1);
            $row['rating'] = 1440 + 40 * $row['score'] - 100 * $row['count'];
        }
    }
}

//
function circolodelre_standing_sort($row0, $row1)
{
    return $row0['total'] > $row1['total'] ? -1 : 1;
}

//
function circolodelre_apply_rank(&$standing)
{
    usort($standing, 'circolodelre_standing_sort');

    $rank = 1;
    foreach ($standing as &$row) {
        $row['rank'] = $rank;
        $rank++;
    }
}

// Update ranks
circolodelre_apply_rank($trends);

// Update ranks
circolodelre_apply_rank($championship['general']['rows']);

// Apply trends
foreach ($championship['general']['rows'] as $key => &$row) {
    $row['trend'] = [];

    if (empty($trends[$key])) {
        $row['trend']['rank']   = '=';
        $row['trend']['rating'] = '=';
        continue;
    }

    $row['trend']['rank']   = circolodelre_trend_sign($trends[$key]['rank'], $row['rank']);
    $row['trend']['rating'] = circolodelre_trend_sign($trends[$key]['rating'], $row['rating']);
}

//
foreach ($championship['stages'] as &$stage) {
    circolodelre_apply_rank($stage['rows']);
}

ksort($championship['stages']);

// Save file
$championship['general']['rows'] = array_values($championship['general']['rows']);
file_put_contents($jsonPath.'/Championship.json', json_encode($championship, JSON_PRETTY_PRINT));
