<?php

use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerDocumentType;
use App\Models\WorkerImport;
use App\Services\Worker\WorkerService;
use Database\Seeders\PermitCatalogueSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('creates and lists workers for create-workers users', function () {
    $user = User::factory()->withRole('SCC Operator')->create();

    $response = $this->actingAs($user)
        ->post(route('tracking.workers.store'), [
            'name' => 'Jane Doe',
            'contractor' => 'ACME',
            'worker_type' => 'contractor',
            'role_title' => 'Rigger',
            'nationality' => 'Filipino',
            'date_of_birth' => '1990-05-15',
            'joined_on' => '2024-01-10',
            'government_id_number' => '2345678901',
            'badge_number' => 'BDG-1',
        ]);

    $worker = Worker::query()->where('badge_number', 'BDG-1')->firstOrFail();

    $response->assertRedirect(route('tracking.workers.show', [
        'worker' => $worker,
        'onboarding' => 1,
    ]));

    $this->actingAs($user)
        ->get(route('tracking.workers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workforce/workers/index')
            ->has('workers.data', 1)
            ->where('workers.data.0.name', 'Jane Doe')
            ->where('workers.data.0.nationality', 'Filipino')
            ->where('workers.data.0.government_id_number', '2345678901')
            ->where('workers.data.0.date_of_birth', '1990-05-15')
            ->where('workers.data.0.age', $worker->age()));

    expect($worker->present)->toBeFalse()
        ->and($worker->created_by)->toBe($user->id)
        ->and($worker->joined_on?->toDateString())->toBe('2024-01-10');
});

it('shows document checklist and permit readiness on worker show during onboarding', function () {
    $this->seed(PermitCatalogueSeeder::class);

    $user = User::factory()->withRole('Super Admin')->create();
    $worker = Worker::factory()->create(['created_by' => $user->id]);

    $this->actingAs($user)
        ->get(route('tracking.workers.show', ['worker' => $worker, 'onboarding' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workforce/workers/show')
            ->where('onboarding', true)
            ->has('documentChecklist')
            ->has('permitReadiness')
            ->has('readinessSummary')
            ->where('readinessSummary.verified_docs', 0));
});

it('strips identity without view-worker-identity', function () {
    $operator = User::factory()->withRole('SCC Operator')->create();
    $pm = User::factory()->withRole('Project Manager')->create();
    $worker = Worker::factory()->create([
        'name' => 'Secret Name',
        'badge_number' => 'BDG-SECRET',
        'phone' => '+15551212',
        'employee_code' => 'EMP-SECRET',
        'government_id_number' => 'ID-SECRET',
        'date_of_birth' => '1988-03-01',
        'nationality' => 'Saudi',
        'joined_on' => '2023-06-01',
        'created_by' => $operator->id,
    ]);

    expect($pm->can('view-worker-identity'))->toBeFalse()
        ->and($pm->can('view-tracking'))->toBeTrue();

    $this->actingAs($pm)
        ->get(route('tracking.workers.show', $worker))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('worker.name', "Worker #{$worker->id}")
            ->where('worker.badge_number', null)
            ->where('worker.phone', null)
            ->where('worker.employee_code', null)
            ->where('worker.government_id_number', null)
            ->where('worker.date_of_birth', null)
            ->where('worker.age', null)
            ->where('worker.nationality', 'Saudi')
            ->where('worker.joined_on', '2023-06-01')
            ->where('worker.contractor', $worker->contractor));

    $this->actingAs($operator)
        ->get(route('tracking.workers.show', $worker))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('worker.name', 'Secret Name')
            ->where('worker.badge_number', 'BDG-SECRET')
            ->where('worker.government_id_number', 'ID-SECRET')
            ->where('worker.date_of_birth', '1988-03-01')
            ->where('worker.age', $worker->age()));
});

