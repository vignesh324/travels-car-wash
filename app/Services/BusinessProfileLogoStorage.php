<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BusinessProfileLogoStorage
{
    public function store(UploadedFile $logo): string
    {
        $path = $logo->getPathname();
        if (! $logo->isValid() || $path === '' || ! is_file($path) || ! is_readable($path)) {
            throw ValidationException::withMessages([
                'logo' => 'The logo upload could not be read. Please choose an image and try again.',
            ]);
        }

        $stream = fopen($path, 'r');
        if ($stream === false) {
            throw ValidationException::withMessages([
                'logo' => 'The logo upload could not be read. Please choose an image and try again.',
            ]);
        }

        try {
            $storedPath = 'logos/' . $logo->hashName();
            if (! Storage::disk('public')->writeStream($storedPath, $stream)) {
                throw new RuntimeException('The business logo could not be stored.');
            }
        } finally {
            fclose($stream);
        }

        return $storedPath;
    }
}
