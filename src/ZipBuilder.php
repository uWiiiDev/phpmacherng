<?php
/**
 * PHPMacherNG - Pure-PHP / ZipArchive ZIP Builder (ZipBuilder.php)
 * Erzeugt Standard-ZIP-Archive (PKZIP) aus Verzeichnissen oder Dateien.
 * Funktioniert SOWOHL mit der PHP-Erweiterung `ZipArchive` ALS AUCH komplett ohne `ext-zip`
 * über einen reinen PHP-Fallback (`gzcompress` / `crc32` / `pack`), kompatibel mit PHP 5.4 bis PHP 8.4+.
 */

class ZipBuilder
{
    /**
     * Verpackt ein komplettes Verzeichnis rekursiv in ein ZIP-Archiv (als Binär-String zum direkten Download).
     *
     * @param string $sourceDir Absoluter Pfad zum Quellverzeichnis
     * @param string $rootPrefix Optionaler Unterordner-Präfix im ZIP-Archiv (z.B. "crm_shop_demo/")
     * @return string|false ZIP-Binärdaten oder false bei Fehler
     */
    public static function createZipFromDirectory($sourceDir, $rootPrefix = '')
    {
        $sourceDir = rtrim(str_replace('\\', '/', $sourceDir), '/');
        if (!is_dir($sourceDir)) {
            return false;
        }

        $rootPrefix = trim(str_replace('\\', '/', $rootPrefix), '/');
        if ($rootPrefix !== '') {
            $rootPrefix .= '/';
        }

        $fileMap = array();
        self::collectDirectoryFiles($sourceDir, $sourceDir, $rootPrefix, $fileMap);

        // 1. Falls ZipArchive (ext-zip) verfügbar ist, nutzen wir diese
        if (class_exists('ZipArchive')) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'pmzip_');
            if ($tmpFile !== false) {
                $zip = new ZipArchive();
                if ($zip->open($tmpFile, ZipArchive::OVERWRITE) === true) {
                    foreach ($fileMap as $relPath => $absPath) {
                        $zip->addFile($absPath, $relPath);
                    }
                    $zip->close();
                    $data = @file_get_contents($tmpFile);
                    @unlink($tmpFile);
                    if ($data !== false && strlen($data) > 0) {
                        return $data;
                    }
                }
                @unlink($tmpFile);
            }
        }

        // 2. Reiner PHP-Fallback (benötigt kein ext-zip!)
        return self::buildPurePhpZip($fileMap);
    }

    /**
     * Sammelt rekursiv alle Dateien eines Ordners.
     */
    protected static function collectDirectoryFiles($baseDir, $currentDir, $rootPrefix, &$fileMap)
    {
        $items = @scandir($currentDir);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $fullPath = $currentDir . '/' . $item;
            if (is_dir($fullPath)) {
                self::collectDirectoryFiles($baseDir, $fullPath, $rootPrefix, $fileMap);
            } elseif (is_file($fullPath)) {
                $rel = ltrim(substr($fullPath, strlen($baseDir)), '/');
                $fileMap[$rootPrefix . $rel] = $fullPath;
            }
        }
    }

    /**
     * Erzeugt einen standardkonformen PKZIP-Binärstream im Arbeitsspeicher (mit Deflate-Kompression falls zlib aktiv ist).
     *
     * @param array $fileMap Assoziatives Array: 'pfad/im/zip.php' => '/absoluter/pfad/zur/datei.php'
     * @return string
     */
    public static function buildPurePhpZip($fileMap)
    {
        $dataSection = '';
        $centralDir = '';
        $offset = 0;
        $entriesCount = 0;
        $hasZlib = function_exists('gzcompress');

        foreach ($fileMap as $zipPath => $diskPath) {
            $content = @file_get_contents($diskPath);
            if ($content === false) {
                continue;
            }

            $zipPath = str_replace('\\', '/', $zipPath);
            $mtime = @filemtime($diskPath);
            if (!$mtime) {
                $mtime = time();
            }
            list($dosTime, $dosDate) = self::unixToDosTime($mtime);

            $uncLen = strlen($content);
            // CRC32 als vorzeichenloser 32-Bit-Integer
            $crc = crc32($content);
            if ($crc < 0) {
                $crc += 4294967296;
            }

            if ($hasZlib && $uncLen > 0) {
                $gz = gzcompress($content, 6);
                // Zlib-Header (2 Bytes) und Adler32-Prüfsumme am Ende (4 Bytes) abschneiden -> reiner DEFLATE-Stream
                $compData = substr($gz, 2, -4);
                $compMethod = 8; // Deflate
                $compLen = strlen($compData);
            } else {
                $compData = $content;
                $compMethod = 0; // Stored (unkomprimiert)
                $compLen = $uncLen;
            }

            $nameLen = strlen($zipPath);

            // Local File Header (PK\x03\x04)
            $localHeader = "\x50\x4b\x03\x04"
                . pack('v', 20)          // Version needed to extract (2.0)
                . pack('v', 0)           // General purpose bit flag
                . pack('v', $compMethod) // Compression method (8=Deflate, 0=Store)
                . pack('v', $dosTime)    // Last mod file time
                . pack('v', $dosDate)    // Last mod file date
                . pack('V', $crc)        // CRC-32
                . pack('V', $compLen)    // Compressed size
                . pack('V', $uncLen)     // Uncompressed size
                . pack('v', $nameLen)    // File name length
                . pack('v', 0)           // Extra field length
                . $zipPath;

            $dataSection .= $localHeader . $compData;

            // Central Directory File Header (PK\x01\x02)
            $centralDir .= "\x50\x4b\x01\x02"
                . pack('v', 20)          // Version made by
                . pack('v', 20)          // Version needed to extract
                . pack('v', 0)           // General purpose bit flag
                . pack('v', $compMethod) // Compression method
                . pack('v', $dosTime)    // Last mod file time
                . pack('v', $dosDate)    // Last mod file date
                . pack('V', $crc)        // CRC-32
                . pack('V', $compLen)    // Compressed size
                . pack('V', $uncLen)     // Uncompressed size
                . pack('v', $nameLen)    // File name length
                . pack('v', 0)           // Extra field length
                . pack('v', 0)           // File comment length
                . pack('v', 0)           // Disk number start
                . pack('v', 0)           // Internal file attributes
                . pack('V', 32)          // External file attributes (Archive bit)
                . pack('V', $offset)     // Relative offset of local header
                . $zipPath;

            $offset += strlen($localHeader) + $compLen;
            $entriesCount++;
        }

        // End of Central Directory Record (PK\x05\x06)
        $eocd = "\x50\x4b\x05\x06"
            . pack('v', 0)                     // Number of this disk
            . pack('v', 0)                     // Disk where central directory starts
            . pack('v', $entriesCount)         // Total number of entries on this disk
            . pack('v', $entriesCount)         // Total number of entries in central dir
            . pack('V', strlen($centralDir))   // Size of central directory
            . pack('V', strlen($dataSection))  // Offset of start of central directory
            . pack('v', 0);                    // ZIP file comment length

        return $dataSection . $centralDir . $eocd;
    }

    /**
     * Wandelt einen Unix-Timestamp in MS-DOS Datum & Zeit für ZIP-Header um.
     */
    protected static function unixToDosTime($unixTime)
    {
        $d = getdate($unixTime);
        if ($d['year'] < 1980) {
            $d['year'] = 1980;
            $d['mon'] = 1;
            $d['mday'] = 1;
            $d['hours'] = 0;
            $d['minutes'] = 0;
            $d['seconds'] = 0;
        }
        $dosTime = (($d['hours'] << 11) | ($d['minutes'] << 5) | ($d['seconds'] >> 1)) & 0xFFFF;
        $dosDate = (((($d['year'] - 1980) << 9) | ($d['mon'] << 5) | $d['mday'])) & 0xFFFF;
        return array($dosTime, $dosDate);
    }
}
