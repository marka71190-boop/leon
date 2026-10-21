<?php
declare(strict_types=1);

// Простая запись и чтение файлов Excel (.xlsx) без дополнительных модулей PHP.
// .xlsx — это zip-архив с XML-файлами; zip собираем и разбираем сами (сжатие deflate из zlib).

/** Собрать zip-архив из [путь => содержимое] */
function lp_zip_build(array $files): string
{
    $data = '';
    $central = '';
    $offset = 0;
    foreach ($files as $name => $content) {
        $deflated = gzdeflate($content, 6);
        $crc = crc32($content);
        $dosTime = 0x0000; $dosDate = 0x5921; // фиксированная дата, Excel её не проверяет
        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 8, $dosTime, $dosDate, $crc, strlen($deflated), strlen($content), strlen($name), 0) . $name;
        $data .= $local . $deflated;
        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 8, $dosTime, $dosDate, $crc, strlen($deflated), strlen($content), strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
        $offset += strlen($local) + strlen($deflated);
    }
    return $data . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($central), $offset, 0);
}

/** Разобрать zip-архив: [путь => содержимое]. null — не zip или повреждён */
function lp_zip_read(string $zip): ?array
{
    $eocd = strrpos($zip, pack('V', 0x06054b50));
    if ($eocd === false || strlen($zip) < $eocd + 22) {
        return null;
    }
    $e = unpack('Vsig/vdisk/vcdisk/vcount/vtotal/Vsize/Voffset/vclen', substr($zip, $eocd, 22));
    $pos = $e['offset'];
    $files = [];
    for ($i = 0; $i < $e['total']; $i++) {
        if (substr($zip, $pos, 4) !== pack('V', 0x02014b50)) {
            return null;
        }
        $c = unpack('Vsig/vver/vneed/vflag/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/velen/vclen/vdisk/vint/Vext/Voffset', substr($zip, $pos, 46));
        $name = substr($zip, $pos + 46, $c['nlen']);
        $pos += 46 + $c['nlen'] + $c['elen'] + $c['clen'];
        $l = unpack('Vsig/vneed/vflag/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/velen', substr($zip, $c['offset'], 30));
        $raw = substr($zip, $c['offset'] + 30 + $l['nlen'] + $l['elen'], $c['csize']);
        if ($c['method'] === 0) {
            $files[$name] = $raw;
        } elseif ($c['method'] === 8) {
            $out = @gzinflate($raw);
            if ($out === false) {
                return null;
            }
            $files[$name] = $out;
        }
    }
    return $files;
}

/** Номер столбца → буквы: 0 → A, 26 → AA */
function xlsx_col(int $i): string
{
    $s = '';
    for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
        $s = chr(65 + ($i - 1) % 26) . $s;
    }
    return $s;
}

/**
 * Файл Excel из таблицы. $rows — массив строк (первая — заголовки), $widths — ширина столбцов.
 * Числа записываются числами, остальное — текстом.
 */
function xlsx_build(array $rows, array $widths = [], string $sheet = 'Лист1'): string
{
    $x = static fn($s) => htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
    if ($widths) {
        $sheetXml .= '<cols>';
        foreach ($widths as $i => $w) {
            $sheetXml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float)$w . '" customWidth="1"/>';
        }
        $sheetXml .= '</cols>';
    }
    $sheetXml .= '<sheetData>';
    foreach (array_values($rows) as $r => $row) {
        $sheetXml .= '<row r="' . ($r + 1) . '">';
        foreach (array_values($row) as $c => $v) {
            $ref = xlsx_col($c) . ($r + 1);
            $style = $r === 0 ? ' s="1"' : '';
            if ($v === null || $v === '') {
                continue;
            }
            if ((is_int($v) || is_float($v)) && $r > 0) {
                $sheetXml .= '<c r="' . $ref . '"' . $style . '><v>' . $v . '</v></c>';
            } else {
                $sheetXml .= '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . $x($v) . '</t></is></c>';
            }
        }
        $sheetXml .= '</row>';
    }
    $sheetXml .= '</sheetData><autoFilter ref="A1:' . xlsx_col(max(0, count($rows[0] ?? []) - 1)) . max(1, count($rows)) . '"/></worksheet>';

    return lp_zip_build([
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $x($sheet) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'' . $x($sheet) . '\'!$A$1:$' . xlsx_col(max(0, count($rows[0] ?? []) - 1)) . '$' . max(1, count($rows)) . '</definedName></definedNames></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFE4CC"/></patternFill></fill></fills>'
            . '<borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="2"><xf xfId="0"/><xf xfId="0" fontId="1" fillId="2" applyFont="1" applyFill="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
        'xl/worksheets/sheet1.xml' => $sheetXml,
    ]);
}

