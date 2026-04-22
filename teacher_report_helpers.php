<?php

function teacher_reports_ensure_table(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS teacher_reports (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            teacher_id INT UNSIGNED NOT NULL,
            classroom_id INT UNSIGNED NULL,
            title VARCHAR(160) NOT NULL,
            report_type ENUM('generated_pdf', 'imported_pdf') NOT NULL,
            summary TEXT NULL,
            file_path VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_teacher_reports_teacher (teacher_id),
            KEY idx_teacher_reports_classroom (classroom_id),
            CONSTRAINT fk_teacher_reports_teacher
                FOREIGN KEY (teacher_id) REFERENCES users(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_teacher_reports_classroom
                FOREIGN KEY (classroom_id) REFERENCES classrooms(id)
                ON DELETE SET NULL
        )
    ");
}

function teacher_reports_storage_dir(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'teacher_reports';
}

function teacher_reports_ensure_storage_dir(): string
{
    $dir = teacher_reports_storage_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function teacher_reports_slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function teacher_reports_escape_pdf_text(string $value): string
{
    $value = str_replace('\\', '\\\\', $value);
    $value = str_replace('(', '\\(', $value);
    $value = str_replace(')', '\\)', $value);
    return preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';
}

function teacher_reports_wrap_lines(array $lines, int $max_chars = 92): array
{
    $wrapped = [];

    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '') {
            $wrapped[] = '';
            continue;
        }

        $parts = preg_split('/\r\n|\r|\n/', $line) ?: [$line];
        foreach ($parts as $part) {
            $part = trim((string)$part);
            if ($part === '') {
                $wrapped[] = '';
                continue;
            }

            $chunked = wordwrap($part, $max_chars, "\n", true);
            foreach (explode("\n", $chunked) as $chunk) {
                $wrapped[] = $chunk;
            }
        }
    }

    return $wrapped;
}

function teacher_reports_build_pdf(string $title, array $lines): string
{
    $title = $title !== '' ? $title : 'Teacher Report';
    $all_lines = array_merge([$title, str_repeat('=', min(40, strlen($title)))], teacher_reports_wrap_lines($lines));
    $lines_per_page = 46;
    $pages = array_chunk($all_lines, $lines_per_page);

    if (!$pages) {
        $pages = [['Teacher report']];
    }

    $objects = [];
    $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';

    $page_object_ids = [];
    $content_object_ids = [];
    $next_object_id = 4;

    foreach ($pages as $_page) {
        $page_object_ids[] = $next_object_id++;
        $content_object_ids[] = $next_object_id++;
    }

    $kids = [];
    foreach ($page_object_ids as $page_object_id) {
        $kids[] = $page_object_id . ' 0 R';
    }
    $objects[] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($page_object_ids) . ' >>';
    $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';

    foreach ($pages as $index => $page_lines) {
        $page_object_id = $page_object_ids[$index];
        $content_object_id = $content_object_ids[$index];

        $stream_lines = ['BT', '/F1 10 Tf', '40 800 Td', '12 TL'];
        foreach ($page_lines as $line_index => $line) {
            $escaped = teacher_reports_escape_pdf_text($line);
            if ($line_index === 0) {
                $stream_lines[] = '(' . $escaped . ') Tj';
            } else {
                $stream_lines[] = 'T* (' . $escaped . ') Tj';
            }
        }
        $stream_lines[] = 'ET';
        $stream = implode("\n", $stream_lines);

        $objects[$page_object_id - 1] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $content_object_id . ' 0 R >>';
        $objects[$content_object_id - 1] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
    }

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $index => $object_body) {
        $offsets[] = strlen($pdf);
        $object_number = $index + 1;
        $pdf .= $object_number . " 0 obj\n" . $object_body . "\nendobj\n";
    }

    $xref_offset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }

    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xref_offset . "\n%%EOF";

    return $pdf;
}
