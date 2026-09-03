<?php
/**
 * Minimal, dependency-free QR Code generator (ISO/IEC 18004).
 *
 * Scope is deliberately narrow: byte mode, versions 1-10. That covers the
 * otpauth:// enrolment URIs used by two-factor authentication without pulling
 * in an external library or sending the shared secret to a third-party QR
 * rendering service.
 */

/**
 * Error correction block structure per version and level.
 * [ec codewords per block, [[block count, data codewords per block], ...]]
 */
const QR_EC_BLOCKS = [
    1  => ['L' => [7,  [[1, 19]]],            'M' => [10, [[1, 16]]],            'Q' => [13, [[1, 13]]],            'H' => [17, [[1, 9]]]],
    2  => ['L' => [10, [[1, 34]]],            'M' => [16, [[1, 28]]],            'Q' => [22, [[1, 22]]],            'H' => [28, [[1, 16]]]],
    3  => ['L' => [15, [[1, 55]]],            'M' => [26, [[1, 44]]],            'Q' => [18, [[2, 17]]],            'H' => [22, [[2, 13]]]],
    4  => ['L' => [20, [[1, 80]]],            'M' => [18, [[2, 32]]],            'Q' => [26, [[2, 24]]],            'H' => [16, [[4, 9]]]],
    5  => ['L' => [26, [[1, 108]]],           'M' => [24, [[2, 43]]],            'Q' => [18, [[2, 15], [2, 16]]],   'H' => [22, [[2, 11], [2, 12]]]],
    6  => ['L' => [18, [[2, 68]]],            'M' => [16, [[4, 27]]],            'Q' => [24, [[4, 19]]],            'H' => [28, [[4, 15]]]],
    7  => ['L' => [20, [[2, 78]]],            'M' => [18, [[4, 31]]],            'Q' => [18, [[2, 14], [4, 15]]],   'H' => [26, [[4, 13], [1, 14]]]],
    8  => ['L' => [24, [[2, 97]]],            'M' => [22, [[2, 38], [2, 39]]],   'Q' => [22, [[4, 18], [2, 19]]],   'H' => [26, [[4, 14], [2, 15]]]],
    9  => ['L' => [30, [[2, 116]]],           'M' => [22, [[3, 36], [2, 37]]],   'Q' => [20, [[4, 16], [4, 17]]],   'H' => [24, [[4, 12], [4, 13]]]],
    10 => ['L' => [18, [[2, 68], [2, 69]]],   'M' => [26, [[4, 43], [1, 44]]],   'Q' => [24, [[6, 19], [2, 20]]],   'H' => [28, [[6, 15], [2, 16]]]],
];

