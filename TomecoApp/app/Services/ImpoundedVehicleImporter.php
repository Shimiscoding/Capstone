<?php

namespace App\Services;

use App\Models\ImpoundedVehicle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;

class ImpoundedVehicleImporter
{
    private const REQUIRED = ['reference', 'owner', 'vehicle', 'type', 'plate', 'violation', 'date_impounded', 'location', 'status'];

    public function __construct(private readonly Settings $settings) {}

    public function import(UploadedFile $file): array
    {
        $rows = IOFactory::load($file->getRealPath())->getActiveSheet()->toArray(null, true, true, false);
        $headerIndex = collect($rows)->search(fn ($row) => in_array('reference', array_map(fn ($cell) => Str::slug((string) $cell, '_'), $row), true));
        if ($headerIndex === false) {
            throw new RuntimeException('The spreadsheet must contain a header row with Reference, Owner, Vehicle, Type, Plate Number, Violation, Date Impounded, Location, and Status.');
        }

        $headers = array_map(fn ($cell) => $this->normalizeHeader((string) $cell), $rows[$headerIndex]);
        if (array_diff(self::REQUIRED, $headers)) {
            throw new RuntimeException('One or more required spreadsheet columns are missing.');
        }

        $imported = 0;
        $updated = 0;
        $errors = [];
        foreach (array_slice($rows, $headerIndex + 1, null, true) as $index => $row) {
            if (collect($row)->filter(fn ($cell) => $cell !== null && trim((string) $cell) !== '')->isEmpty()) {
                continue;
            }
            $data = array_combine($headers, array_pad($row, count($headers), null));
            try {
                $data['date_impounded'] = $this->date($data['date_impounded']);
            } catch (Throwable) {
                $errors[] = 'Row '.($index + 1).': Date Impounded is invalid.';

                continue;
            }
            $validator = Validator::make($data, [
                'reference' => ['required', 'string', 'max:100'], 'owner' => ['required', 'string', 'max:255'],
                'vehicle' => ['required', 'string', 'max:255'], 'type' => ['required', 'in:Car,Motorcycle'],
                'plate' => ['required', 'string', 'max:50'], 'violation' => ['required', 'string', 'max:255'],
                'date_impounded' => ['required', 'date'], 'location' => ['required', 'string', 'max:255'],
                'status' => ['required', 'in:Impounded,For release,Released'],
            ]);
            if ($validator->fails()) {
                $errors[] = 'Row '.($index + 1).': '.$validator->errors()->first();

                continue;
            }

            $attributes = $validator->validated();
            $attributes['impounded_at'] = $attributes['date_impounded'];
            $attributes['user_id'] = User::query()
                ->where('role', User::ROLE_DRIVER)
                ->whereRaw('LOWER(TRIM(plateNumber)) = ?', [Str::lower(trim($attributes['plate']))])
                ->value('id');
            unset($attributes['date_impounded']);
            $record = ImpoundedVehicle::firstOrNew(['reference' => $attributes['reference']]);
            if ($record->exists && $this->settings->get('data.duplicate_behavior') === 'skip') {
                continue;
            }
            $record->fill($attributes)->save();
            $record->wasRecentlyCreated ? $imported++ : $updated++;
        }

        return compact('imported', 'updated', 'errors');
    }

    private function normalizeHeader(string $header): string
    {
        return match (Str::slug($header, '_')) {
            'plate_number' => 'plate',
            default => Str::slug($header, '_'),
        };
    }

    private function date(mixed $value): string
    {
        return is_numeric($value)
            ? ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d')
            : Carbon::parse((string) $value)->format('Y-m-d');
    }
}
