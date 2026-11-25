<?php
/**
 * Tiny .xlsx writer — no Composer / PhpSpreadsheet needed.
 * Builds the .zip container itself in plain PHP, so no PHP extensions are needed (zlib is used for compression when present).
 *
 *   $book = new XlsxBook();
 *   $sh   = $book->addSheet('My sheet');
 *   $sh->cell(1, 1, 'Hello', ['b' => 1, 'ha' => 'center', 'bd' => 'LRTB'], 1, 3);   // row, col, value, style, rowspan, colspan
 *   $book->stream('file.xlsx');
 *
 * Rows and columns are 1-based. Style keys: b, i, sz, col (hex), fill (hex), ha, va, wrap, rot, bd ('LRTB' sides).
 * A value may be a string, a number, or a list of [text, bold] runs (rich text).
 */

function xl_col(int $c): string {
    $s = '';
    while ($c > 0) { $m = ($c - 1) % 26; $s = chr(65 + $m) . $s; $c = intdiv($c - 1, 26); }
    return $s;
}
function xl_esc($t): string {
    $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string)$t);
    return htmlspecialchars($t, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

class XlsxBook {
    public $sheets = [];
    private $fonts = [];
    private $fills = ['none', 'gray125'];
    private $borders = [''];
    private $xfs = [];
    private $xfmap = [];

    public function __construct() { $this->style([]); }          // xf 0 = default style

    public function addSheet(string $name): XlsxSheet {
        $base = trim(preg_replace('/[\[\]:*?\/\\\\]/', '-', $name));
        $base = mb_substr($base, 0, 31, 'UTF-8');
        if ($base === '') $base = 'Sheet';
        $n = $base; $i = 2;
        while ($this->hasSheet($n)) {
            $suf = ' (' . $i . ')';
            $n = mb_substr($base, 0, 31 - strlen($suf), 'UTF-8') . $suf;
            $i++;
        }
        $sh = new XlsxSheet($this, $n);
        $this->sheets[] = $sh;
        return $sh;
    }
    private function hasSheet(string $n): bool {
        foreach ($this->sheets as $s) if (mb_strtolower($s->name, 'UTF-8') === mb_strtolower($n, 'UTF-8')) return true;
        return false;
    }
    private function idx(array &$list, string $v): int {
        $i = array_search($v, $list, true);
        if ($i === false) { $list[] = $v; $i = count($list) - 1; }
        return (int)$i;
    }

    /** Register a style and return its index (cellXfs). */
    public function style(array $st): int {
        ksort($st);
        $key = json_encode($st);
        if (isset($this->xfmap[$key])) return $this->xfmap[$key];

        $font = '<font>' . (!empty($st['b']) ? '<b/>' : '') . (!empty($st['i']) ? '<i/>' : '')
              . '<sz val="' . (float)($st['sz'] ?? 10) . '"/><color rgb="FF' . ($st['col'] ?? '000000') . '"/><name val="Arial"/></font>';
        $fid = $this->idx($this->fonts, $font);

        $flid = 0;
        if (!empty($st['fill'])) $flid = $this->idx($this->fills, (string)$st['fill']);

        $bid = 0;
        $bd = (string)($st['bd'] ?? '');
        if ($bd !== '') {
            $side = function (string $tag, string $k) use ($bd) {
                return strpos($bd, $k) !== false ? '<' . $tag . ' style="thin"><color auto="1"/></' . $tag . '>' : '<' . $tag . '/>';
            };
            $b = '<border>' . $side('left', 'L') . $side('right', 'R') . $side('top', 'T') . $side('bottom', 'B') . '<diagonal/></border>';
            $bid = $this->idx($this->borders, $b);
        }

        $al = '<alignment horizontal="' . ($st['ha'] ?? 'general') . '" vertical="' . ($st['va'] ?? 'center') . '"'
            . (!empty($st['wrap']) ? ' wrapText="1"' : '') . (!empty($st['rot']) ? ' textRotation="' . (int)$st['rot'] . '"' : '') . '/>';
        $this->xfs[] = '<xf numFmtId="0" fontId="' . $fid . '" fillId="' . $flid . '" borderId="' . $bid . '" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $al . '</xf>';
        $this->xfmap[$key] = count($this->xfs) - 1;
        return $this->xfmap[$key];
    }

    private function stylesXml(): string {
        $fills = '';
        foreach ($this->fills as $f) {
            if ($f === 'none' || $f === 'gray125') $fills .= '<fill><patternFill patternType="' . $f . '"/></fill>';
            else $fills .= '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $f . '"/><bgColor indexed="64"/></patternFill></fill>';
        }
        $borders = '';
        foreach ($this->borders as $b) $borders .= $b !== '' ? $b : '<border><left/><right/><top/><bottom/><diagonal/></border>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="' . count($this->fonts) . '">' . implode('', $this->fonts) . '</fonts>'
            . '<fills count="' . count($this->fills) . '">' . $fills . '</fills>'
            . '<borders count="' . count($this->borders) . '">' . $borders . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . count($this->xfs) . '">' . implode('', $this->xfs) . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /** All parts of the .xlsx package as [path => content]. */
    private function parts(): array {
        if (!$this->sheets) { $e = $this->addSheet('Empty'); $e->cell(1, 1, 'Nothing to export yet.'); }
        $n = count($this->sheets);
        $f = [];

        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for ($i = 1; $i <= $n; $i++) $ct .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $f['[Content_Types].xml'] = $ct . '</Types>';

        $f['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';

        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($this->sheets as $i => $s) {
            $k = $i + 1;
            $wb .= '<sheet name="' . xl_esc($s->name) . '" sheetId="' . $k . '" r:id="rId' . $k . '"/>';
            $rels .= '<Relationship Id="rId' . $k . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $k . '.xml"/>';
        }
        $rels .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $f['xl/workbook.xml'] = $wb . '</sheets></workbook>';
        $f['xl/_rels/workbook.xml.rels'] = $rels;

        foreach ($this->sheets as $i => $s) $f['xl/worksheets/sheet' . ($i + 1) . '.xml'] = $s->xml();   // sheets first: they register styles
        $f['xl/styles.xml'] = $this->stylesXml();
        return $f;
    }

    /**
     * Pack [name => content] into a .zip, in plain PHP (no ZipArchive / zip extension, no temp file).
     * Uses raw deflate when zlib is available, otherwise stores the files uncompressed.
     */
    private function zip(array $files): string {
        $y = (int)date('Y'); $mo = (int)date('n'); $d = (int)date('j');
        $dosDate = max(0, ($y - 1980)) << 9 | $mo << 5 | $d;
        $dosTime = ((int)date('G')) << 11 | ((int)date('i')) << 5 | intdiv((int)date('s'), 2);
        $data = ''; $central = ''; $count = 0;
        foreach ($files as $name => $content) {
            $name = (string)$name; $content = (string)$content;
            $crc = crc32($content);
            $usize = strlen($content);
            $method = 0; $packed = $content;
            if (function_exists('gzdeflate')) {
                $z = gzdeflate($content, 6);
                if ($z !== false && strlen($z) < $usize) { $method = 8; $packed = $z; }
            }
            $csize = strlen($packed);
            $offset = strlen($data);
            $data .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, $method, $dosTime, $dosDate, $crc, $csize, $usize, strlen($name), 0) . $name . $packed;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, $method, $dosTime, $dosDate, $crc, $csize, $usize, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
            $count++;
        }
        return $data . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), strlen($data), 0);
    }

    /** The finished .xlsx file as a binary string. */
    public function build(): string { return $this->zip($this->parts()); }

    /** Write the workbook to $path. Returns true on success. */
    public function save(string $path): bool { return file_put_contents($path, $this->build()) !== false; }

    /** Send the workbook to the browser as a download. */
    public function stream(string $filename): void {
        $bytes = $this->build();
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: no-store');
        echo $bytes;
        exit;
    }
}