const QR_FORMAT_BITS = ['L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2];

const QR_PENALTY_N1 = 3;
const QR_PENALTY_N2 = 3;
const QR_PENALTY_N3 = 40;
const QR_PENALTY_N4 = 10;

class QrCodeException extends Exception {}

function qrDataCodewordCount(int $version, string $ecLevel): int
{
    [, $blocks] = QR_EC_BLOCKS[$version][$ecLevel];
    $total = 0;
    foreach ($blocks as [$count, $dataPerBlock]) {
        $total += $count * $dataPerBlock;
    }
    return $total;
}

function qrCharCountBits(int $version): int
{
    // Byte mode: 8 bits for versions 1-9, 16 bits for versions 10-40.
    return $version <= 9 ? 8 : 16;
}

function qrPickVersion(int $dataLength, string $ecLevel): int
{
    foreach (array_keys(QR_EC_BLOCKS) as $version) {
        $capacityBits = qrDataCodewordCount($version, $ecLevel) * 8;
        $requiredBits = 4 + qrCharCountBits($version) + ($dataLength * 8);
        if ($requiredBits <= $capacityBits) {
            return $version;
        }
    }

    throw new QrCodeException('Data too long for supported QR versions (max version 10).');
}

/* ---------------------------------------------------------------------------
 * GF(256) arithmetic for Reed-Solomon error correction
 * ------------------------------------------------------------------------ */

function qrGfTables(): array
{
    static $tables = null;
    if ($tables !== null) {
        return $tables;
    }

    $exp = array_fill(0, 512, 0);
    $log = array_fill(0, 256, 0);

    $x = 1;
    for ($i = 0; $i < 255; $i++) {
        $exp[$i] = $x;
        $log[$x] = $i;
        $x <<= 1;
        if ($x & 0x100) {
            $x ^= 0x11D; // primitive polynomial x^8 + x^4 + x^3 + x^2 + 1
        }
    }
    for ($i = 255; $i < 512; $i++) {
        $exp[$i] = $exp[$i - 255];
    }

    $tables = [$exp, $log];
    return $tables;
}

function qrGfMul(int $a, int $b): int
{
    if ($a === 0 || $b === 0) {
        return 0;
    }
    [$exp, $log] = qrGfTables();
    return $exp[$log[$a] + $log[$b]];
}

/**
 * Build the RS generator polynomial of the given degree, as coefficients in
 * descending order of power, i.e. the product of (x - a^i) for i in [0, degree).
 */
function qrRsGeneratorPoly(int $degree): array
{
    $poly = [1];
    [$exp] = qrGfTables();

    for ($i = 0; $i < $degree; $i++) {
        $next = array_fill(0, count($poly) + 1, 0);
        foreach ($poly as $index => $coeff) {
            $next[$index] ^= $coeff;                          // multiply by x
            $next[$index + 1] ^= qrGfMul($coeff, $exp[$i]);   // multiply by a^i
        }
        $poly = $next;
    }

    return $poly;
}

/**
 * @param int[] $data Data codewords
 * @return int[] Error correction codewords
 */
function qrRsRemainder(array $data, int $ecCount): array
{
    $generator = qrRsGeneratorPoly($ecCount);
    $remainder = array_fill(0, $ecCount, 0);

    foreach ($data as $codeword) {
        $factor = $codeword ^ array_shift($remainder);
        $remainder[] = 0;
        for ($i = 0; $i < $ecCount; $i++) {
            $remainder[$i] ^= qrGfMul($generator[$i + 1], $factor);
        }
    }

    return $remainder;
}

/* ---------------------------------------------------------------------------
 * Codeword assembly
 * ------------------------------------------------------------------------ */

/**
 * Encode the payload into the final interleaved codeword sequence.
 *
 * @return int[]
 */
function qrBuildCodewords(string $data, int $version, string $ecLevel): array
{
    [$ecPerBlock, $blockSpec] = QR_EC_BLOCKS[$version][$ecLevel];
    $dataCodewordCount = qrDataCodewordCount($version, $ecLevel);

    // Bit stream: mode indicator, character count, payload.
    $bits = '0100';
    $bits .= str_pad(decbin(strlen($data)), qrCharCountBits($version), '0', STR_PAD_LEFT);
    for ($i = 0, $len = strlen($data); $i < $len; $i++) {
        $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
    }

    $capacityBits = $dataCodewordCount * 8;

    // Terminator (up to four zero bits) then pad to a byte boundary.
    $bits .= str_repeat('0', min(4, $capacityBits - strlen($bits)));
    if (strlen($bits) % 8 !== 0) {
        $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
    }

    $codewords = [];
    foreach (str_split($bits, 8) as $byte) {
        $codewords[] = bindec($byte);
    }

    // Alternating pad codewords fill the remaining capacity.
    $padBytes = [0xEC, 0x11];
    $padIndex = 0;
    while (count($codewords) < $dataCodewordCount) {
        $codewords[] = $padBytes[$padIndex % 2];
        $padIndex++;
    }

    // Split into blocks and compute error correction per block.
    $dataBlocks = [];
    $ecBlocks = [];
    $offset = 0;
    foreach ($blockSpec as [$count, $dataPerBlock]) {
        for ($b = 0; $b < $count; $b++) {
            $block = array_slice($codewords, $offset, $dataPerBlock);
            $offset += $dataPerBlock;
            $dataBlocks[] = $block;
            $ecBlocks[] = qrRsRemainder($block, $ecPerBlock);
        }
    }

    // Interleave data codewords, then error correction codewords.
    $result = [];
    $maxDataLength = max(array_map('count', $dataBlocks));
    for ($i = 0; $i < $maxDataLength; $i++) {
        foreach ($dataBlocks as $block) {
            if (isset($block[$i])) {
                $result[] = $block[$i];
            }
        }
    }
    for ($i = 0; $i < $ecPerBlock; $i++) {
        foreach ($ecBlocks as $block) {
            $result[] = $block[$i];
        }
    }

    return $result;
}

/* ---------------------------------------------------------------------------
 * Matrix construction
 * ------------------------------------------------------------------------ */

function qrAlignmentPositions(int $version): array
{
    if ($version === 1) {
        return [];
    }

    $size = $version * 4 + 17;
    $count = intdiv($version, 7) + 2;
    $step = intdiv($version * 4 + $count * 2 + 1, $count * 2 - 2) * 2;

    $positions = array_fill(0, $count, 0);
    $positions[0] = 6;
    for ($i = $count - 1, $pos = $size - 7; $i >= 1; $i--, $pos -= $step) {
        $positions[$i] = $pos;
    }

    return $positions;
}

function qrDrawFinderPattern(array &$matrix, array &$isFunction, int $size, int $centerRow, int $centerCol): void
{
    for ($dr = -4; $dr <= 4; $dr++) {
        for ($dc = -4; $dc <= 4; $dc++) {
            $row = $centerRow + $dr;
            $col = $centerCol + $dc;
            if ($row < 0 || $row >= $size || $col < 0 || $col >= $size) {
                continue;
            }
            $distance = max(abs($dr), abs($dc));
            $matrix[$row][$col] = ($distance !== 2 && $distance !== 4) ? 1 : 0;
            $isFunction[$row][$col] = true;
        }
    }
}

function qrDrawAlignmentPattern(array &$matrix, array &$isFunction, int $centerRow, int $centerCol): void
{
    for ($dr = -2; $dr <= 2; $dr++) {
        for ($dc = -2; $dc <= 2; $dc++) {
            $matrix[$centerRow + $dr][$centerCol + $dc] = max(abs($dr), abs($dc)) !== 1 ? 1 : 0;
            $isFunction[$centerRow + $dr][$centerCol + $dc] = true;
        }
    }
}

function qrFormatInfoBits(string $ecLevel, int $mask): int
{
    $data = (QR_FORMAT_BITS[$ecLevel] << 3) | $mask;
    $remainder = $data;
    for ($i = 0; $i < 10; $i++) {
        $remainder = ($remainder << 1) ^ (($remainder >> 9) * 0x537);
    }
    return (($data << 10) | ($remainder & 0x3FF)) ^ 0x5412;
}

function qrVersionInfoBits(int $version): int
{
    $remainder = $version;
    for ($i = 0; $i < 12; $i++) {
        $remainder = ($remainder << 1) ^ (($remainder >> 11) * 0x1F25);
    }
    return ($version << 12) | ($remainder & 0xFFF);
}

function qrDrawFormatBits(array &$matrix, array &$isFunction, int $size, string $ecLevel, int $mask): void
{
    $bits = qrFormatInfoBits($ecLevel, $mask);
    $bit = static fn(int $index): int => ($bits >> $index) & 1;

    // First copy, around the top-left finder pattern.
    for ($i = 0; $i <= 5; $i++) {
        $matrix[$i][8] = $bit($i);
        $isFunction[$i][8] = true;
    }
    $matrix[7][8] = $bit(6);
    $isFunction[7][8] = true;
    $matrix[8][8] = $bit(7);
    $isFunction[8][8] = true;
    $matrix[8][7] = $bit(8);
    $isFunction[8][7] = true;
    for ($i = 9; $i < 15; $i++) {
        $matrix[8][14 - $i] = $bit($i);
        $isFunction[8][14 - $i] = true;
    }

    // Second copy, split between the other two finder patterns.
    for ($i = 0; $i < 8; $i++) {
        $matrix[8][$size - 1 - $i] = $bit($i);
        $isFunction[8][$size - 1 - $i] = true;
    }
    for ($i = 8; $i < 15; $i++) {
        $matrix[$size - 15 + $i][8] = $bit($i);
        $isFunction[$size - 15 + $i][8] = true;
    }

    // Dark module.
    $matrix[$size - 8][8] = 1;
    $isFunction[$size - 8][8] = true;
}

function qrDrawVersionBits(array &$matrix, array &$isFunction, int $size, int $version): void
{
    if ($version < 7) {
        return;
    }

    $bits = qrVersionInfoBits($version);
    for ($i = 0; $i < 18; $i++) {
        $bit = ($bits >> $i) & 1;
        $a = $size - 11 + ($i % 3);
        $b = intdiv($i, 3);

        $matrix[$b][$a] = $bit;
        $isFunction[$b][$a] = true;
        $matrix[$a][$b] = $bit;
        $isFunction[$a][$b] = true;
    }
}

function qrApplyMask(array &$matrix, array $isFunction, int $size, int $mask): void
{
    for ($row = 0; $row < $size; $row++) {
        for ($col = 0; $col < $size; $col++) {
            if ($isFunction[$row][$col]) {
                continue;
            }

            switch ($mask) {
                case 0: $invert = ($row + $col) % 2 === 0; break;
                case 1: $invert = $row % 2 === 0; break;
                case 2: $invert = $col % 3 === 0; break;
                case 3: $invert = ($row + $col) % 3 === 0; break;
                case 4: $invert = (intdiv($col, 3) + intdiv($row, 2)) % 2 === 0; break;
                case 5: $invert = (($row * $col) % 2 + ($row * $col) % 3) === 0; break;
                case 6: $invert = ((($row * $col) % 2 + ($row * $col) % 3) % 2) === 0; break;
                case 7: $invert = ((($row + $col) % 2 + ($row * $col) % 3) % 2) === 0; break;
                default: throw new QrCodeException('Invalid mask pattern: ' . $mask);
            }

            if ($invert) {
                $matrix[$row][$col] ^= 1;
            }
        }
    }
}

/**
 * Count occurrences of the 1:1:3:1:1 finder-like pattern with a 4-module
 * light margin, which penalty rule 3 penalises.
 */
function qrCountFinderLikePatterns(array $line): int
{
    $bits = implode('', $line);
    $count = 0;
    foreach (['10111010000', '00001011101'] as $pattern) {
        $offset = 0;
        while (($position = strpos($bits, $pattern, $offset)) !== false) {
            $count++;
            $offset = $position + 1;
        }
    }
    return $count;
}

function qrPenaltyScore(array $matrix, int $size): int
{
    $score = 0;

    // Rule 1: runs of five or more same-coloured modules in a row or column.
    // Rule 3: finder-like patterns in a row or column.
    for ($i = 0; $i < $size; $i++) {
        $row = $matrix[$i];
        $column = array_column($matrix, $i);

        foreach ([$row, $column] as $line) {
            $runLength = 1;
            for ($j = 1; $j < $size; $j++) {
                if ($line[$j] === $line[$j - 1]) {
                    $runLength++;
                } else {
                    if ($runLength >= 5) {
                        $score += QR_PENALTY_N1 + ($runLength - 5);
                    }
                    $runLength = 1;
                }
            }
            if ($runLength >= 5) {
                $score += QR_PENALTY_N1 + ($runLength - 5);
            }

            $score += qrCountFinderLikePatterns($line) * QR_PENALTY_N3;
        }
    }

    // Rule 2: 2x2 blocks of a single colour.
    for ($row = 0; $row < $size - 1; $row++) {
        for ($col = 0; $col < $size - 1; $col++) {
            $value = $matrix[$row][$col];
            if ($value === $matrix[$row][$col + 1]
                && $value === $matrix[$row + 1][$col]
                && $value === $matrix[$row + 1][$col + 1]) {
                $score += QR_PENALTY_N2;
            }
        }
    }

    // Rule 4: deviation from an even balance of dark and light modules.
    $dark = 0;
    foreach ($matrix as $row) {
        $dark += array_sum($row);
    }
    $total = $size * $size;
    $deviation = intdiv(abs($dark * 20 - $total * 10) + $total - 1, $total) - 1;
    $score += $deviation * QR_PENALTY_N4;

    return $score;
}

/**
 * Encode text into a QR code module matrix.
 *
 * @param int $forceMask -1 selects the lowest-penalty mask (per the spec).
 * @return int[][] Row-major matrix of 0 (light) and 1 (dark)
 */
function qrEncodeMatrix(string $text, string $ecLevel = 'M', int $forceMask = -1): array
{
    $ecLevel = strtoupper($ecLevel);
    if (!isset(QR_FORMAT_BITS[$ecLevel])) {
        throw new QrCodeException('Invalid error correction level: ' . $ecLevel);
    }

    $version = qrPickVersion(strlen($text), $ecLevel);
    $size = $version * 4 + 17;
    $codewords = qrBuildCodewords($text, $version, $ecLevel);

    $matrix = array_fill(0, $size, array_fill(0, $size, 0));
    $isFunction = array_fill(0, $size, array_fill(0, $size, false));

    // Timing patterns.
    for ($i = 0; $i < $size; $i++) {
        $matrix[6][$i] = $i % 2 === 0 ? 1 : 0;
        $isFunction[6][$i] = true;
        $matrix[$i][6] = $i % 2 === 0 ? 1 : 0;
        $isFunction[$i][6] = true;
    }

    qrDrawFinderPattern($matrix, $isFunction, $size, 3, 3);
    qrDrawFinderPattern($matrix, $isFunction, $size, 3, $size - 4);
    qrDrawFinderPattern($matrix, $isFunction, $size, $size - 4, 3);

    $alignment = qrAlignmentPositions($version);
    $alignmentCount = count($alignment);
    for ($i = 0; $i < $alignmentCount; $i++) {
        for ($j = 0; $j < $alignmentCount; $j++) {
            $isCorner = ($i === 0 && $j === 0)
                || ($i === 0 && $j === $alignmentCount - 1)
                || ($i === $alignmentCount - 1 && $j === 0);
            if (!$isCorner) {
                qrDrawAlignmentPattern($matrix, $isFunction, $alignment[$i], $alignment[$j]);
            }
        }
    }

    // Reserve the format and version areas before laying out data.
    qrDrawFormatBits($matrix, $isFunction, $size, $ecLevel, 0);
    qrDrawVersionBits($matrix, $isFunction, $size, $version);

    // Lay out codewords in the two-column zigzag, skipping function modules.
    $totalBits = count($codewords) * 8;
    $bitIndex = 0;
    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right === 6) {
            $right = 5;
        }
        for ($vertical = 0; $vertical < $size; $vertical++) {
            for ($j = 0; $j < 2; $j++) {
                $col = $right - $j;
                $upward = (($right + 1) & 2) === 0;
                $row = $upward ? $size - 1 - $vertical : $vertical;

                if (!$isFunction[$row][$col] && $bitIndex < $totalBits) {
                    $matrix[$row][$col] = ($codewords[$bitIndex >> 3] >> (7 - ($bitIndex & 7))) & 1;
                    $bitIndex++;
                }
            }
        }
    }

    if ($forceMask >= 0 && $forceMask <= 7) {
        qrApplyMask($matrix, $isFunction, $size, $forceMask);
        qrDrawFormatBits($matrix, $isFunction, $size, $ecLevel, $forceMask);
        return $matrix;
    }

    $bestMask = 0;
    $bestScore = PHP_INT_MAX;
    for ($mask = 0; $mask < 8; $mask++) {
        $candidate = $matrix;
        qrApplyMask($candidate, $isFunction, $size, $mask);
        qrDrawFormatBits($candidate, $isFunction, $size, $ecLevel, $mask);

        $score = qrPenaltyScore($candidate, $size);
        if ($score < $bestScore) {
            $bestScore = $score;
            $bestMask = $mask;
        }
    }

    qrApplyMask($matrix, $isFunction, $size, $bestMask);
    qrDrawFormatBits($matrix, $isFunction, $size, $ecLevel, $bestMask);

    return $matrix;
}

