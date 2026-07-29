<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Turns the wallpaper sources into what the browser gets.
 *
 * The originals live in assets/ — full size, PNG, whatever the generator produced, and carrying
 * whatever metadata it wrote into them. public/ gets a resized JPEG and nothing else: re-encoding
 * through GD drops every EXIF, XMP and generation-parameter block on the way, which is the point
 * as much as the weight is.
 *
 * Run by `npm run build`, and on its own with `php artisan bokit:wallpapers`.
 */
class BuildWallpapersCommand extends Command
{
    protected $signature = 'bokit:wallpapers
        {--force : Rebuild even the wallpapers that are already up to date}
        {--width=2560 : Maximum width, in pixels}
        {--quality=82 : JPEG quality}';

    protected $description = 'Convert the wallpaper sources in assets/ into stripped JPEGs in public/';

    public function handle(): int
    {
        $sources = base_path('assets/images/wallpapers');

        if (!File::isDirectory($sources)) {
            $this->components->warn("No wallpaper sources in {$sources}");

            return self::SUCCESS;
        }

        $built = 0;
        $skipped = 0;

        foreach (['light', 'dark'] as $theme) {
            $target = public_path("images/wallpapers/{$theme}");

            File::ensureDirectoryExists($target);

            foreach (File::glob("{$sources}/{$theme}/*.{png,jpg,jpeg,webp}", GLOB_BRACE) ?: [] as $source) {
                // The source is an archive file and may be named as one pleases; what lands in
                // public/ becomes a URL, so spaces and the like are folded out here rather than
                // encoded at every use.
                $name = Str::slug(pathinfo($source, PATHINFO_FILENAME));
                $destination = $target . '/' . $name . '.jpg';

                if (
                    !$this->option('force')
                    && File::exists($destination)
                    && File::lastModified($destination) >= File::lastModified($source)
                ) {
                    $skipped++;

                    continue;
                }

                if ($this->convert($source, $destination)) {
                    $this->components->twoColumnDetail(
                        "{$theme}/" . basename($destination),
                        $this->weight($destination),
                    );
                    $built++;
                }
            }
        }

        $this->components->info("Wallpapers: {$built} built, {$skipped} already up to date");

        return self::SUCCESS;
    }

    private function convert(string $source, string $destination): bool
    {
        $image = @imagecreatefromstring(File::get($source));

        if ($image === false) {
            $this->components->error('Not an image GD can read: ' . basename($source));

            return false;
        }

        $width = imagesx($image);
        $maximum = (int) $this->option('width');

        if ($width > $maximum) {
            $scaled = imagescale($image, $maximum);

            if ($scaled !== false) {
                imagedestroy($image);
                $image = $scaled;
            }
        }

        // A PNG with transparency would otherwise flatten to black.
        $flattened = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefilledrectangle(
            $flattened,
            0,
            0,
            imagesx($image),
            imagesy($image),
            imagecolorallocate($flattened, 255, 255, 255),
        );
        imagecopy($flattened, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        $written = imagejpeg($flattened, $destination, (int) $this->option('quality'));

        imagedestroy($image);
        imagedestroy($flattened);

        return $written;
    }

    private function weight(string $path): string
    {
        return number_format(File::size($path) / 1024, 0, '.', ' ') . ' kB';
    }
}
