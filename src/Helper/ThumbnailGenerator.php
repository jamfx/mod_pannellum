<?php
/**
 * @package     mod_pannellum
 * @version     1.1.0
 * @author      Niko Winckel
 * @copyright   (C) 2025 Niko Winckel. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://nik-o-mat.de
 *
 * Pannellum 360° Viewer – Thumbnail-Generator (Equirectangular → Perspektive)
 */

namespace Nikomat\Module\Pannellum\Site\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;

/**
 * Erzeugt aus einem Equirectangular-Panorama ein perspektivisch korrektes Vorschaubild.
 *
 * Ablauf:
 *  1. Große Quellbilder werden auf maximal 4096×2048 Pixel verkleinert
 *     (Imagick-Extension → Kommandozeile → GD mit Speicherprüfung).
 *     Die verkleinerte Zwischenversion ist eine temporäre Datei und wird danach gelöscht.
 *  2. Mit GD wird die perspektivische Projektion mit bilinearer Interpolation berechnet.
 */
class ThumbnailGenerator
{
    /** Maximale Größe des Zwischenbildes. */
    public const MAX_WIDTH  = 4096;
    public const MAX_HEIGHT = 2048;

    /** Zielordner relativ zum Joomla-Root. */
    public const THUMBNAIL_DIR = 'images/mod_pannellum/thumbnails';

    /**
     * Ab dieser Pixelzahl (Breite × Höhe) wird ein Bild nicht mehr mit GD verkleinert:
     * GD dekodiert das Bild komplett und braucht dafür sehr lange und sehr viel RAM.
     */
    private const GD_MAX_PIXELS = 40000000;

    /** Zeitlimit in Sekunden für ImageMagick (Kommandozeile und Imagick-Erweiterung). */
    private const CLI_TIMEOUT = 30;

    /** Debug-Protokoll (ausführliche Schritte) – wird über das Modul-Feld "debug_log" gesteuert. */
    private static bool $debug = false;

    /** JPEG-Qualität der Thumbnails. */
    private const JPEG_QUALITY = 85;

    /** Thumbnail-Größen je Seitenverhältnis (Breite, Höhe). */
    private const SIZES = [
        '16_9' => [640, 360],
        '4_3'  => [640, 480],
        '1_1'  => [640, 640],
    ];

    /**
     * Schaltet das ausführliche Debug-Protokoll ein oder aus.
     *
     * @param   bool  $debug  true = ausführlich protokollieren
     *
     * @return  void
     */
    public static function setDebug(bool $debug): void
    {
        self::$debug = $debug;
    }

    /**
     * Ermittelt den lokalen Bildpfad (relativ zum Joomla-Root) aus einem Media-Feld-Wert.
     *
     * @param   string  $raw  Wert des Media-Felds (z. B. "images/a.jpg#joomlaImage://local-images/a.jpg?width=1")
     *
     * @return  string  Pfad wie "images/panoramas/a.jpg"
     *
     * @throws  \RuntimeException  bei leerem, externem oder unzulässigem Pfad
     */
    public static function normalizeImagePath(string $raw): string
    {
        $path = trim($raw);
        $hash = strpos($path, '#');

        if ($hash !== false) {
            $path = substr($path, 0, $hash);
        }

        $path = ltrim(rawurldecode($path), '/');

        if ($path === '' || preg_match('#^[a-z][a-z0-9+.-]*://#i', $path)) {
            throw new \RuntimeException(Text::_('MOD_PANNELLUM_ERROR_IMAGE_NOT_LOCAL'));
        }

        if (str_contains($path, '..') || str_contains($path, "\0") || !str_starts_with($path, 'images/')) {
            throw new \RuntimeException(Text::_('MOD_PANNELLUM_ERROR_IMAGE_PATH_INVALID'));
        }

        return $path;
    }