/**
 * Render text as a standalone SVG QR code.
 *
 * The dark modules are merged into a single path so the markup stays small
 * enough to embed directly in a page or a data URI.
 */
function qrCodeSvg(string $text, string $ecLevel = 'M', int $moduleSize = 6, int $quietZone = 4): string
{
    $matrix = qrEncodeMatrix($text, $ecLevel);
    $size = count($matrix);
    $dimension = ($size + ($quietZone * 2)) * $moduleSize;

    $path = '';
    foreach ($matrix as $rowIndex => $row) {
        $col = 0;
        while ($col < $size) {
            if ($row[$col] !== 1) {
                $col++;
                continue;
            }

            // Merge horizontally adjacent dark modules into one rectangle.
            $runStart = $col;
            while ($col < $size && $row[$col] === 1) {
                $col++;
            }

            $x = ($runStart + $quietZone) * $moduleSize;
            $y = ($rowIndex + $quietZone) * $moduleSize;
            $width = ($col - $runStart) * $moduleSize;
            $path .= "M{$x} {$y}h{$width}v{$moduleSize}h-{$width}z";
        }
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $dimension . '" height="' . $dimension . '" '
        . 'viewBox="0 0 ' . $dimension . ' ' . $dimension . '" shape-rendering="crispEdges" role="img" '
        . 'aria-label="Two-factor authentication QR code">'
        . '<rect width="' . $dimension . '" height="' . $dimension . '" fill="#ffffff"/>'
        . '<path d="' . $path . '" fill="#000000"/>'
        . '</svg>';
}

/**
 * Render text as a QR code in a base64 data URI, suitable for an <img> src.
 */
function qrCodeSvgDataUri(string $text, string $ecLevel = 'M', int $moduleSize = 6, int $quietZone = 4): string
{
    return 'data:image/svg+xml;base64,' . base64_encode(qrCodeSvg($text, $ecLevel, $moduleSize, $quietZone));
}
