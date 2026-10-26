<?php
/**
 * Bantay Aso — Native PHP Diff Engine
 * Implementation of Longest Common Subsequence (LCS) for line-by-line diffing.
 * Generates unified diffs and side-by-side comparison structures with zero external CLI dependencies.
 */

declare(strict_types=1);

class Differ
{
    /**
     * Compute a structural line diff between baseline and comparison content.
     *
     * @param string $baselineText Original baseline text or cleaned HTML
     * @param string $comparisonText New comparison text or cleaned HTML
     * @return array [
     *   'lines' => array,
     *   'side_by_side' => array,
     *   'unified' => string,
     *   'stats' => array
     * ]
     */
    public static function diff(string $baselineText, string $comparisonText): array
    {
        $oldLines = self::splitLines($baselineText);
        $newLines = self::splitLines($comparisonText);

        $matrix = self::computeLcsMatrix($oldLines, $newLines);
        $diffItems = self::backtrack($matrix, $oldLines, $newLines, count($oldLines), count($newLines));

        $stats = [
            'added' => 0,
            'removed' => 0,
            'unchanged' => 0,
            'total' => count($diffItems),
            'similarity_pct' => 100.0,
        ];

        $unified = [];
        $sideBySide = [];
        $oldLineNum = 1;
        $newLineNum = 1;

        foreach ($diffItems as $item) {
            $type = $item['type'];
            $text = $item['text'];

            if ($type === 'unchanged') {
                $stats['unchanged']++;
                $unified[] = ' ' . $text;
                $sideBySide[] = [
                    'left' => ['num' => $oldLineNum++, 'type' => 'unchanged', 'text' => $text],
                    'right' => ['num' => $newLineNum++, 'type' => 'unchanged', 'text' => $text],
                ];
            } elseif ($type === 'removed') {
                $stats['removed']++;
                $unified[] = '-' . $text;
                $sideBySide[] = [
                    'left' => ['num' => $oldLineNum++, 'type' => 'removed', 'text' => $text],
                    'right' => ['num' => null, 'type' => 'empty', 'text' => ''],
                ];
            } elseif ($type === 'added') {
                $stats['added']++;
                $unified[] = '+' . $text;
                $sideBySide[] = [
                    'left' => ['num' => null, 'type' => 'empty', 'text' => ''],
                    'right' => ['num' => $newLineNum++, 'type' => 'added', 'text' => $text],
                ];
            }
        }

        $totalDiffs = $stats['added'] + $stats['removed'];
        $baseTotal = count($oldLines) + count($newLines);
        if ($baseTotal > 0) {
            $stats['similarity_pct'] = round((($stats['unchanged'] * 2) / $baseTotal) * 100, 2);
        } else {
            $stats['similarity_pct'] = 100.0;
        }

        return [
            'lines' => $diffItems,
            'side_by_side' => $sideBySide,
            'unified' => implode("\n", $unified),
            'stats' => $stats,
        ];
    }

    private static function splitLines(string $content): array
    {
        $normalized = preg_replace("/\r\n|\r/", "\n", $content);
        if ($normalized === '') {
            return [];
        }
        return explode("\n", $normalized);
    }

    /**
     * Compute LCS length matrix.
     */
    private static function computeLcsMatrix(array $a, array $b): array
    {
        $m = count($a);
        $n = count($b);

        // Optimization for large files: cap lines to 800 to prevent memory spikes (>100MB)
        $maxLines = 800;
        if ($m > $maxLines) {
            $a = array_slice($a, 0, $maxLines);
            $m = $maxLines;
        }
        if ($n > $maxLines) {
            $b = array_slice($b, 0, $maxLines);
            $n = $maxLines;
        }

        $matrix = [];
        for ($i = 0; $i <= $m; $i++) {
            $matrix[$i] = array_fill(0, $n + 1, 0);
        }

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                if ($a[$i - 1] === $b[$j - 1]) {
                    $matrix[$i][$j] = $matrix[$i - 1][$j - 1] + 1;
                } else {
                    $matrix[$i][$j] = max($matrix[$i - 1][$j], $matrix[$i][$j - 1]);
                }
            }
        }

        return $matrix;
    }

    /**
     * Backtrack matrix to form ordered diff items.
     */
    private static function backtrack(array $matrix, array $a, array $b, int $i, int $j): array
    {
        $items = [];
        $currI = min($i, count($a));
        $currJ = min($j, count($b));

        while ($currI > 0 || $currJ > 0) {
            if ($currI > 0 && $currJ > 0 && $a[$currI - 1] === $b[$currJ - 1]) {
                $items[] = ['type' => 'unchanged', 'text' => $a[$currI - 1]];
                $currI--;
                $currJ--;
            } elseif ($currJ > 0 && ($currI === 0 || $matrix[$currI][$currJ - 1] >= $matrix[$currI - 1][$currJ])) {
                $items[] = ['type' => 'added', 'text' => $b[$currJ - 1]];
                $currJ--;
            } elseif ($currI > 0 && ($currJ === 0 || $matrix[$currI][$currJ - 1] < $matrix[$currI - 1][$currJ])) {
                $items[] = ['type' => 'removed', 'text' => $a[$currI - 1]];
                $currI--;
            }
        }

        return array_reverse($items);
    }
}