it('disables name search for users without view-worker-identity', function () {
    $operator = User::factory()->withRole('SCC Operator')->create();
    $pm = User::factory()->withRole('Project Manager')->create();

    Worker::factory()->create([
        'name' => 'Hidden Person',
        'contractor' => 'VisibleCo',
        'created_by' => $operator->id,
    ]);

    $this->actingAs($pm)
        ->get(route('tracking.workers.index', ['search' => 'Hidden']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('workers.data', 0));

    $this->actingAs($pm)
        ->get(route('tracking.workers.index', ['search' => 'VisibleCo']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('workers.data', 1));
});

it('rejects present and last_seen_at on store', function () {
    $user = User::factory()->withRole('SCC Operator')->create();

    $this->actingAs($user)
        ->post(route('tracking.workers.store'), [
            'name' => 'Probe',
            'contractor' => 'ACME',
            'worker_type' => 'employee',
            'present' => true,
            'last_seen_at' => now()->toIso8601String(),
        ])
        ->assertRedirect();

    $worker = Worker::query()->where('name', 'Probe')->firstOrFail();

    expect($worker->present)->toBeFalse()
        ->and($worker->last_seen_at)->toBeNull();
});

it('blocks deactivate when worker is present', function () {
    $user = User::factory()->withRole('SCC Operator')->create();
    $worker = Worker::factory()->present()->create(['created_by' => $user->id]);

    $this->actingAs($user)
        ->post(route('tracking.workers.deactivate', $worker))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.message');
});

it('offboards an inactive-site worker', function () {
    $user = User::factory()->withRole('SCC Operator')->create();
    $worker = Worker::factory()->create([
        'is_active' => true,
        'present' => false,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('tracking.workers.offboard', $worker))
        ->assertRedirect();

    expect($worker->fresh()->is_active)->toBeFalse();
});

it('forbids workers list without view-tracking', function () {
    $user = User::factory()->withRole('Field Staff')->create();

    $this->actingAs($user)
        ->get(route('tracking.workers.index'))
        ->assertForbidden();
});

it('imports valid csv rows partially when some fail', function () {
    Storage::fake('private');

    $user = User::factory()->withRole('SCC Operator')->create();

    $csv = implode("\n", [
        'name,contractor,worker_type,role_title,nationality,date_of_birth,joined_on,government_id_number,badge_number,employee_code,phone,notes',
        'Alice,ACME,contractor,Rigger,Filipino,1992-01-02,2024-02-01,111222333,BDG-A1,,,',
        'Bad,,visitor,,,,,,,,,',
        'Bob,BuildCo,employee,Welder,Indian,1985-07-20,2023-11-15,444555666,BDG-B1,,,',
    ]);

    $file = UploadedFile::fake()->createWithContent('roster.csv', $csv);

    $this->actingAs($user)
        ->post(route('tracking.workers.import.store'), ['file' => $file])
        ->assertRedirect(route('tracking.workers.import'));

    $import = WorkerImport::query()->latest('id')->firstOrFail();

    expect(Worker::query()->count())->toBe(2)
        ->and($import->status)->toBe('completed')
        ->and($import->stored_path)->toBe('')
        ->and($import->summary['created'])->toBe(2)
        ->and($import->summary['error_count'])->toBeGreaterThan(0)
        ->and($import->summary)->not->toHaveKey('errors')
        ->and(session('worker_import_result.errors'))->not->toBeEmpty()
        ->and(Storage::disk('private')->allFiles())->toBe([])
        ->and(Worker::query()->where('badge_number', 'BDG-A1')->value('nationality'))->toBe('Filipino')
        ->and(Worker::query()->where('badge_number', 'BDG-A1')->value('government_id_number'))->toBe('111222333');
});

it('imports the Aramco manpower Excel columns', function () {
    Storage::fake('private');
    $user = User::factory()->withRole('SCC Operator')->create();
    $xlsxPath = tempnam(sys_get_temp_dir(), 'workers-');
    $zip = new ZipArchive;
    $zip->open($xlsxPath);
    $cell = static fn (string $reference, string $value): string => '<c r="'.$reference.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
    $headers = ['Employee', 'FullNameEn', 'DateOfBirth', 'Nationality', 'Job Title', 'Iqama / ID', 'Iqama Expire in Muqeem', 'HiringDate', 'Project / Cost Center ID', 'Mobile', 'EmpStatusID'];
    $values = ['23488', 'MOHAMMED MUNEER ABDULLAH ALSUBAIE', '34533', 'Saudi Arabia', 'Security', '1082681469', '0', '46266', 'Aramco-Early works for SUGCP', '0554873710', 'On Duty'];
    $rows = [];
    foreach ([$headers, $values] as $rowNumber => $row) {
        $cells = [];
        foreach ($row as $column => $value) {
            $cells[] = $cell(chr(65 + $column).($rowNumber + 1), $value);
        }
        $rows[] = '<row r="'.($rowNumber + 1).'">'.implode('', $cells).'</row>';
    }
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets><sheet name="Employees Full File" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.implode('', $rows).'</sheetData></worksheet>');
    $zip->close();
    $path = 'imports/workers/aramco.xlsx';
    Storage::disk('private')->put($path, file_get_contents($xlsxPath));
    unlink($xlsxPath);
    $import = WorkerImport::query()->create([
        'created_by' => $user->id,
        'original_filename' => 'aramco.xlsx',
        'stored_path' => $path,
        'status' => 'pending',
    ]);

    $summary = app(WorkerService::class)->processImport($import);
    $worker = Worker::query()->where('employee_code', '23488')->firstOrFail();

    expect($summary['created'])->toBe(1)
        ->and($import->fresh()->stored_path)->toBe('')
        ->and(Storage::disk('private')->exists($path))->toBeFalse()
        ->and($worker->name)->toBe('MOHAMMED MUNEER ABDULLAH ALSUBAIE')
        ->and($worker->contractor)->toBe('Aramco-Early works for SUGCP')
        ->and($worker->worker_type)->toBe(\App\Enums\WorkerType::Employee)
        ->and($worker->date_of_birth?->toDateString())->toBe('1994-07-18')
        ->and($worker->joined_on?->toDateString())->toBe('2026-09-01')
        ->and($worker->government_id_number)->toBe('1082681469')
        ->and($worker->phone)->toBe('0554873710');
});

it('downloads an Aramco-shaped import template', function () {
    $user = User::factory()->withRole('SCC Operator')->create();

    $response = $this->actingAs($user)->get(route('tracking.workers.import.template'));

    $response->assertOk();
    $csv = (string) $response->getContent();
    expect($csv)
        ->toContain('Employee,FullNameEn,DateOfBirth,Nationality,Job Title,Iqama / ID')
        ->toContain('Project / Cost Center ID,Mobile,EmpStatusID')
        ->and($response->headers->get('content-disposition'))
        ->toContain('aramco-manpower-import-template.csv');
});

it('imports Aramco xlsx when the PHP temp upload path has no extension', function () {
    Storage::fake('private');
    $user = User::factory()->withRole('SCC Operator')->create();
    $xlsxPath = tempnam(sys_get_temp_dir(), 'workers-');
    $zip = new ZipArchive;
    $zip->open($xlsxPath);
    $cell = static fn (string $reference, string $value): string => '<c r="'.$reference.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
    $headers = ['Employee', 'FullNameEn', 'DateOfBirth', 'Nationality', 'Job Title', 'Iqama / ID', 'Iqama Expire in Muqeem', 'HiringDate', 'Project / Cost Center ID', 'Mobile', 'EmpStatusID'];
    $values = ['23488', 'MOHAMMED MUNEER ABDULLAH ALSUBAIE', '34533', 'Saudi Arabia', 'Security', '1082681469', '0', '46266', 'Aramco-Early works for SUGCP', '0554873710', 'On Duty'];
    $rows = [];
    foreach ([$headers, $values] as $rowNumber => $row) {
        $cells = [];
        foreach ($row as $column => $value) {
            $cells[] = $cell(chr(65 + $column).($rowNumber + 1), $value);
        }
        $rows[] = '<row r="'.($rowNumber + 1).'">'.implode('', $cells).'</row>';
    }
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets><sheet name="Employees Full File" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.implode('', $rows).'</sheetData></worksheet>');
    $zip->close();

    // Mimic PHP upload temp file: no .xlsx suffix on disk, only on the client name.
    $tmpUpload = tempnam(sys_get_temp_dir(), 'php');
    file_put_contents($tmpUpload, file_get_contents($xlsxPath));
    unlink($xlsxPath);
    expect(pathinfo($tmpUpload, PATHINFO_EXTENSION))->toBe('');

    $file = new UploadedFile(
        $tmpUpload,
        'Aramco Project Manpower List.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );

    $summary = app(WorkerService::class)->importUploadedFile($file, $user->id);

    expect($summary['created'])->toBe(1)
        ->and($summary['errors'])->toBe([])
        ->and(Worker::query()->where('employee_code', '23488')->value('name'))
        ->toBe('MOHAMMED MUNEER ABDULLAH ALSUBAIE');
});

it('seeds vaccination as a worker document type', function () {
    $this->seed(PermitCatalogueSeeder::class);

    expect(WorkerDocumentType::query()->where('code', 'vaccination')->exists())->toBeTrue();
});

it('updates on re-import matched by badge_number', function () {
    Storage::fake('private');
    $user = User::factory()->withRole('SCC Operator')->create();
    Worker::factory()->create([
        'name' => 'Old Name',
        'badge_number' => 'BDG-DUP',
        'contractor' => 'ACME',
        'created_by' => $user->id,
    ]);

    $csv = "name,contractor,worker_type,role_title,nationality,date_of_birth,joined_on,government_id_number,badge_number,employee_code,phone,notes\nNew Name,ACME,contractor,Lead,Saudi,1991-04-04,2022-01-01,999888777,BDG-DUP,,,\n";
    $path = 'imports/workers/test.csv';
    Storage::disk('private')->put($path, $csv);

    $import = WorkerImport::query()->create([
        'created_by' => $user->id,
        'original_filename' => 'test.csv',
        'stored_path' => $path,
        'status' => 'pending',
    ]);

    $this->actingAs($user);
    $summary = app(WorkerService::class)->processImport($import);

    expect($summary['updated'])->toBe(1)
        ->and($summary['created'])->toBe(0)
        ->and($import->fresh()->stored_path)->toBe('')
        ->and(Storage::disk('private')->exists($path))->toBeFalse()
        ->and(Worker::query()->where('badge_number', 'BDG-DUP')->value('name'))->toBe('New Name')
        ->and(Worker::query()->count())->toBe(1);
});

it('only syncPresenceMirror writes present state', function () {
    $worker = Worker::factory()->create(['present' => false]);

    expect(fn () => app(WorkerService::class)->deactivate(
        Worker::factory()->present()->create()
    ))->toThrow(HttpException::class);

    app(WorkerService::class)->syncPresenceMirror($worker, true);

    expect($worker->fresh()->present)->toBeTrue()
        ->and($worker->fresh()->last_seen_at)->not->toBeNull();
});
