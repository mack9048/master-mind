<?php
declare(strict_types=1);

/**
 * Small QR Code encoder (ISO/IEC 18004): byte mode, error-correction level M, versions 1–6
 * (up to 106 bytes – plenty for a Reg ID or a short link). No external libraries.
 *
 * qr_matrix('MM26-ABC123') → array of rows, each an array of bools (true = dark module),
 * without the quiet zone (leave 4 modules of white around it when drawing).
 */
function qr_matrix(string $data): array
{
    // [total codewords, ec codewords per block, number of blocks] for level M, versions 1..6
    static $spec = [1 => [26, 10, 1], 2 => [44, 16, 1], 3 => [70, 26, 1], 4 => [100, 18, 2], 5 => [134, 24, 2], 6 => [172, 16, 4]];
    static $align = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34]];

    $bytes = array_values(unpack('C*', $data) ?: []);
    $len = count($bytes);

    // pick the smallest version that fits: 4 bits mode + 8 bits length + data
    $ver = 0;
    foreach ($spec as $v => [$total, $ecLen, $blocks]) {
        if (4 + 8 + 8 * $len <= ($total - $ecLen * $blocks) * 8) { $ver = $v; break; }
    }
    if ($ver === 0) {
        throw new InvalidArgumentException('QR data too long');
    }
    [$total, $ecLen, $numBlocks] = $spec[$ver];
    $dataCap = $total - $ecLen * $numBlocks;

    // ---- bit stream: mode 0100 (byte), 8-bit count, data, terminator, pad
    $bits = [];
    $push = function (int $val, int $n) use (&$bits): void {
        for ($i = $n - 1; $i >= 0; $i--) { $bits[] = ($val >> $i) & 1; }
    };
    $push(0b0100, 4);
    $push($len, 8);
    foreach ($bytes as $b) { $push($b, 8); }
    $push(0, min(4, $dataCap * 8 - count($bits)));
    while (count($bits) % 8) { $bits[] = 0; }
    $codewords = [];
    foreach (array_chunk($bits, 8) as $chunk) { $codewords[] = bindec(implode('', $chunk)); }
    for ($pad = 0xEC; count($codewords) < $dataCap; $pad ^= 0xEC ^ 0x11) { $codewords[] = $pad; }

    // ---- Reed–Solomon over GF(256), polynomial 0x11D
    $mul = function (int $x, int $y): int {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z & 0xFF;
    };
    $divisor = array_fill(0, $ecLen - 1, 0);
    $divisor[] = 1;
    $root = 1;
    for ($i = 0; $i < $ecLen; $i++) {
        for ($j = 0; $j < $ecLen; $j++) {
            $divisor[$j] = $mul($divisor[$j], $root);
            if ($j + 1 < $ecLen) { $divisor[$j] ^= $divisor[$j + 1]; }
        }
        $root = $mul($root, 0x02);
    }
    $ecc = function (array $block) use ($divisor, $ecLen, $mul): array {
        $res = array_fill(0, $ecLen, 0);
        foreach ($block as $b) {
            $factor = $b ^ array_shift($res);
            $res[] = 0;
            for ($i = 0; $i < $ecLen; $i++) { $res[$i] ^= $mul($divisor[$i], $factor); }
        }
        return $res;
    };

    // ---- split into equal blocks (true for level M, v1–6) and interleave
    $blockLen = intdiv($dataCap, $numBlocks);
    $dataBlocks = array_chunk($codewords, $blockLen);
    $eccBlocks = array_map($ecc, $dataBlocks);
    $final = [];
    for ($i = 0; $i < $blockLen; $i++) { foreach ($dataBlocks as $b) { $final[] = $b[$i]; } }
    for ($i = 0; $i < $ecLen; $i++) { foreach ($eccBlocks as $b) { $final[] = $b[$i]; } }

    // ---- function patterns
    $size = 17 + 4 * $ver;
    $mod = array_fill(0, $size, array_fill(0, $size, false));
    $fn = array_fill(0, $size, array_fill(0, $size, false));
    $set = function (int $x, int $y, bool $dark) use (&$mod, &$fn): void { $mod[$y][$x] = $dark; $fn[$y][$x] = true; };

    for ($i = 0; $i < $size; $i++) { $set(6, $i, $i % 2 === 0); $set($i, 6, $i % 2 === 0); }
    foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as [$cx, $cy]) {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $d = max(abs($dx), abs($dy));
                $x = $cx + $dx; $y = $cy + $dy;
                if ($x >= 0 && $x < $size && $y >= 0 && $y < $size) { $set($x, $y, $d !== 2 && $d !== 4); }
            }
        }
    }
    $ap = $align[$ver];
    $n = count($ap);
    for ($i = 0; $i < $n; $i++) {
        for ($j = 0; $j < $n; $j++) {
            if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) { continue; }
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) { $set($ap[$i] + $dx, $ap[$j] + $dy, max(abs($dx), abs($dy)) !== 1); }
            }
        }
    }
    $drawFormat = function (int $mask) use ($set, $size): void {
        $data = (0 << 3) | $mask;                 // level M = 00
        $rem = $data;
        for ($i = 0; $i < 10; $i++) { $rem = ($rem << 1) ^ (($rem >> 9) * 0x537); }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $b = fn (int $i): bool => (($bits >> $i) & 1) === 1;
        for ($i = 0; $i <= 5; $i++) { $set(8, $i, $b($i)); }
        $set(8, 7, $b(6)); $set(8, 8, $b(7)); $set(7, 8, $b(8));
        for ($i = 9; $i < 15; $i++) { $set(14 - $i, 8, $b($i)); }
        for ($i = 0; $i < 8; $i++) { $set($size - 1 - $i, 8, $b($i)); }
        for ($i = 8; $i < 15; $i++) { $set(8, $size - 15 + $i, $b($i)); }
        $set(8, $size - 8, true);                  // dark module
    };
    $drawFormat(0);                                // reserve format areas

    // ---- place data (zig-zag from bottom-right)
    $i = 0;
    $totalBits = count($final) * 8;
    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right === 6) { $right = 5; }
        for ($vert = 0; $vert < $size; $vert++) {
            for ($j = 0; $j < 2; $j++) {
                $x = $right - $j;
                $upward = (($right + 1) & 2) === 0;
                $y = $upward ? $size - 1 - $vert : $vert;
                if (!$fn[$y][$x] && $i < $totalBits) {
                    $mod[$y][$x] = (($final[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                    $i++;
                }
            }
        }
    }

    // ---- choose the mask with the lowest penalty
    $masks = [
        fn ($x, $y) => ($x + $y) % 2 === 0,
        fn ($x, $y) => $y % 2 === 0,
        fn ($x, $y) => $x % 3 === 0,
        fn ($x, $y) => ($x + $y) % 3 === 0,
        fn ($x, $y) => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
        fn ($x, $y) => ($x * $y) % 2 + ($x * $y) % 3 === 0,
        fn ($x, $y) => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
        fn ($x, $y) => ((($x + $y) % 2) + ($x * $y) % 3) % 2 === 0,
    ];
    $best = null; $bestScore = PHP_INT_MAX;
    foreach ($masks as $m => $f) {
        $grid = $mod;
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if (!$fn[$y][$x] && $f($x, $y)) { $grid[$y][$x] = !$grid[$y][$x]; }
            }
        }
        // apply format bits for this mask on a copy
        $saveMod = $mod; $saveFn = $fn;
        $mod = $grid; $drawFormat($m); $grid = $mod;
        $mod = $saveMod; $fn = $saveFn;
        $score = qr_penalty($grid);
        if ($score < $bestScore) { $bestScore = $score; $best = $grid; }
    }
    return $best;
}