    /**
     * Liefert den (deterministischen) Thumbnail-Pfad relativ zum Joomla-Root.
     *
     * Der Dateiname enthält einen MD5-Hash aus Bildpfad, Blickrichtung, Sichtfeld,
     * Seitenverhältnis und Änderungszeit des Quellbildes. Ändert sich eines davon,
     * ergibt sich automatisch ein neuer Dateiname.
     *
     * @param   string  $imagePath     Lokaler Bildpfad (siehe normalizeImagePath)
     * @param   float   $yaw           Yaw in Grad
     * @param   float   $pitch         Pitch in Grad
     * @param   float   $hfov          Horizontales Sichtfeld in Grad
     * @param   string  $aspectRatio   16_9, 4_3 oder 1_1
     *
     * @return  string
     */
    public static function getThumbnailPath(string $imagePath, float $yaw, float $pitch, float $hfov, string $aspectRatio): string
    {
        [$yaw, $pitch, $hfov] = self::sanitizeView($yaw, $pitch, $hfov);

        $source = JPATH_ROOT . '/' . $imagePath;
        $mtime  = is_file($source) ? (int) filemtime($source) : 0;

        $hash = md5($imagePath . $yaw . $pitch . $hfov . self::sanitizeAspect($aspectRatio) . $mtime);

        return self::THUMBNAIL_DIR . '/thumb_' . $hash . '.jpg';
    }

    /**
     * Erzeugt das Thumbnail.
     *
     * @param   string  $imagePath     Pfad zum Equirectangular-Bild relativ zum Joomla-Root
     * @param   float   $yaw           Horizontale Blickrichtung (-180 bis 180)
     * @param   float   $pitch         Vertikale Blickrichtung (-90 bis 90)
     * @param   float   $hfov          Horizontales Sichtfeld (30 bis 120)
     * @param   string  $aspectRatio   16_9, 4_3 oder 1_1
     *
     * @return  string  Pfad zum Thumbnail relativ zum Joomla-Root
     *
     * @throws  \RuntimeException  mit verständlicher Meldung
     */
    public function generate(string $imagePath, float $yaw, float $pitch, float $hfov, string $aspectRatio): string
    {
        if (!\extension_loaded('gd')) {
            throw new \RuntimeException(Text::_('MOD_PANNELLUM_ERROR_GD_MISSING'));
        }

        $imagePath   = self::normalizeImagePath($imagePath);
        $aspectRatio = self::sanitizeAspect($aspectRatio);
        [$yaw, $pitch, $hfov] = self::sanitizeView($yaw, $pitch, $hfov);

        $source = JPATH_ROOT . '/' . $imagePath;

        if (!is_file($source) || !is_readable($source)) {
            throw new \RuntimeException(Text::sprintf('MOD_PANNELLUM_ERROR_IMAGE_NOT_FOUND', $imagePath));
        }

        $targetRel = self::getThumbnailPath($imagePath, $yaw, $pitch, $hfov, $aspectRatio);
        $targetDir = JPATH_ROOT . '/' . self::THUMBNAIL_DIR;

        if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new \RuntimeException(Text::sprintf('MOD_PANNELLUM_ERROR_DIR_NOT_WRITABLE', self::THUMBNAIL_DIR));
        }

        if (!is_writable($targetDir)) {
            throw new \RuntimeException(Text::sprintf('MOD_PANNELLUM_ERROR_DIR_NOT_WRITABLE', self::THUMBNAIL_DIR));
        }

        $info = @getimagesize($source);

        if ($info === false || !\in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new \RuntimeException(Text::sprintf('MOD_PANNELLUM_ERROR_IMAGE_UNSUPPORTED', $imagePath));
        }

        [$srcWidth, $srcHeight, $srcType] = $info;

        if (\function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $started = microtime(true);
        self::logEnvironment();
        self::debug(sprintf('Generate %s (%dx%d), yaw %s pitch %s hfov %s, %s', $imagePath, $srcWidth, $srcHeight, $yaw, $pitch, $hfov, $aspectRatio));

        $tempFile = null;
        $work     = $source;
        $dst      = null;
        $src      = null;

        try {
            // 1. Vorverarbeitung: große Bilder auf maximal 4096×2048 verkleinern
            if ($srcWidth > self::MAX_WIDTH || $srcHeight > self::MAX_HEIGHT) {
                $tempFile = $this->downscale($source, $srcWidth, $srcHeight, $srcType);
                $work     = $tempFile;
                $info     = @getimagesize($work);

                if ($info === false) {
                    throw new \RuntimeException(Text::sprintf('MOD_PANNELLUM_ERROR_IMAGE_UNSUPPORTED', $imagePath));
                }

                [$srcWidth, $srcHeight, $srcType] = $info;
            }

            // 2. Projektion mit GD
            $src = $this->loadWithGd($work, $srcWidth, $srcHeight, $srcType, $imagePath);

            [$width, $height] = self::SIZES[$aspectRatio];
            $dst = $this->project($src, $srcWidth, $srcHeight, $yaw, $pitch, $hfov, $width, $height);

            $target = JPATH_ROOT . '/' . $targetRel;

            if (!@imagejpeg($dst, $target, self::JPEG_QUALITY)) {
                throw new \RuntimeException(Text::sprintf('MOD_PANNELLUM_ERROR_THUMBNAIL_WRITE', $targetRel));
            }
            self::debug(sprintf('Thumbnail %s done in %.2fs', $targetRel, microtime(true) - $started));
        } catch (\Error $e) {
            // z. B. Speicherfehler bei sehr großen Bildern
            throw new \RuntimeException(Text::sprintf('MOD_PANNELLUM_ERROR_THUMBNAIL_FAILED_GENERIC', $e->getMessage()), 0, $e);
        } finally {
            if ($src instanceof \GdImage) {
                imagedestroy($src);
            }

            if ($dst instanceof \GdImage) {
                imagedestroy($dst);
            }

            if ($tempFile !== null && is_file($tempFile)) {
                @unlink($tempFile);
            }
        }

        return $targetRel;
    }

