<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Photos de téléphone (3 à 5 Mo) -> WebP léger pour la 3G.
 * Grande image 1080 px (~100 Ko) + vignette 400 px (~20 Ko) pour la grille.
 * GD uniquement : aucune dépendance en plus.
 */
class ImageProcessor
{
    public const LARGE = 1080;

    public const THUMB = 400;

    public const QUALITY = 75;

    /**
     * @return array{path:string, thumb_path:string}
     */
    public function storeProductPhoto(UploadedFile $file, string $directory, string $disk = 'public'): array
    {
        $image = $this->load($file);
        $name = $directory.'/'.Str::ulid();

        $path = $name.'.webp';
        $thumb = $name.'-400.webp';
        Storage::disk($disk)->put($path, $this->encode($this->resize($image, self::LARGE)));
        Storage::disk($disk)->put($thumb, $this->encode($this->resize($image, self::THUMB, square: true)));
        imagedestroy($image);

        return ['path' => $path, 'thumb_path' => $thumb];
    }

    /** Une seule image réduite (preuve de paiement, logo). */
    public function storeSingle(UploadedFile $file, string $directory, string $disk, int $max = self::LARGE): string
    {
        $image = $this->load($file);
        $path = $directory.'/'.Str::ulid().'.webp';
        Storage::disk($disk)->put($path, $this->encode($this->resize($image, $max)));
        imagedestroy($image);

        return $path;
    }

    private function load(UploadedFile $file): \GdImage
    {
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $image) {
            throw new RuntimeException('Image illisible.');
        }

        // Les photos de téléphone sont souvent "couchées" : on applique l'orientation EXIF.
        if (function_exists('exif_read_data') && in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);
            $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
            if ($angle !== 0) {
                $rotated = imagerotate($image, $angle, 0);
                imagedestroy($image);
                $image = $rotated;
            }
        }

        return $image;
    }

    private function resize(\GdImage $src, int $max, bool $square = false): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);

        if ($square) {
            // Recadrage carré centré pour la grille.
            $side = min($w, $h);
            $sx = intdiv($w - $side, 2);
            $sy = intdiv($h - $side, 2);
            $size = min($max, $side);
            $dst = imagecreatetruecolor($size, $size);
            imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $size, $size, $side, $side);

            return $dst;
        }

        $ratio = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $ratio));
        $nh = max(1, (int) round($h * $ratio));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $dst;
    }

    private function encode(\GdImage $image): string
    {
        ob_start();
        imagewebp($image, null, self::QUALITY);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
