<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TicketEvidence
{
    public const PREFIX = 'evidence-file:';

    public static function store(UploadedFile $photo): string
    {
        $reference = self::PREFIX.Str::uuid();
        if (! Storage::disk('local')->put(self::path($reference), Crypt::encryptString(file_get_contents($photo->getRealPath())))) {
            throw new \RuntimeException('Could not save encrypted evidence.');
        }

        return $reference;
    }

    public static function read(string $reference): string
    {
        $path = self::path($reference);
        abort_unless(Storage::disk('local')->exists($path), 404, 'Evidence file is unavailable.');

        return Crypt::decryptString(Storage::disk('local')->get($path));
    }

    public static function delete(string $reference): void
    {
        if (str_starts_with($reference, self::PREFIX)) {
            Storage::disk('local')->delete(self::path($reference));
        }
    }

    private static function path(string $reference): string
    {
        $id = substr($reference, strlen(self::PREFIX));
        if (! str_starts_with($reference, self::PREFIX) || ! Str::isUuid($id)) {
            throw new \InvalidArgumentException('Invalid evidence reference.');
        }

        return 'ticket-evidence/'.$id.'.encrypted';
    }
}
