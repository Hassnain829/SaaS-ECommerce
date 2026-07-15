<?php

namespace Tests\Support;

final class FedExUs09TempAssetFactory
{
    public static function png(int $width, int $height): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'us09-png-'.bin2hex(random_bytes(4)).'.png';
        file_put_contents($path, self::buildPng(max(1, $width), max(1, $height)));

        return $path;
    }

    public static function pdf(int $minBytes = 1200): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'us09-pdf-'.bin2hex(random_bytes(4)).'.pdf';
        $padding = str_repeat('Commercial invoice content for FedEx US09 validation. ', max(1, (int) ceil($minBytes / 50)));
        file_put_contents($path, "%PDF-1.4\n1 0 obj<<>>endobj\nstream\n{$padding}\nendstream\n%%EOF\n");

        return $path;
    }

    private static function buildPng(int $width, int $height): string
    {
        $raw = '';
        for ($y = 0; $y < $height; $y++) {
            $raw .= "\x00";
            $raw .= str_repeat("\x20\x40\x80", $width);
        }

        $compressed = zlib_encode($raw, ZLIB_ENCODING_DEFLATE);
        $png = "\x89PNG\r\n\x1a\n";
        $png .= self::chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0));
        $png .= self::chunk('IDAT', $compressed);
        $png .= self::chunk('IEND', '');

        return $png;
    }

    private static function chunk(string $type, string $data): string
    {
        $crc = hash('crc32b', $type.$data, true);

        return pack('N', strlen($data)).$type.$data.$crc;
    }
}
