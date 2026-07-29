<?php

namespace Tests\Feature;

use App\Models\ImpoundedVehicle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImpoundedVehicleExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_export_filtered_impounded_vehicles_to_excel(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        ImpoundedVehicle::create(['reference' => 'TEST-CAR-2026', 'owner' => 'Maria Santos', 'vehicle' => 'Toyota Vios', 'type' => 'Car', 'plate' => 'NCR 4821', 'violation' => 'Road obstruction', 'impounded_at' => '2026-07-19', 'location' => 'Main Yard', 'status' => 'For release']);
        ImpoundedVehicle::create(['reference' => 'TEST-CAR-2025', 'owner' => 'Angela Lim', 'vehicle' => 'Mitsubishi Mirage', 'type' => 'Car', 'plate' => 'ABC 9087', 'violation' => 'Abandoned vehicle', 'impounded_at' => '2025-12-17', 'location' => 'Main Yard', 'status' => 'Impounded']);
        ImpoundedVehicle::create(['reference' => 'TEST-MOTORCYCLE-2026', 'owner' => 'Juan Dela Cruz', 'vehicle' => 'Honda Click 125i', 'type' => 'Motorcycle', 'plate' => '123 ABC', 'violation' => 'Illegal parking', 'impounded_at' => '2026-07-20', 'location' => 'Main Yard', 'status' => 'Impounded']);

        $response = $this->actingAs($user)->get(route('dashboard.impounding.export', [
            'vehicle_type' => 'Car',
            'year_from' => 2025,
            'year_to' => 2026,
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename="impounded-vehicles-2025-2026.xls"')
            ->assertSee('Toyota Vios')
            ->assertSee('Mitsubishi Mirage')
            ->assertDontSee('Honda Click 125i');
    }

    public function test_export_rejects_an_inverted_year_range(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($user)->get(route('dashboard.impounding.export', [
            'year_from' => 2026,
            'year_to' => 2025,
        ]))->assertSessionHasErrors('year_to');
    }

    public function test_authenticated_user_can_import_an_xlsx_file(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['Reference', 'Owner', 'Vehicle', 'Type', 'Plate Number', 'Violation', 'Date Impounded', 'Location', 'Status'],
            ['IMP-2026-0100', 'Ana Reyes', 'Honda Civic', 'Car', 'ABC 1000', 'Road obstruction', '2026-07-21', 'Main Yard', 'Impounded'],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'impound').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        try {
            $response = $this->actingAs($user)->post(route('dashboard.impounding.import'), [
                'excel_file' => new UploadedFile($path, 'vehicles.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ]);
        } finally {
            @unlink($path);
        }

        $response->assertRedirect(route('dashboard.impounding'))->assertSessionHas('success');
        $this->assertDatabaseHas('impounded_vehicles', [
            'reference' => 'IMP-2026-0100', 'owner' => 'Ana Reyes', 'plate' => 'ABC 1000',
        ]);
    }

    public function test_exported_html_xls_file_can_be_imported(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $html = '<table><tr><th>Reference</th><th>Owner</th><th>Vehicle</th><th>Type</th><th>Plate Number</th><th>Violation</th><th>Date Impounded</th><th>Location</th><th>Status</th></tr>'
            .'<tr><td>IMP-2026-0200</td><td>Ben Cruz</td><td>Toyota Wigo</td><td>Car</td><td>XYZ 200</td><td>Illegal parking</td><td>2026-07-22</td><td>Main Yard</td><td>Impounded</td></tr></table>';

        $response = $this->actingAs($user)->post(route('dashboard.impounding.import'), [
            'excel_file' => UploadedFile::fake()->createWithContent('export.xls', $html),
        ]);

        $response->assertRedirect(route('dashboard.impounding'))->assertSessionHas('success');
        $this->assertDatabaseHas('impounded_vehicles', [
            'reference' => 'IMP-2026-0200', 'owner' => 'Ben Cruz',
        ]);
    }
}
