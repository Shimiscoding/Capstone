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
        $bytes = file_get_contents($photo->getPathname());
        abort_if($bytes === false, 503, 'An evidence photo could not be read. Attach it again and retry.');

        $disk = Storage::disk('local');
        $path = self::path($reference);
        if (! $disk->put($path, Crypt::encryptString($bytes))) {
            $disk->delete($path);
            abort(503, 'An evidence photo could not be saved. Please retry the citation.');
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
