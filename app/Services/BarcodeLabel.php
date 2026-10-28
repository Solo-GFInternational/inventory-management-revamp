<?php

namespace App\Services;

use App\Models\Inventory;
use Illuminate\Support\Facades\Storage;
use Picqer\Barcode\Renderers\PngRenderer;
use Picqer\Barcode\Types\TypeCode128;
use RuntimeException;

/**
 * The barcode label for an inventory record: a Code 128 barcode of its
 * barcode value (e.g. INV000123) with the value printed underneath, saved
 * as a JPEG on the local disk at Inventory::barcodePath().
 *
 * Sized to be read by a laptop webcam held up to a screen or a printout:
 * 4px per module, 160px tall bars and a wide white quiet zone either side.
 */
class BarcodeLabel
{
    /** Pixels per Code 128 module (the narrowest bar). */
    private const MODULE = 4;

    private const BAR_HEIGHT = 160;

    /** White space around the bars; Code 128 needs at least 10 modules either side. */
    private const QUIET_ZONE = 12 * self::MODULE;

    private const MARGIN_Y = 24;

    /** The printed value: GD's built-in font 5, drawn at twice its size. */
    private const FONT = 5;

    private const TEXT_SCALE = 2;

    private const JPEG_QUALITY = 95;

    /** The barcode value for a record id: INV + the id padded to 6 digits. */
    public static function valueFor(int $id): string
    {
        return 'INV'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /** Write the record's label JPEG (replacing any old one). */
    public function generate(Inventory $inventory): void
    {
        if (! $inventory->barcode) {
            throw new RuntimeException("Inventory record {$inventory->getKey()} has no barcode value.");
        }

        Storage::disk('local')->put($inventory->barcodePath(), $this->render($inventory->barcode));
    }

    /** Make sure the label JPEG exists, regenerating it if it has gone missing. */
    public function ensure(Inventory $inventory): void
    {
        if (! Storage::disk('local')->exists($inventory->barcodePath())) {
            $this->generate($inventory);
        }
    }

    /** The JPEG bytes of a label for this value. */
    public function render(string $value): string
    {
        $barcode = (new TypeCode128)->getBarcode($value);
        $barsWidth = $barcode->getWidth() * self::MODULE;

        // A whole number of pixels per module, so every bar is the same width.
        $bars = imagecreatefromstring(
            (new PngRenderer)->useGd()->setBackgroundColor([255, 255, 255])->render($barcode, $barsWidth, self::BAR_HEIGHT)
        );

        $textWidth = imagefontwidth(self::FONT) * strlen($value) * self::TEXT_SCALE;
        $textHeight = imagefontheight(self::FONT) * self::TEXT_SCALE;

        $width = max($barsWidth, $textWidth) + 2 * self::QUIET_ZONE;
        $height = self::MARGIN_Y + self::BAR_HEIGHT + 12 + $textHeight + self::MARGIN_Y;

        $label = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($label, 255, 255, 255);
        imagefill($label, 0, 0, $white);

        imagecopy($label, $bars, intdiv($width - $barsWidth, 2), self::MARGIN_Y, 0, 0, $barsWidth, self::BAR_HEIGHT);

        // Draw the value at the font's own size, then scale it up onto the label.
        $text = imagecreatetruecolor(intdiv($textWidth, self::TEXT_SCALE), intdiv($textHeight, self::TEXT_SCALE));
        imagefill($text, 0, 0, imagecolorallocate($text, 255, 255, 255));
        imagestring($text, self::FONT, 0, 0, $value, imagecolorallocate($text, 0, 0, 0));
        imagecopyresampled($label, $text, intdiv($width - $textWidth, 2), self::MARGIN_Y + self::BAR_HEIGHT + 12,
            0, 0, $textWidth, $textHeight, imagesx($text), imagesy($text));

        ob_start();
        imagejpeg($label, null, self::JPEG_QUALITY);

        return (string) ob_get_clean();
    }
}
