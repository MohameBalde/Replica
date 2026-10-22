<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Store;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Storage;

/**
 * Image verticale 1080 × 1920 à poster en statut WhatsApp ou sur TikTok :
 * la boutique (4 produits) ou un produit, son prix, le lien et un QR code.
 * Dessinée avec GD et la police Inter livrée avec l'app (resources/fonts).
 */
class StatusImage
{
    public const WIDTH = 1080;

    public const HEIGHT = 1920;

    private const INDIGO = [0x37, 0x30, 0xA3];

    private const INDIGO_DARK = [0x1E, 0x1B, 0x4B];

    private const GOLD = [0xF5, 0x9E, 0x0B];

    private \GdImage $img;

    public function forStore(Store $store): string
    {
        $this->canvas();
        $this->badge($store->name, 90);
        // Ligne de base suivante = ligne précédente + taille de la nouvelle police + un espace.
        $y = $this->paragraph($store->name, 'Bold', 68, 0xFFFFFF, 320, 1, center: true);
        $sub = trim(collect([$store->city, $store->description])->filter()->implode(' · '));
        if ($sub !== '') {
            $this->paragraph($sub, 'Medium', 34, $this->rgb([0xC7, 0xD2, 0xFE]), $y + 34 + 24, 2, center: true);
        }

        // Les 4 premiers produits en vente ; un produit épuisé ne sert qu'à compléter.
        $products = $store->products()->where('is_visible', true)->with('firstImage', 'variants')
            ->orderBy('position')->orderByDesc('id')->limit(24)->get()
            ->sortBy(fn (Product $p) => $p->isSoldOut() ? 1 : 0)->take(4);
        $this->grid($products, 500);

        $this->footer($store, route('shop.show', $store), $this->payments($store));

        return $this->jpeg();
    }

    public function forProduct(Product $product): string
    {
        $store = $product->store;
        $this->canvas();
        $this->text($this->fit($store->name, 'Medium', 40, self::WIDTH - 160), 'Medium', 40, $this->rgb([0xC7, 0xD2, 0xFE]), 110, center: true);

        $this->photo($product, 140, 160, 800);
        $y = $this->paragraph($product->name, 'Bold', 60, 0xFFFFFF, 1070, 2, center: true);
        $price = ($product->variants->count() > 1 && $product->variants->min('price') !== $product->variants->max('price') ? 'À partir de ' : '')
            .gnf($product->fromPrice());
        $size = $this->sizeToFit($price, 'Bold', 68, self::WIDTH - 160);
        $y += $size + 34;
        $this->text($price, 'Bold', $size, $this->rgb(self::GOLD), $y, center: true);
        $choices = $product->variants->pluck('name')->reject(fn ($n) => $n === 'Standard');
        if ($choices->isNotEmpty()) {
            $this->paragraph('Choix : '.$choices->take(6)->implode(', ').($choices->count() > 6 ? '…' : ''), 'Medium', 34, $this->rgb([0xC7, 0xD2, 0xFE]), $y + 34 + 24, 1, center: true);
        }

        $this->footer($store, route('shop.product', [$store, $product]), $this->payments($store));

        return $this->jpeg();
    }

    // ----------------------------------------------------------------- dessin

    private function canvas(): void
    {
        $this->img = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagealphablending($this->img, true);
        imagefill($this->img, 0, 0, $this->rgb(self::INDIGO));
        // Bande plus sombre en bas : le bloc du lien ressort.
        imagefilledrectangle($this->img, 0, 1500, self::WIDTH, self::HEIGHT, $this->rgb(self::INDIGO_DARK));
    }

    /** Pastille or avec l'initiale de la boutique. */
    private function badge(string $name, int $y): void
    {
        $cx = intdiv(self::WIDTH, 2);
        imagefilledellipse($this->img, $cx, $y + 70, 140, 140, $this->rgb(self::GOLD));
        $this->text(mb_strtoupper(mb_substr(self::drawable($name) ?: 'W', 0, 1)), 'Bold', 72, $this->rgb(self::INDIGO), $y + 70 + 26, center: true);
    }

