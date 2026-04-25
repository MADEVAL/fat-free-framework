<?php

declare(strict_types=1);

namespace Tests\Unit\Util;

use Image;
use PHPUnit\Framework\TestCase;

/**
 * GD-backed image manipulation. All assertions work in-memory using
 * imagecreatefromstring on Image::dump() output.
 */
final class ImageTest extends TestCase
{
    private function fixture(int $w = 40, int $h = 20): Image
    {
        $gd = imagecreatetruecolor($w, $h);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 255, 0, 0));
        ob_start();
        imagepng($gd);
        $bytes = ob_get_clean();
        // Note: imagedestroy() is a no-op since PHP 8.0 and deprecated in 8.5.
        $img = new Image();
        $img->load($bytes);
        return $img;
    }

    public function testRgbHexToTriad(): void
    {
        $img = new Image();
        $this->assertSame([255, 0, 0], $img->rgb(0xFF0000));
        // Numeric values <= 0xFFF use 3-digit shorthand where each nibble doubles.
        $this->assertSame([0, 255, 255], $img->rgb(0x0FF));
    }

    public function testWidthHeightAfterLoad(): void
    {
        $img = $this->fixture(64, 32);
        $this->assertSame(64, $img->width());
        $this->assertSame(32, $img->height());
    }

    public function testInvert(): void
    {
        $img = $this->fixture();
        $img->invert();
        $bytes = $img->dump();
        $this->assertNotEmpty($bytes);
        $check = imagecreatefromstring($bytes);
        $rgb = imagecolorat($check, 0, 0);
        $this->assertSame(0x00FFFF, $rgb & 0xFFFFFF); // red inverted -> cyan
    }

    public function testGrayscale(): void
    {
        $img = $this->fixture();
        $img->grayscale();
        $check = imagecreatefromstring($img->dump());
        $c = imagecolorsforindex($check, imagecolorat($check, 0, 0));
        $this->assertSame($c['red'], $c['green']);
        $this->assertSame($c['green'], $c['blue']);
    }

    public function testResizeNoCrop(): void
    {
        $img = $this->fixture(100, 50);
        $img->resize(40, 40, false);
        // Without crop, aspect ratio preserved: 40x20.
        $this->assertSame(40, $img->width());
        $this->assertSame(20, $img->height());
    }

    public function testResizeWithCrop(): void
    {
        $img = $this->fixture(100, 50);
        $img->resize(40, 40, true);
        $this->assertSame(40, $img->width());
        $this->assertSame(40, $img->height());
    }

    public function testCrop(): void
    {
        $img = $this->fixture(50, 50);
        $img->crop(10, 10, 29, 29);
        $this->assertSame(20, $img->width());
        $this->assertSame(20, $img->height());
    }

    public function testRotate(): void
    {
        $img = $this->fixture(40, 20);
        $img->rotate(90);
        // Rotation swaps width/height.
        $this->assertSame(20, $img->width());
        $this->assertSame(40, $img->height());
    }

    public function testHflipPreservesDimensions(): void
    {
        $img = $this->fixture(40, 20);
        $img->hflip();
        $this->assertSame(40, $img->width());
        $this->assertSame(20, $img->height());
    }

    public function testVflipPreservesDimensions(): void
    {
        $img = $this->fixture(40, 20);
        $img->vflip();
        $this->assertSame(40, $img->width());
        $this->assertSame(20, $img->height());
    }

    public function testBrightnessSepiaContrastEmboss(): void
    {
        $img = $this->fixture();
        $img->brightness(20)->contrast(10)->emboss()->sepia();
        $this->assertNotEmpty($img->dump());
    }

    public function testPixelateAndBlur(): void
    {
        $img = $this->fixture();
        $img->pixelate(3)->blur(false);
        $this->assertNotEmpty($img->dump());
    }

    public function testIdenticonProducesSquareImage(): void
    {
        $img = new Image();
        $img->identicon('user@example.com', 64, 4);
        $this->assertSame(64, $img->width());
        $this->assertSame(64, $img->height());
        $this->assertNotEmpty($img->dump());
    }

    public function testDumpJpegFormat(): void
    {
        $img = $this->fixture();
        $jpg = $img->dump('jpeg', 80);
        $this->assertNotEmpty($jpg);
        $this->assertStringStartsWith("\xFF\xD8", $jpg);
    }

    public function testFileNotFoundThrows(): void
    {
        $this->expectException(\Exception::class);
        new Image('definitely-missing-' . uniqid() . '.png');
    }

    public function testOverlay(): void
    {
        $base = $this->fixture(40, 40);
        $top = $this->fixture(20, 20);
        $base->overlay($top, Image::POS_Center | Image::POS_Middle, 100);
        $this->assertSame(40, $base->width());
    }

    public function testSmooth(): void
    {
        $img = $this->fixture();
        $img->smooth(5);
        $this->assertNotEmpty($img->dump());
    }

    public function testSketch(): void
    {
        $img = $this->fixture();
        $img->sketch();
        $this->assertNotEmpty($img->dump());
    }

    public function testBlurSelective(): void
    {
        $img = $this->fixture();
        $img->blur(true);
        $this->assertNotEmpty($img->dump());
    }

    public function testDataReturnsGdImage(): void
    {
        $img = $this->fixture();
        $this->assertInstanceOf(\GdImage::class, $img->data());
    }

    public function testRenderOutputsImageBytes(): void
    {
        $img = $this->fixture();
        ob_start();
        $img->render();
        $out = ob_get_clean();
        $this->assertNotEmpty($out);
        // PNG signature: first 8 bytes.
        $this->assertStringStartsWith("\x89PNG", $out);
    }

    public function testResizeProportionalByWidth(): void
    {
        // 40x20 -> resize(20, null): height = round((20/40)*20) = 10.
        $img = $this->fixture(40, 20);
        $img->resize(20, null, false);
        $this->assertSame(20, $img->width());
        $this->assertSame(10, $img->height());
    }

    public function testResizeProportionalByHeight(): void
    {
        // 40x20 -> resize(null, 10): width = round((10/20)*40) = 20.
        $img = $this->fixture(40, 20);
        $img->resize(null, 10, false);
        $this->assertSame(20, $img->width());
        $this->assertSame(10, $img->height());
    }

    public function testResizeNoEnlargeKeepsDimensions(): void
    {
        // Trying to enlarge a 40x20 image to 80x40 with $enlarge=false: stays 40x20.
        $img = $this->fixture(40, 20);
        $img->resize(80, 40, false, false);
        $this->assertSame(40, $img->width());
        $this->assertSame(20, $img->height());
    }

    public function testHistoryModeRestoreAndUndo(): void
    {
        // Build a small red image.
        $gd = imagecreatetruecolor(20, 20);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 255, 0, 0));
        ob_start();
        imagepng($gd);
        $bytes = ob_get_clean();

        $img = new Image(null, true); // history enabled
        $img->load($bytes);           // state 1: red
        $img->grayscale();            // state 2: gray

        // After grayscale, R=G=B.
        $c = imagecolorsforindex($img->data(), imagecolorat($img->data(), 0, 0));
        $this->assertSame($c['red'], $c['green']);

        // Restore to state 1: should be red again.
        $img->restore(1);
        $c2 = imagecolorsforindex($img->data(), imagecolorat($img->data(), 0, 0));
        $this->assertGreaterThan(0, $c2['red']);
        $this->assertSame(0, $c2['blue']);
    }

    public function testUndoRevertsLastFilter(): void
    {
        $gd = imagecreatetruecolor(20, 20);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 255, 0, 0));
        ob_start();
        imagepng($gd);
        $bytes = ob_get_clean();

        $img = new Image(null, true);
        $img->load($bytes);   // state 1
        $img->grayscale();    // state 2

        $img->undo();         // back to state 1

        $c = imagecolorsforindex($img->data(), imagecolorat($img->data(), 0, 0));
        $this->assertGreaterThan(0, $c['red']);
        $this->assertSame(0, $c['blue']);
    }

    public function testRgbWithStringHexColor(): void
    {
        $img = new Image();
        // rgb() accepts both integer and string hex inputs.
        $this->assertSame([255, 0, 0], $img->rgb(0xFF0000));
    }

    public function testLoadReturnsFalseOnInvalidData(): void
    {
        $img = new Image();
        $this->assertFalse($img->load('not-an-image'));
    }

    public function testRgbWithStringHexInput(): void
    {
        $img = new Image();
        // 6-digit hex string: 'ff0000' represents red.
        $this->assertSame([255, 0, 0], $img->rgb('ff0000'));
        // 3-digit shorthand string: 'f00' also represents red via doubling.
        $this->assertSame([255, 0, 0], $img->rgb('f00'));
    }

    // -- save ---------------------------------------------------------------

    public function testSaveWritesSnapshotFileToTemp(): void
    {
        $f3      = \Base::instance();
        $prevTemp = $f3->get('TEMP');
        $dir     = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'img-save-' . uniqid() . DIRECTORY_SEPARATOR;
        $f3->set('TEMP', $dir);

        try {
            $img = new Image(null, true); // flag=true: history mode
            $img->load($this->fixture()->dump());

            $result = $img->save();

            // save() returns $this for chaining.
            $this->assertSame($img, $result);

            // A snapshot .png file must exist in the TEMP directory.
            $files = glob($dir . '*.png') ?: [];
            $this->assertNotEmpty($files, 'save() must write a snapshot PNG to TEMP');
        } finally {
            foreach (glob($dir . '*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
            $f3->set('TEMP', $prevTemp);
        }
    }
}