class XlsxSheet {
    public $name;
    public $landscape = false;
    public $fitHeight = 1;          // 1 = squeeze onto one page tall, 0 = as many pages as needed
    public $widths = [];            // col => width
    public $heights = [];           // row => points
    public $breaks = [];            // rows after which a page break is forced
    private $book;
    private $cells = [];
    private $merges = [];

    public function __construct(XlsxBook $book, string $name) { $this->book = $book; $this->name = $name; }

    public function width(int $c, float $w): void  { $this->widths[$c] = $w; }
    public function height(int $r, float $pt): void { $this->heights[$r] = $pt; }

    /** Put a value in a cell. $rs / $cs merge the cell downward / rightward; every cell of the merge gets the same style (so borders draw). */
    public function cell(int $r, int $c, $val = '', array $st = [], int $rs = 1, int $cs = 1): void {
        $this->cells[$r][$c] = [$val, $st];
        for ($rr = $r; $rr < $r + $rs; $rr++) {
            for ($cc = $c; $cc < $c + $cs; $cc++) {
                if ($rr !== $r || $cc !== $c) $this->cells[$rr][$cc] = ['', $st];
            }
        }
        if ($rs > 1 || $cs > 1) $this->merges[] = [$r, $c, $r + $rs - 1, $c + $cs - 1];
    }

