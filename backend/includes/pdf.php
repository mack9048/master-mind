<?php
declare(strict_types=1);

/**
 * Tiny PDF writer – just enough for the entry ticket (shapes + Helvetica text).
 * Uses the PDF standard fonts (no font files needed). Text is converted to
 * Windows-1252, so stick to Latin characters (₹ is written as "Rs.").
 * Coordinates are in millimetres from the TOP-LEFT corner.
 */
final class MiniPdf
{
    private const PT = 2.834645669; // points per mm

    /** Helvetica / Helvetica-Bold widths for ASCII 32..126 (1/1000 em, standard Adobe metrics) */
    private const W_REG = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
    private const W_BOLD = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584];

    private string $ops = '';

    public function __construct(private float $w, private float $h)
    {
    }

    private function n(float $v): string
    {
        return rtrim(rtrim(sprintf('%.3F', $v), '0'), '.') ?: '0';
    }

    private function x(float $mm): string { return $this->n($mm * self::PT); }
    private function y(float $mm): string { return $this->n(($this->h - $mm) * self::PT); }

    /** colour as '#rrggbb' */
    private function rgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        return implode(' ', array_map(fn ($i) => $this->n(hexdec(substr($hex, $i, 2)) / 255), [0, 2, 4]));
    }

    public function rect(float $x, float $y, float $w, float $h, string $fill, float $r = 0): void
    {
        $this->ops .= $this->rgb($fill) . " rg\n";
        if ($r <= 0) {
            $this->ops .= sprintf("%s %s %s %s re f\n", $this->x($x), $this->y($y + $h), $this->n($w * self::PT), $this->n($h * self::PT));
            return;
        }
        $r = min($r, $w / 2, $h / 2);
        $k = 0.5523 * $r;
        $p = fn ($px, $py) => $this->x($px) . ' ' . $this->y($py);
        $this->ops .= $p($x + $r, $y) . " m\n"
            . $p($x + $w - $r, $y) . " l\n"
            . $p($x + $w - $r + $k, $y) . ' ' . $p($x + $w, $y + $r - $k) . ' ' . $p($x + $w, $y + $r) . " c\n"
            . $p($x + $w, $y + $h - $r) . " l\n"
            . $p($x + $w, $y + $h - $r + $k) . ' ' . $p($x + $w - $r + $k, $y + $h) . ' ' . $p($x + $w - $r, $y + $h) . " c\n"
            . $p($x + $r, $y + $h) . " l\n"
            . $p($x + $r - $k, $y + $h) . ' ' . $p($x, $y + $h - $r + $k) . ' ' . $p($x, $y + $h - $r) . " c\n"
            . $p($x, $y + $r) . " l\n"
            . $p($x, $y + $r - $k) . ' ' . $p($x + $r - $k, $y) . ' ' . $p($x + $r, $y) . " c\nf\n";
    }

    public function circle(float $cx, float $cy, float $r, string $fill): void
    {
        $this->rect($cx - $r, $cy - $r, 2 * $r, 2 * $r, $fill, $r);
    }

    /** Horizontal gradient made of thin strips through the given colour stops. */
    public function gradientBar(float $x, float $y, float $w, float $h, array $stops, float $r = 0): void
    {
        $steps = 60;
        $col = function (float $t) use ($stops): string {
            $n = count($stops) - 1;
            $seg = min($n - 1, (int) floor($t * $n));
            $lt = $t * $n - $seg;
            $a = array_map('hexdec', str_split(ltrim($stops[$seg], '#'), 2));
            $b = array_map('hexdec', str_split(ltrim($stops[$seg + 1], '#'), 2));
            return sprintf('#%02x%02x%02x', ...array_map(fn ($i) => (int) round($a[$i] + ($b[$i] - $a[$i]) * $lt), [0, 1, 2]));
        };
        for ($i = 0; $i < $steps; $i++) {
            $this->rect($x + $w * $i / $steps, $y, $w / $steps + 0.05, $h, $col($i / ($steps - 1)));
        }
        if ($r > 0) { // round the ends with the end colours
            $this->circle($x, $y + $h / 2, $h / 2, $stops[0]);
            $this->circle($x + $w, $y + $h / 2, $h / 2, end($stops));
        }
    }

    public function line(float $x1, float $y1, float $x2, float $y2, string $color, float $width = 0.3, ?array $dash = null): void
    {
        $this->ops .= $this->rgb($color) . " RG\n" . $this->n($width * self::PT) . " w\n"
            . ($dash ? '[' . implode(' ', array_map(fn ($d) => $this->n($d * self::PT), $dash)) . '] 0 d' : '[] 0 d') . "\n"
            . $this->x($x1) . ' ' . $this->y($y1) . ' m ' . $this->x($x2) . ' ' . $this->y($y2) . " l S\n";
    }

    /**
     * Free path: list of commands in mm, e.g. [['M',x,y], ['L',x,y], ['C',x1,y1,x2,y2,x,y], ['Z']].
     * $fill and/or $stroke colours; $width = stroke width in mm.
     */
    public function path(array $cmds, ?string $fill, ?string $stroke = null, float $width = 0.4): void
    {
        $o = '';
        foreach ($cmds as $c) {
            switch ($c[0]) {
                case 'M': $o .= $this->x($c[1]) . ' ' . $this->y($c[2]) . " m\n"; break;
                case 'L': $o .= $this->x($c[1]) . ' ' . $this->y($c[2]) . " l\n"; break;
                case 'C': $o .= $this->x($c[1]) . ' ' . $this->y($c[2]) . ' ' . $this->x($c[3]) . ' ' . $this->y($c[4]) . ' ' . $this->x($c[5]) . ' ' . $this->y($c[6]) . " c\n"; break;
                case 'Z': $o .= "h\n"; break;
            }
        }
        if ($fill) { $o = $this->rgb($fill) . " rg\n" . $o; }
        if ($stroke) { $o = $this->rgb($stroke) . ' RG ' . $this->n($width * self::PT) . " w [] 0 d 1 j\n" . $o; }
        $this->ops .= $o . ($fill && $stroke ? "B\n" : ($fill ? "f\n" : "S\n"));
    }

    /** Smooth closed blob through points (Catmull-Rom → Bézier). */
    public function blob(array $pts, string $fill, ?string $stroke = null, float $width = 0.4): void
    {
        $n = count($pts);
        $cmds = [['M', $pts[0][0], $pts[0][1]]];
        for ($i = 0; $i < $n; $i++) {
            [$p0, $p1, $p2, $p3] = [$pts[($i - 1 + $n) % $n], $pts[$i], $pts[($i + 1) % $n], $pts[($i + 2) % $n]];
            $cmds[] = ['C', $p1[0] + ($p2[0] - $p0[0]) / 6, $p1[1] + ($p2[1] - $p0[1]) / 6,
                            $p2[0] - ($p3[0] - $p1[0]) / 6, $p2[1] - ($p3[1] - $p1[1]) / 6, $p2[0], $p2[1]];
        }
        $cmds[] = ['Z'];
        $this->path($cmds, $fill, $stroke, $width);
    }

    /** Organic blob around (cx, cy) with radius r; $seed makes each one different but repeatable. */
    public function organicBlob(float $cx, float $cy, float $rx, float $ry, int $seed, string $fill, ?string $stroke = null, float $width = 0.4): void
    {
        $pts = [];
        for ($i = 0; $i < 8; $i++) {
            $a = $i * M_PI / 4 + $seed * 0.37;
            $k = 0.78 + 0.22 * sin($seed * 12.9898 + $i * 78.233);
            $pts[] = [$cx + cos($a) * $rx * $k, $cy + sin($a) * $ry * $k];
        }
        $this->blob($pts, $fill, $stroke, $width);
    }

    /** Rotate everything drawn until endTransform() by $deg (counter-clockwise) around (cx, cy) in mm. */
    public function rotate(float $deg, float $cx, float $cy): void
    {
        $a = deg2rad($deg);
        $c = cos($a); $s = sin($a);
        $px = $cx * self::PT; $py = ($this->h - $cy) * self::PT;
        $this->ops .= sprintf("q %s %s %s %s %s %s cm\n", $this->n($c), $this->n($s), $this->n(-$s), $this->n($c),
            $this->n($px - $px * $c + $py * $s), $this->n($py - $px * $s - $py * $c));
    }

    public function endTransform(): void
    {
        $this->ops .= "Q\n";
    }

    public function strokeRect(float $x, float $y, float $w, float $h, string $color, float $width = 0.4, float $r = 0): void
    {
        if ($r <= 0) {
            $this->ops .= $this->rgb($color) . ' RG ' . $this->n($width * self::PT) . " w [] 0 d\n"
                . sprintf("%s %s %s %s re S\n", $this->x($x), $this->y($y + $h), $this->n($w * self::PT), $this->n($h * self::PT));
            return;
        }
        $k = 0.5523 * $r;
        $this->path([
            ['M', $x + $r, $y], ['L', $x + $w - $r, $y], ['C', $x + $w - $r + $k, $y, $x + $w, $y + $r - $k, $x + $w, $y + $r],
            ['L', $x + $w, $y + $h - $r], ['C', $x + $w, $y + $h - $r + $k, $x + $w - $r + $k, $y + $h, $x + $w - $r, $y + $h],
            ['L', $x + $r, $y + $h], ['C', $x + $r - $k, $y + $h, $x, $y + $h - $r + $k, $x, $y + $h - $r],
            ['L', $x, $y + $r], ['C', $x, $y + $r - $k, $x + $r - $k, $y, $x + $r, $y], ['Z'],
        ], null, $color, $width);
    }

    /**
     * Chunky "sticker" text: shadow + thick outline + fill (font: 'B' bold, 'I' bold-oblique).
     */
    public function stickerText(float $x, float $y, string $s, float $size, string $fill, string $outline, float $outlineW = 0.9, float $shadow = 0.9, string $font = 'B', string $align = 'L', float $spacing = 0): float
    {
        $w = $this->textWidth($s, $size, true, $spacing);
        if ($align === 'C') { $x -= $w / 2; }
        if ($align === 'R') { $x -= $w; }
        $t = strtr(self::enc($s), ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
        $f = $font === 'I' ? 'F3' : 'F2';
        $base = '/' . $f . ' ' . $this->n($size) . ' Tf ' . $this->n($spacing) . ' Tc ';
        $this->ops .= "[] 0 d\n"; // solid outline even after a dashed line
        $lw = $this->n($outlineW * self::PT);
        // shadow (outlined so it matches the outline thickness)
        if ($shadow > 0) {
            $this->ops .= 'BT ' . $this->rgb($outline) . ' rg ' . $this->rgb($outline) . ' RG ' . $lw . ' w 1 j 2 Tr ' . $base
                . $this->x($x + $shadow) . ' ' . $this->y($y + $shadow) . " Td ($t) Tj ET\n";
        }
        $this->ops .= 'BT ' . $this->rgb($outline) . ' RG ' . $lw . ' w 1 j 1 Tr ' . $base . $this->x($x) . ' ' . $this->y($y) . " Td ($t) Tj ET\n";
        $this->ops .= 'BT ' . $this->rgb($fill) . ' rg 0 Tr ' . $base . $this->x($x) . ' ' . $this->y($y) . " Td ($t) Tj ET\n";
        return $w;
    }

    /** Filled polygon; $pts = [[x,y], ...] in mm. */
    public function polygon(array $pts, string $fill): void
    {
        $this->ops .= $this->rgb($fill) . " rg\n";
        foreach ($pts as $i => [$px, $py]) {
            $this->ops .= $this->x($px) . ' ' . $this->y($py) . ($i === 0 ? " m\n" : " l\n");
        }
        $this->ops .= "h f\n";
    }

    /** Five-pointed star centred at (cx, cy). */
    public function star(float $cx, float $cy, float $r, string $fill): void
    {
        $pts = [];
        for ($i = 0; $i < 10; $i++) {
            $a = -M_PI / 2 + $i * M_PI / 5;
            $rr = $i % 2 ? $r * 0.42 : $r;
            $pts[] = [$cx + cos($a) * $rr, $cy + sin($a) * $rr];
        }
        $this->polygon($pts, $fill);
    }

    /** Everything drawn until endClip() is clipped to this rectangle. */
    public function clip(float $x, float $y, float $w, float $h): void
    {
        $this->ops .= sprintf("q %s %s %s %s re W n\n", $this->x($x), $this->y($y + $h), $this->n($w * self::PT), $this->n($h * self::PT));
    }

    public function endClip(): void
    {
        $this->ops .= "Q\n";
    }

    public function dashedRect(float $x, float $y, float $w, float $h, string $color, float $width = 0.35, array $dash = [1.4, 1.2]): void
    {
        $this->line($x, $y, $x + $w, $y, $color, $width, $dash);
        $this->line($x + $w, $y, $x + $w, $y + $h, $color, $width, $dash);
        $this->line($x + $w, $y + $h, $x, $y + $h, $color, $width, $dash);
        $this->line($x, $y + $h, $x, $y, $color, $width, $dash);
    }

    /** Text rotated 90° counter-clockwise (reads bottom → top), starting at (x, y). */
    public function textUp(float $x, float $y, string $s, float $size, string $color = '#000000', bool $bold = false, float $spacing = 0): void
    {
        $t = strtr(self::enc($s), ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
        $this->ops .= 'BT ' . $this->rgb($color) . ' rg /' . ($bold ? 'F2' : 'F1') . ' ' . $this->n($size) . ' Tf '
            . $this->n($spacing) . ' Tc 0 1 -1 0 ' . $this->x($x) . ' ' . $this->y($y) . " Tm ($t) Tj ET\n";
    }

    /**
     * Code 128 (set B) barcode drawn vertically: bars run left→right, the code reads top→bottom.
     * $x,$y = top-left, $w = bar length (mm), $h = total height (mm).
     */
    public function barcode128Vertical(float $x, float $y, float $w, float $h, string $data, string $color = '#000000'): void
    {
        $modules = self::code128Modules($data);
        $unit = $h / array_sum($modules);
        $pos = $y;
        foreach ($modules as $i => $m) {
            if ($i % 2 === 0) { // even index = bar, odd = space
                $this->rect($x, $pos, $w, $m * $unit, $color);
            }
            $pos += $m * $unit;
        }
    }

    /** Code 128 (set B) barcode drawn horizontally (bars vertical). */
    public function barcode128(float $x, float $y, float $w, float $h, string $data, string $color = '#000000'): void
    {
        $modules = self::code128Modules($data);
        $unit = $w / array_sum($modules);
        $pos = $x;
        foreach ($modules as $i => $m) {
            if ($i % 2 === 0) {
                $this->rect($pos, $y, $m * $unit, $h, $color);
            }
            $pos += $m * $unit;
        }
    }

    /** Module widths (bar, space, bar, …) for Code 128-B incl. quiet-zone-free start/checksum/stop. */
    public static function code128Modules(string $data): array
    {
        static $P = ['BaBbBb','BbBaBb','BbBbBa','AbAbBc','AbAcBb','AcAbBb','AbBbAc','AbBcAb','AcBbAb','BbAbAc','BbAcAb','BcAbAb','AaBbCb','AbBaCb','AbBbCa','AaCbBb','AbCaBb','AbCbBa','BbCbAa','BbAaCb','BbAbCa','BaCbAb','BbCaAb','CaBaCa','CaAbBb','CbAaBb','CbAbBa','CaBbAb','CbBaAb','CbBbAa','BaBaBc','BaBcBa','BcBaBa','AaAcBc','AcAaBc','AcAcBa','AaBcAc','AcBaAc','AcBcAa','BaAcAc','BcAaAc','BcAcAa','AaBaCc','AaBcCa','AcBaCa','AaCaBc','AaCcBa','AcCaBa','CaCaBa','BaAcCa','BcAaCa','BaCaAc','BaCcAa','BaCaCa','CaAaBc','CaAcBa','CcAaBa','CaBaAc','CaBcAa','CcBaAa','CaDaAa','BbAdAa','DcAaAa','AaAbBd','AaAdBb','AbAaBd','AbAdBa','AdAaBb','AdAbBa','AaBbAd','AaBdAb','AbBaAd','AbBdAa','AdBaAb','AdBbAa','BdAbAa','BbAaAd','DaCaAa','BdAaAb','AcDaAa','AaAbDb','AbAaDb','AbAbDa','AaDbAb','AbDaAb','AbDbAa','DaAbAb','DbAaAb','DbAbAa','BaBaDa','BaDaBa','DaBaBa','AaAaDc','AaAcDa','AcAaDa','AaDaAc','AaDcAa','DaAaAc','DaAcAa','AaCaDa','AaDaCa','CaAaDa','DaAaCa','BaAdAb','BaAbAd','BaAbCb','BcCaAaB'];
        $vals = [104]; // Start B
        foreach (str_split(preg_replace('/[^\x20-\x7E]/', '?', $data)) as $ch) {
            $vals[] = ord($ch) - 32;
        }
        $sum = 104;
        foreach (array_slice($vals, 1) as $i => $v) {
            $sum += ($i + 1) * $v;
        }
        $vals[] = $sum % 103;
        $vals[] = 106; // Stop
        $mods = [];
        foreach ($vals as $v) {
            foreach (str_split($P[$v]) as $c) {
                $mods[] = ord(strtolower($c)) - 96; // a/A=1 … d/D=4
            }
        }
        return $mods;
    }

    public static function enc(string $s): string
    {
        $s = str_replace('₹', 'Rs.', $s);
        $out = function_exists('iconv') ? @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s) : false;
        return $out === false ? preg_replace('/[^\x20-\x7E]/', '?', $s) : $out;
    }

    /** Width of a string in mm. */
    public function textWidth(string $s, float $size, bool $bold = false, float $spacing = 0): float
    {
        $w = 0;
        $t = self::enc($s);
        $table = $bold ? self::W_BOLD : self::W_REG;
        for ($i = 0, $n = strlen($t); $i < $n; $i++) {
            $c = ord($t[$i]);
            $w += ($c >= 32 && $c <= 126) ? $table[$c - 32] : 556;
        }
        return ($w * $size / 1000 + $spacing * max(0, strlen($t) - 1)) / self::PT;
    }

    /** Draw text; $y is the baseline. $align: L | C | R. $spacing is extra letter spacing in pt. */
    public function text(float $x, float $y, string $s, float $size, string $color = '#ffffff', bool $bold = false, string $align = 'L', float $spacing = 0, bool $oblique = false): float
    {
        $w = $this->textWidth($s, $size, $bold, $spacing);
        if ($align === 'C') { $x -= $w / 2; }
        if ($align === 'R') { $x -= $w; }
        $t = strtr(self::enc($s), ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
        $this->ops .= 'BT ' . $this->rgb($color) . ' rg 0 Tr /' . ($oblique ? 'F3' : ($bold ? 'F2' : 'F1')) . ' ' . $this->n($size) . ' Tf '
            . $this->n($spacing) . ' Tc ' . $this->x($x) . ' ' . $this->y($y) . " Td ($t) Tj ET\n";
        return $w;
    }

    /** Shrink the font until the text fits $maxW mm. */
    public function fitText(float $x, float $y, string $s, float $size, float $maxW, string $color = '#ffffff', bool $bold = false, string $align = 'L', float $spacing = 0): void
    {
        while ($size > 6 && $this->textWidth($s, $size, $bold, $spacing) > $maxW) {
            $size -= 0.5;
        }
        $this->text($x, $y, $s, $size, $color, $bold, $align, $spacing);
    }

    public function output(string $title = 'Ticket'): string
    {
        $content = $this->ops;
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objs[3] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] /Resources << /Font << /F1 5 0 R /F2 6 0 R /F3 8 0 R >> >> /Contents 4 0 R >>',
            $this->n($this->w * self::PT), $this->n($this->h * self::PT));
        $objs[4] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        $objs[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[6] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objs[8] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-BoldOblique /Encoding /WinAnsiEncoding >>';
        $objs[7] = '<< /Title (' . strtr(self::enc($title), ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']) . ') /Producer (Master Mind) >>';

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        ksort($objs);
        foreach ($objs as $i => $o) {
            $offsets[$i] = strlen($pdf);
            $pdf .= "$i 0 obj\n$o\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $pdf .= sprintf("%010d 00000 n \n", $off);
        }
        $pdf .= 'trailer << /Size ' . (count($objs) + 1) . " /Root 1 0 R /Info 7 0 R >>\nstartxref\n$xref\n%%EOF";
        return $pdf;
    }
}
