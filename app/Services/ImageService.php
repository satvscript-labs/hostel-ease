<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Str;

class ImageService
{
    protected ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Compress and convert an image to WebP format.
     *
     * @param UploadedFile $file The uploaded image file.
     * @param int|null $maxWidth The maximum width of the image.
     * @param int|null $maxHeight The maximum height of the image.
     * @param int $quality The quality of the output image (0-100).
     * @return array Returns an array with 'content' (the encoded image) and 'extension'.
     */
    public function compressAndConvertToWebp(UploadedFile $file, ?int $maxWidth = 1200, ?int $maxHeight = 1200, int $quality = 80): array
    {
        $image = $this->manager->read($file->getRealPath());

        if ($maxWidth || $maxHeight) {
            $image->scaleDown($maxWidth, $maxHeight);
        }

        $encoded = $image->toWebp($quality);

        return [
            'content' => (string) $encoded,
            'extension' => 'webp',
        ];
    }
}