    /**
     * Verkleinert das Quellbild auf maximal 4096×2048 Pixel und speichert es als temporäre JPEG-Datei.
     * Reihenfolge: Kommandozeile (magick/convert, eigener Prozess mit Zeitlimit) → Imagick-Extension → GD mit Speicherprüfung.
     *
     * @param   string  $source  Absoluter Pfad des Quellbildes
     * @param   int     $width   Breite des Quellbildes
     * @param   int     $height  Höhe des Quellbildes
     * @param   int     $type    IMAGETYPE_*
     *
     * @return  string  Absoluter Pfad der temporären Datei
     */
    private function downscale(string $source, int $width, int $height, int $type): string
    {
        $temp  = $this->createTempFile();
        $start = microtime(true);

        try {
            // Kommandozeile zuerst: läuft in einem eigenen Prozess mit hartem Zeitlimit.
            // Hängt oder stürzt ImageMagick ab, bleibt der PHP-Request davon unberührt.
            if ($this->downscaleWithCli($source, $type, $temp)) {
                self::debug(sprintf('Downscale %dx%d via ImageMagick CLI: %.2fs', $width, $height, microtime(true) - $start));

                return $temp;
            }

            if ($this->downscaleWithImagick($source, $type, $temp)) {
                self::debug(sprintf('Downscale %dx%d via Imagick extension: %.2fs', $width, $height, microtime(true) - $start));

                return $temp;
            }

            self::log(sprintf(
                'Neither ImageMagick CLI (exec: %s) nor Imagick extension (%s) usable, falling back to GD for %dx%d',
                \function_exists('proc_open') || \function_exists('exec') ? 'enabled' : 'disabled',
                \extension_loaded('imagick') ? 'loaded' : 'missing',
                $width,
                $height
            ));

            $this->downscaleWithGd($source, $width, $height, $type, $temp);

            self::debug(sprintf('Downscale via GD: %.2fs', microtime(true) - $start));

            return $temp;
        } catch (\Throwable $e) {
            @unlink($temp);

            throw $e;
        }
    }

    /**
     * @return  string  Pfad einer neu angelegten, leeren temporären Datei
     */
    private function createTempFile(): string
    {
        $dir  = rtrim((string) \Joomla\CMS\Factory::getApplication()->get('tmp_path', sys_get_temp_dir()), '/\\');
        $dir  = is_dir($dir) && is_writable($dir) ? $dir : sys_get_temp_dir();
        $temp = tempnam($dir, 'pnl_');

        if ($temp === false) {
            throw new \RuntimeException(Text::_('MOD_PANNELLUM_ERROR_TEMP_FILE'));
        }

        return $temp;
    }