/** Standard QR penalty (rules 1–4). */
function qr_penalty(array $g): int
{
    $size = count($g);
    $score = 0;
    $lines = [];
    for ($i = 0; $i < $size; $i++) {
        $row = ''; $col = '';
        for ($j = 0; $j < $size; $j++) { $row .= $g[$i][$j] ? '1' : '0'; $col .= $g[$j][$i] ? '1' : '0'; }
        $lines[] = $row; $lines[] = $col;
    }
    foreach ($lines as $l) {
        preg_match_all('/0{5,}|1{5,}/', $l, $m);
        foreach ($m[0] as $run) { $score += 3 + strlen($run) - 5; }
        $score += 40 * (substr_count('0000' . $l . '0000', '10111010000') + substr_count('0000' . $l . '0000', '00001011101'));
    }
    $dark = 0;
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            $dark += $g[$y][$x] ? 1 : 0;
            if ($x < $size - 1 && $y < $size - 1 && $g[$y][$x] === $g[$y][$x + 1] && $g[$y][$x] === $g[$y + 1][$x] && $g[$y][$x] === $g[$y + 1][$x + 1]) {
                $score += 3;
            }
        }
    }
    $score += 10 * intdiv(abs($dark * 20 - $size * $size * 10), $size * $size);
    return $score;
}