    /** 2 × 2 produits : photo carrée, nom, prix. */
    private function grid($products, int $top): void
    {
        if ($products->isEmpty()) {
            $this->paragraph('Découvrez nos produits et commandez en ligne.', 'Bold', 52, 0xFFFFFF, $top + 200, 3, center: true);

            return;
        }
        // Deux rangées de 380 px + nom + prix : tout tient au-dessus du bloc du lien (y = 1500).
        $size = 380;
        $gap = 60;
        $left = intdiv(self::WIDTH - 2 * $size - $gap, 2);
        foreach ($products->values() as $i => $product) {
            $x = $left + ($i % 2) * ($size + $gap);
            $y = $top + intdiv($i, 2) * ($size + 130);
            $this->photo($product, $x, $y, $size);
            $this->text($this->fit($product->name, 'Medium', 30, $size), 'Medium', 30, 0xFFFFFF, $y + $size + 44, x: $x);
            $price = ($product->variants->min('price') !== $product->variants->max('price') ? 'Dès ' : '').gnf($product->fromPrice());
            $this->text($price, 'Bold', $this->sizeToFit($price, 'Bold', 38, $size), $this->rgb(self::GOLD), $y + $size + 92, x: $x);
        }
    }

    /** Photo carrée recadrée au centre ; sans photo, un carré clair avec les initiales. */
    private function photo(Product $product, int $x, int $y, int $size): void
    {
        $path = $product->firstImage?->path;
        $disk = Storage::disk('public');
        $src = $path && $disk->exists($path) ? @imagecreatefromstring((string) $disk->get($path)) : false;

        if (! $src) {
            imagefilledrectangle($this->img, $x, $y, $x + $size, $y + $size, $this->rgb([0xE0, 0xE7, 0xFF]));
            $letters = mb_strtoupper(mb_substr(self::drawable($product->name) ?: '?', 0, 2));
            $this->text($letters, 'Bold', intdiv($size, 4), $this->rgb(self::INDIGO), $y + intdiv($size, 2) + intdiv($size, 10), center: false, x: $x + intdiv($size - $this->width($letters, 'Bold', intdiv($size, 4)), 2));

            return;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        imagecopyresampled($this->img, $src, $x, $y, intdiv($w - $side, 2), intdiv($h - $side, 2), $size, $size, $side, $side);
        imagedestroy($src);
    }

    /** Bloc blanc du bas : « Commandez ici », le lien, les paiements et le QR code. */
    private function footer(Store $store, string $url, string $payments): void
    {
        $top = 1560;
        imagefilledrectangle($this->img, 60, $top, self::WIDTH - 60, $top + 300, 0xFFFFFF);
        $qrSize = 240;
        // Scanné depuis un statut WhatsApp ou une vidéo TikTok : compté comme visite « QR code ».
        $qr = $this->qr($url.'?src=qr', $qrSize);
        imagecopy($this->img, $qr, self::WIDTH - 90 - $qrSize, $top + 30, 0, 0, $qrSize, $qrSize);
        imagedestroy($qr);

        $textWidth = self::WIDTH - 90 - $qrSize - 30 - 100;
        $this->text('Commandez ici', 'Bold', 44, $this->rgb(self::INDIGO), $top + 80, x: 100);
        $link = preg_replace('#^https?://#', '', $url);
        $this->paragraph($link, 'Bold', 34, $this->rgb(self::INDIGO_DARK), $top + 135, 2, x: 100, width: $textWidth, breakAnywhere: true);
        $this->paragraph($payments, 'Medium', 26, $this->rgb([0x5B, 0x64, 0x70]), $top + 232, 2, x: 100, width: $textWidth);
    }

    private function payments(Store $store): string
    {
        $labels = $store->paymentMethods()->where('is_active', true)->get()->filter->isComplete()
            ->map(fn (PaymentMethod $m) => $m->isCashOnDelivery() ? 'à la livraison' : $m->label());

        return $labels->isEmpty() ? 'Commande sur WhatsApp' : 'Paiement : '.$labels->implode(', ');
    }

    private function qr(string $data, int $size): \GdImage
    {
        $png = (new Writer(new GDLibRenderer($size, 1)))->writeString($data);

        return imagecreatefromstring($png);
    }

    // ------------------------------------------------------------------ texte

    private function font(string $weight): string
    {
        return resource_path('fonts/Inter-'.$weight.'.otf');
    }

    private function width(string $text, string $weight, int $size): int
    {
        $box = imagettfbbox($size, 0, $this->font($weight), self::drawable($text));

        return (int) abs($box[2] - $box[0]);
    }

    /**
     * La police Inter couvre le latin, le grec et le cyrillique, et GD lit mal les
     * caractères sur 4 octets : emoji, drapeaux et autres écritures sont retirés
     * (sinon des carrés « NO GLYPH » s'affichent).
     */
    public static function drawable(string $text): string
    {
        // Symboles retirés sauf ceux du latin courant (° © ®), que la police dessine.
        $text = preg_replace('/[^\p{Latin}\p{Greek}\p{Cyrillic}\p{Common}\p{Inherited}]|(?![\x{A0}-\x{FF}])\p{So}|[\p{Cf}\p{Co}\p{Cs}\x{20E3}\x{FE00}-\x{FE0F}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /** Écrit une ligne ; $y est la ligne de base. */
    private function text(string $text, string $weight, int $size, int $color, int $y, bool $center = false, int $x = 0): void
    {
        $text = self::drawable($text);
        if ($center) {
            $x = intdiv(self::WIDTH - $this->width($text, $weight, $size), 2);
        }
        imagettftext($this->img, $size, 0, $x, $y, $color, $this->font($weight), $text);
    }

    /**
     * Texte sur plusieurs lignes (au plus $maxLines, « … » si ça dépasse).
     * $y est la ligne de base de la première ligne ; renvoie celle de la dernière.
     */
    private function paragraph(string $text, string $weight, int $size, int $color, int $y, int $maxLines, bool $center = false, int $x = 80, ?int $width = null, bool $breakAnywhere = false): int
    {
        $width ??= self::WIDTH - 2 * $x;
        $text = self::drawable($text);
        $lines = [];
        $line = '';
        $tokens = $breakAnywhere ? mb_str_split($text) : preg_split('/\s+/u', $text);
        $glue = $breakAnywhere ? '' : ' ';
        foreach ($tokens as $token) {
            $try = $line === '' ? $token : $line.$glue.$token;
            if ($this->width($try, $weight, $size) <= $width || $line === '') {
                $line = $try;

                continue;
            }
            $lines[] = $line;
            $line = $token;
        }
        $lines[] = $line;
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = $this->fit($lines[$maxLines - 1].'…', $weight, $size, $width);
        }
        // Un mot seul plus large que la ligne (ex. un nom sans espace) est coupé lui aussi.
        $lines = array_map(fn ($l) => $this->fit($l, $weight, $size, $width), $lines);

        $lineHeight = (int) round($size * 1.3);
        foreach ($lines as $i => $l) {
            $this->text($l, $weight, $size, $color, $y + $i * $lineHeight, $center, $x);
        }

        return $y + (count($lines) - 1) * $lineHeight;
    }

    /** Plus grande taille (jusqu'à $max) à laquelle le texte tient sur une ligne. */
    private function sizeToFit(string $text, string $weight, int $max, int $width): int
    {
        $size = $max;
        while ($size > 36 && $this->width($text, $weight, $size) > $width) {
            $size -= 2;
        }

        return $size;
    }

    /** Coupe avec « … » pour tenir dans $width. */
    private function fit(string $text, string $weight, int $size, int $width): string
    {
        if ($this->width($text, $weight, $size) <= $width) {
            return $text;
        }
        $text = rtrim($text, '…');
        while (mb_strlen($text) > 1 && $this->width($text.'…', $weight, $size) > $width) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text).'…';
    }

    private function rgb(array $c): int
    {
        return imagecolorallocate($this->img, $c[0], $c[1], $c[2]);
    }

    private function jpeg(): string
    {
        ob_start();
        imagejpeg($this->img, null, 85);
        imagedestroy($this->img);

        return (string) ob_get_clean();
    }
}