    private function downscaleWithImagick(string $source, int $type, string $temp): bool
    {
        if (!\extension_loaded('imagick') || !class_exists(\Imagick::class)) {
            return false;
        }

        self::debug('Trying Imagick extension');

        try {
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_TIME, self::CLI_TIMEOUT);
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, 512 * 1048576);
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MAP, 1024 * 1048576);

            $im = new \Imagick();

            // JPEG: bereits beim Dekodieren auf die Zielgröße verkleinern (spart Zeit und Speicher)
            if ($type === IMAGETYPE_JPEG) {
                $im->setOption('jpeg:size', self::MAX_WIDTH . 'x' . self::MAX_HEIGHT);
            }

            $im->readImage($source . '[0]');
            $im->thumbnailImage(self::MAX_WIDTH, self::MAX_HEIGHT, true);
            $im->setImageBackgroundColor('white');
            $im = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $im->setImageFormat('jpeg');
            $im->setImageCompressionQuality(90);
            $im->stripImage();
            $ok = $im->writeImage('jpeg:' . $temp);
            $im->clear();
            $im->destroy();

            return $ok && is_file($temp) && filesize($temp) > 0;
        } catch (\Throwable $e) {
            self::log('Imagick extension failed: ' . $e->getMessage());

            return false;
        }
    }

    private function downscaleWithCli(string $source, int $type, string $temp): bool
    {
        if (!\function_exists('proc_open') && !\function_exists('exec')) {
            self::log('ImageMagick CLI not usable: proc_open and exec are disabled');

            return false;
        }

        foreach (['magick', 'convert'] as $binary) {
            [$code, $output] = $this->runProcess([$binary, '-version'], 10);

            if ($code !== 0 || stripos($output, 'ImageMagick') === false) {
                self::debug(sprintf('ImageMagick CLI "%s" not available (exit %s)', $binary, $code === null ? 'none' : $code));

                continue;
            }

            $args = [$binary, '-limit', 'memory', '512MiB', '-limit', 'map', '1GiB', '-limit', 'thread', '2', '-limit', 'time', (string) self::CLI_TIMEOUT];

            if ($type === IMAGETYPE_JPEG) {
                $args[] = '-define';
                $args[] = 'jpeg:size=' . self::MAX_WIDTH . 'x' . self::MAX_HEIGHT;
            }

            array_push(
                $args,
                $source . '[0]',
                '-background', 'white', '-alpha', 'remove', '-alpha', 'off',
                '-resize', self::MAX_WIDTH . 'x' . self::MAX_HEIGHT . '>',
                '-quality', '90', '-strip',
                'jpeg:' . $temp
            );

            self::debug('Trying ImageMagick CLI: ' . $binary);

            [$code, $output] = $this->runProcess($args, self::CLI_TIMEOUT);

            if ($code === 0 && is_file($temp) && filesize($temp) > 0) {
                return true;
            }

            self::log(sprintf(
                'ImageMagick CLI "%s" failed (exit %s): %s',
                $binary,
                $code === null ? 'timeout after ' . self::CLI_TIMEOUT . 's, process killed' : $code,
                substr(trim($output), 0, 300)
            ));
        }

        return false;
    }

    /**
     * Führt ein Programm ohne Shell aus und beendet es hart nach Ablauf des Zeitlimits.
     *
     * @param   string[]  $args     Programm und Argumente
     * @param   int       $timeout  Zeitlimit in Sekunden
     *
     * @return  array  [Exit-Code (null bei Zeitüberschreitung/Startfehler), Ausgabe]
     */
    private function runProcess(array $args, int $timeout): array
    {
        if (!\function_exists('proc_open')) {
            $out  = [];
            $code = 1;
            @exec(implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1', $out, $code);

            return [$code, implode("\n", $out)];
        }

        $proc = @proc_open($args, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!\is_resource($proc)) {
            return [null, ''];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output   = '';
        $deadline = microtime(true) + $timeout;
        $code     = null;

        while (true) {
            $output .= (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
            $status  = proc_get_status($proc);

            if (!$status['running']) {
                $code = $status['exitcode'];
                break;
            }

            if (microtime(true) > $deadline) {
                proc_terminate($proc, 9);
                break;
            }

            usleep(50000);
        }

        $output .= (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        return [$code, $output];
    }

    private function downscaleWithGd(string $source, int $width, int $height, int $type, string $temp): void
    {
        // Schnell abbrechen statt den Server minutenlang zu blockieren
        if ($width * $height > self::GD_MAX_PIXELS) {
            throw new \RuntimeException(Text::sprintf(
                'MOD_PANNELLUM_ERROR_IMAGE_NEEDS_IMAGEMAGICK',
                $width,
                $height,
                (int) round($width * $height / 1000000)
            ));
        }

        // RAM-Bedarf: Quellbild + verkleinertes Bild, jeweils Breite × Höhe × 4 Byte, plus 50 % Reserve
        $scale  = min(self::MAX_WIDTH / $width, self::MAX_HEIGHT / $height, 1);
        $newW   = max(1, (int) round($width * $scale));
        $newH   = max(1, (int) round($height * $scale));
        $needed = (int) (($width * $height + $newW * $newH) * 4 * 1.5);

        $this->assertMemory($needed, $width, $height);

        $full = $this->createGd($source, $type);

        if ($full === false) {
            throw new \RuntimeException(Text::sprintf('MOD_PANNELLUM_ERROR_IMAGE_UNSUPPORTED', basename($source)));
        }

        $small = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($small, $full, 0, 0, 0, 0, $newW, $newH, $width, $height);
        imagedestroy($full);

        $ok = imagejpeg($small, $temp, 90);
        imagedestroy($small);

        if (!$ok) {
            throw new \RuntimeException(Text::_('MOD_PANNELLUM_ERROR_TEMP_FILE'));
        }
    }

    /**
     * Lädt ein Bild in GD, nachdem der RAM-Bedarf geprüft wurde.
     */
    private function loadWithGd(string $file, int $width, int $height, int $type, string $label): \GdImage
    {
        $this->assertMemory((int) ($width * $height * 4 * 1.5), $width, $height);

        $img = $this->createGd($file, $type);

        if ($img === false) {
            throw new \RuntimeException(Text::sprintf('MOD_PANNELLUM_ERROR_IMAGE_UNSUPPORTED', $label));
        }

        if (!imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }

        return $img;
    }

    /**
     * @return  \GdImage|false
     */
    private function createGd(string $file, int $type)
    {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return @imagecreatefromjpeg($file);
            case IMAGETYPE_PNG:
                return @imagecreatefrompng($file);
            case IMAGETYPE_WEBP:
                return \function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false;
        }

        return false;
    }

    /**
     * Prüft den RAM-Bedarf gegen das PHP memory_limit.
     *
     * @throws  \RuntimeException  wenn der Bedarf nicht gedeckt werden kann
     */
    private function assertMemory(int $needed, int $width, int $height): void
    {
        $limit = self::parseBytes((string) \ini_get('memory_limit'));

        if ($limit < 0) {
            return;
        }

        $available = $limit - memory_get_usage(true);

        if ($needed > $available) {
            throw new \RuntimeException(Text::sprintf(
                'MOD_PANNELLUM_ERROR_IMAGE_TOO_LARGE',
                $width,
                $height,
                (int) ceil($needed / 1048576),
                (int) floor(max(0, $available) / 1048576)
            ));
        }
    }

    private static function parseBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $number = (int) $value;

        switch (strtolower(substr($value, -1))) {
            case 'g':
                $number *= 1024;
                // no break
            case 'm':
                $number *= 1024;
                // no break
            case 'k':
                $number *= 1024;
        }

        return $number;
    }

    /**
     * Perspektivische Projektion eines Equirectangular-Bildes mit bilinearer Interpolation.
     *
     * Koordinatensystem: x nach rechts, y nach oben, z in Blickrichtung.
     * Yaw 0 / Pitch 0 zeigt auf die Bildmitte des Panoramas (wie in Pannellum).
     */
    private function project(\GdImage $src, int $srcW, int $srcH, float $yaw, float $pitch, float $hfov, int $width, int $height): \GdImage
    {
        $dst = imagecreatetruecolor($width, $height);

        $tanHalf = tan(deg2rad($hfov) / 2);
        $ratio   = $height / $width;
        $cosP    = cos(deg2rad($pitch));
        $sinP    = sin(deg2rad($pitch));
        $cosY    = cos(deg2rad($yaw));
        $sinY    = sin(deg2rad($yaw));
        $maxX    = $srcW - 1;
        $maxY    = $srcH - 1;

        for ($y = 0; $y < $height; $y++) {
            // Normalisierte Koordinate in der Bildmitte des Pixels, oben = +1
            $ny  = 1 - 2 * ($y + 0.5) / $height;
            $vy0 = $ny * $tanHalf * $ratio;

            for ($x = 0; $x < $width; $x++) {
                $vx0 = (2 * ($x + 0.5) / $width - 1) * $tanHalf;

                // Rotation um die X-Achse (Pitch), dann um die Y-Achse (Yaw); vz0 = 1
                $y1 = $vy0 * $cosP + $sinP;
                $z1 = -$vy0 * $sinP + $cosP;
                $x2 = $vx0 * $cosY + $z1 * $sinY;
                $z2 = -$vx0 * $sinY + $z1 * $cosY;

                $len = sqrt($x2 * $x2 + $y1 * $y1 + $z2 * $z2);

                $lon = atan2($x2, $z2);
                $lat = asin($y1 / $len);

                $fx = ($lon / M_PI + 1) / 2 * $srcW - 0.5;
                $fy = (1 - $lat / (M_PI / 2)) / 2 * $srcH - 0.5;

                $x0 = (int) floor($fx);
                $y0 = (int) floor($fy);
                $tx = $fx - $x0;
                $ty = $fy - $y0;

                // Horizontal umlaufend, vertikal begrenzt
                $xa = (($x0 % $srcW) + $srcW) % $srcW;
                $xb = ($xa + 1) % $srcW;
                $ya = min($maxY, max(0, $y0));
                $yb = min($maxY, max(0, $y0 + 1));

                $c00 = imagecolorat($src, $xa, $ya);
                $c10 = imagecolorat($src, $xb, $ya);
                $c01 = imagecolorat($src, $xa, $yb);
                $c11 = imagecolorat($src, $xb, $yb);

                $w00 = (1 - $tx) * (1 - $ty);
                $w10 = $tx * (1 - $ty);
                $w01 = (1 - $tx) * $ty;
                $w11 = $tx * $ty;

                $r = (int) round(
                    (($c00 >> 16) & 255) * $w00 + (($c10 >> 16) & 255) * $w10
                    + (($c01 >> 16) & 255) * $w01 + (($c11 >> 16) & 255) * $w11
                );
                $g = (int) round(
                    (($c00 >> 8) & 255) * $w00 + (($c10 >> 8) & 255) * $w10
                    + (($c01 >> 8) & 255) * $w01 + (($c11 >> 8) & 255) * $w11
                );
                $b = (int) round(
                    ($c00 & 255) * $w00 + ($c10 & 255) * $w10
                    + ($c01 & 255) * $w01 + ($c11 & 255) * $w11
                );

                imagesetpixel($dst, $x, $y, ($r << 16) | ($g << 8) | $b);
            }
        }

        return $dst;
    }

    /**
     * Begrenzt Yaw, Pitch und hfov auf zulässige Werte (und rundet für stabile Dateinamen).
     *
     * @return  float[]
     */
    private static function sanitizeView(float $yaw, float $pitch, float $hfov): array
    {
        return [
            round(max(-180.0, min(180.0, $yaw)), 2),
            round(max(-90.0, min(90.0, $pitch)), 2),
            round(max(30.0, min(120.0, $hfov)), 2),
        ];
    }

    /**
     * Schreibt einen Eintrag in logs/mod_pannellum.log.php.
     * Probleme werden immer protokolliert, Details nur im Debug-Modus.
     * Das Format enthält bewusst keine IP-Adresse.
     */
    private static function log(string $message, int $priority = Log::WARNING): void
    {
        static $registered = false;

        if ($priority > Log::WARNING && !self::$debug) {
            return;
        }

        try {
            if (!$registered) {
                Log::addLogger(
                    [
                        'text_file'         => 'mod_pannellum.log.php',
                        'text_entry_format' => "{DATETIME}\t{PRIORITY}\t{MESSAGE}",
                    ],
                    Log::ALL,
                    ['mod_pannellum']
                );
                $registered = true;
            }

            Log::add($message, $priority, 'mod_pannellum');
        } catch (\Throwable $e) {
            // Logging darf die Erzeugung nie verhindern
        }
    }

    /**
     * Ausführliche Meldung (nur im Debug-Modus).
     */
    private static function debug(string $message): void
    {
        self::log($message, Log::INFO);
    }

    /**
     * Schreibt einmal pro Request die Server-Umgebung ins Debug-Protokoll.
     */
    private static function logEnvironment(): void
    {
        static $done = false;

        if ($done || !self::$debug) {
            return;
        }

        $done = true;

        $gd = \extension_loaded('gd') ? (\function_exists('gd_info') ? (gd_info()['GD Version'] ?? 'yes') : 'yes') : 'no';

        self::debug(sprintf(
            'Environment: PHP %s, OS %s, memory_limit %s, max_execution_time %s, GD %s, Imagick extension %s, proc_open %s, exec %s',
            PHP_VERSION,
            PHP_OS_FAMILY,
            \ini_get('memory_limit'),
            \ini_get('max_execution_time'),
            $gd,
            \extension_loaded('imagick') ? 'yes' : 'no',
            \function_exists('proc_open') ? 'yes' : 'no',
            \function_exists('exec') ? 'yes' : 'no'
        ));
    }

    private static function sanitizeAspect(string $aspectRatio): string
    {
        return isset(self::SIZES[$aspectRatio]) ? $aspectRatio : '16_9';
    }
}