/** Прочитать первый лист файла Excel в массив строк (каждая строка — массив значений по столбцам). null — не удалось */
function xlsx_read(string $bin): ?array
{
    $files = lp_zip_read($bin);
    if (!$files || !isset($files['xl/workbook.xml'])) {
        return null;
    }
    $strip = static fn($xml) => preg_replace('~(</?)[a-z0-9]+:~i', '$1', $xml); // убрать префиксы пространств имён
    // Путь к первому листу
    $sheetPath = 'xl/worksheets/sheet1.xml';
    $wb = @simplexml_load_string($strip($files['xl/workbook.xml']));
    $rels = isset($files['xl/_rels/workbook.xml.rels']) ? @simplexml_load_string($strip($files['xl/_rels/workbook.xml.rels'])) : null;
    if ($wb && $rels && isset($wb->sheets->sheet[0])) {
        $rid = '';
        foreach ($wb->sheets->sheet[0]->attributes() as $k => $v) {
            if ($k === 'id') $rid = (string)$v;
        }
        foreach ($rels->Relationship as $r) {
            if ((string)$r['Id'] === $rid) {
                $t = ltrim((string)$r['Target'], '/');
                $sheetPath = str_starts_with($t, 'xl/') ? $t : 'xl/' . $t;
            }
        }
    }
    if (!isset($files[$sheetPath])) {
        return null;
    }
    $shared = [];
    if (isset($files['xl/sharedStrings.xml'])) {
        $ss = @simplexml_load_string($strip($files['xl/sharedStrings.xml']));
        foreach ($ss ? $ss->si : [] as $si) {
            if (isset($si->t)) {
                $shared[] = (string)$si->t;
            } else {
                $txt = '';
                foreach ($si->r as $run) $txt .= (string)$run->t;
                $shared[] = $txt;
            }
        }
    }
    $sheet = @simplexml_load_string($strip($files[$sheetPath]));
    if (!$sheet) {
        return null;
    }
    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $r = [];
        foreach ($row->c as $c) {
            $ref = (string)$c['r'];
            preg_match('~^([A-Z]+)~', $ref, $m);
            $col = 0;
            foreach (str_split($m[1] ?? 'A') as $ch) $col = $col * 26 + (ord($ch) - 64);
            $col--;
            $t = (string)$c['t'];
            if ($t === 's') {
                $v = $shared[(int)$c->v] ?? '';
            } elseif ($t === 'inlineStr') {
                $v = isset($c->is->t) ? (string)$c->is->t : implode('', array_map(fn($x) => (string)$x->t, iterator_to_array($c->is->r ?? [], false)));
            } elseif ($t === 'b') {
                $v = (string)$c->v === '1' ? 'да' : 'нет';
            } else {
                $v = (string)$c->v;
            }
            $r[$col] = $v;
        }
        if ($r) {
            $max = max(array_keys($r));
            $full = [];
            for ($i = 0; $i <= $max; $i++) $full[] = $r[$i] ?? '';
            $rows[(int)$row['r'] ?: count($rows) + 1] = $full;
        }
    }
    return $rows;
}

/** Прочитать CSV (UTF-8 или Windows-1251, разделитель ; или ,) */
function csv_read(string $text): array
{
    if (str_starts_with($text, "\xEF\xBB\xBF")) {
        $text = substr($text, 3);
    } elseif (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1251');
    }
    $first = strtok($text, "\n");
    $delim = substr_count((string)$first, ';') >= substr_count((string)$first, ',') ? ';' : ',';
    $rows = [];
    $h = fopen('php://memory', 'r+');
    fwrite($h, $text);
    rewind($h);
    $n = 0;
    while (($row = fgetcsv($h, 0, $delim, '"', '')) !== false) {
        $n++;
        if ($row !== [null]) $rows[$n] = $row;
    }
    fclose($h);
    return $rows;
}
