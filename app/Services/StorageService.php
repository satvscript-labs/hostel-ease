<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService
{
    /**
     * Store a file, optionally compressing images and returning the path.
     * 
     * @param UploadedFile|string $file The file, or raw content.
     * @param string $path The directory path to store the file in.
     * @param string|null $disk The storage disk to use (defaults to 'public').
     * @param string|null $extension Override extension when saving raw content.
     * @return string The stored file path.
     */
    public function store($file, string $path, ?string $disk = 'public', ?string $extension = null): string
    {
        $filename = Str::uuid() . '-' . time() . '.' . ($extension ?? (is_string($file) ? 'bin' : $file->getClientOriginalExtension()));
        $fullPath = rtrim($path, '/') . '/' . $filename;

        if (is_string($file)) {
            // It's raw content
            Storage::disk($disk)->put($fullPath, $file);
        } else {
            Storage::disk($disk)->putFileAs($path, $file, $filename);
        }

        return $fullPath;
    }

    /**
     * Delete a file from storage.
     *
     * @param string $path The file path.
     * @param string|null $disk The storage disk.
     */
    public function delete(string $path, ?string $disk = 'public'): void
    {
        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}