    public function xml(): string {
        $o = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $o .= '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>';
        $o .= '<sheetViews><sheetView workbookViewId="0" showGridLines="0"/></sheetViews><sheetFormatPr defaultRowHeight="15"/>';
        if ($this->widths) {
            ksort($this->widths);
            $o .= '<cols>';
            foreach ($this->widths as $c => $w) $o .= '<col min="' . $c . '" max="' . $c . '" width="' . $w . '" customWidth="1"/>';
            $o .= '</cols>';
        }
        $o .= '<sheetData>';
        $rows = array_unique(array_merge(array_keys($this->cells), array_keys($this->heights)));
        sort($rows);
        foreach ($rows as $r) {
            $ht = isset($this->heights[$r]) ? ' ht="' . $this->heights[$r] . '" customHeight="1"' : '';
            $o .= '<row r="' . $r . '"' . $ht . '>';
            $line = $this->cells[$r] ?? [];
            ksort($line);
            foreach ($line as $c => $cv) {
                [$val, $st] = $cv;
                $ref = xl_col($c) . $r;
                $s = $this->book->style($st);
                if (is_array($val)) {                                        // rich text: [[text, bold], ...]
                    $runs = '';
                    foreach ($val as $run) {
                        $runs .= '<r><rPr>' . (!empty($run[1]) ? '<b/>' : '') . '<sz val="' . (float)($st['sz'] ?? 10) . '"/><color rgb="FF' . ($st['col'] ?? '000000') . '"/><rFont val="Arial"/></rPr>'
                              . '<t xml:space="preserve">' . xl_esc($run[0]) . '</t></r>';
                    }
                    $o .= '<c r="' . $ref . '" s="' . $s . '" t="inlineStr"><is>' . $runs . '</is></c>';
                } elseif (is_int($val) || is_float($val)) {
                    $o .= '<c r="' . $ref . '" s="' . $s . '"><v>' . rtrim(rtrim(sprintf('%.4F', $val), '0'), '.') . '</v></c>';
                } elseif ($val === '' || $val === null) {
                    $o .= '<c r="' . $ref . '" s="' . $s . '"/>';
                } else {
                    $o .= '<c r="' . $ref . '" s="' . $s . '" t="inlineStr"><is><t xml:space="preserve">' . xl_esc($val) . '</t></is></c>';
                }
            }
            $o .= '</row>';
        }
        $o .= '</sheetData>';
        if ($this->merges) {
            $o .= '<mergeCells count="' . count($this->merges) . '">';
            foreach ($this->merges as $m) $o .= '<mergeCell ref="' . xl_col($m[1]) . $m[0] . ':' . xl_col($m[3]) . $m[2] . '"/>';
            $o .= '</mergeCells>';
        }
        $o .= '<printOptions horizontalCentered="1"/><pageMargins left="0.4" right="0.4" top="0.4" bottom="0.4" header="0.2" footer="0.2"/>';
        // paperSize 14 = Folio 8.5 x 13 in = "long bond"
        $o .= '<pageSetup paperSize="14" orientation="' . ($this->landscape ? 'landscape' : 'portrait') . '" fitToWidth="1" fitToHeight="' . (int)$this->fitHeight . '"/>';
        if ($this->breaks) {
            $o .= '<rowBreaks count="' . count($this->breaks) . '" manualBreakCount="' . count($this->breaks) . '">';
            foreach ($this->breaks as $b) $o .= '<brk id="' . (int)$b . '" max="16383" man="1"/>';
            $o .= '</rowBreaks>';
        }
        return $o . '</worksheet>';
    }
}
