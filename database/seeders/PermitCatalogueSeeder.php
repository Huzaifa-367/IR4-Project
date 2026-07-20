<?php

namespace Database\Seeders;

use App\Models\PermitType;
use App\Models\PermitTypeChecklistItem;
use App\Models\PermitTypeDocumentRequirement;
use App\Models\PermitTypeGasChannel;
use App\Models\PermitTypeRole;
use App\Models\WorkerDocumentType;
use Illuminate\Database\Seeder;

final class PermitCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $documentTypes = $this->seedWorkerDocumentTypes();
        $this->seedPermitTypes($documentTypes);
    }

    /**
     * @return array<string, WorkerDocumentType>
     */
    private function seedWorkerDocumentTypes(): array
    {
        $definitions = [
            ['code' => 'iqama', 'name' => 'Iqama / National ID', 'category' => 'identity', 'sort_order' => 10],
            ['code' => 'medical_fitness', 'name' => 'Occupational Medical Fitness', 'category' => 'medical', 'sort_order' => 20],
            ['code' => 'h2s_awareness', 'name' => 'H₂S Awareness / SCBA', 'category' => 'competence', 'sort_order' => 30],
            ['code' => 'cse_entrant', 'name' => 'Confined Space Entrant', 'category' => 'competence', 'sort_order' => 40],
            ['code' => 'cse_standby', 'name' => 'Confined Space Standby', 'category' => 'competence', 'sort_order' => 50],
            ['code' => 'fire_watch', 'name' => 'Fire Watch Competence', 'category' => 'competence', 'sort_order' => 60],
            ['code' => 'hot_work_welder', 'name' => 'Hot Work / Welding Craft Card', 'category' => 'competence', 'sort_order' => 70],
            ['code' => 'loto_authorized', 'name' => 'LOTO Authorized Employee', 'category' => 'competence', 'sort_order' => 80],
            ['code' => 'work_at_height', 'name' => 'Work at Height / Fall Protection', 'category' => 'competence', 'sort_order' => 90],
        ];

        /** @var array<string, WorkerDocumentType> $indexed */
        $indexed = [];

        foreach ($definitions as $row) {
            $indexed[$row['code']] = WorkerDocumentType::query()->firstOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'description' => null,
                    'category' => $row['category'],
                    'requires_expiry' => true,
                    'requires_file' => true,
                    'is_active' => true,
                    'sort_order' => $row['sort_order'],
                ],
            );
        }

        return $indexed;
    }

    /**
     * @param  array<string, WorkerDocumentType>  $documentTypes
     */
    private function seedPermitTypes(array $documentTypes): void
    {
        $equipmentOpening = $this->upsertPermitType([
            'code' => 'equipment_opening',
            'name' => 'Equipment Opening / Line Break',
            'description' => 'Initial opening of closed systems with flammable, toxic, or injurious contents (SA 9873-1).',
            'colour_token' => 'yellow',
            'sa_form_code' => '9873-1',
            'requires_gas_test' => true,
            'requires_approver' => false,
            'requires_joint_inspection' => true,
            'default_validity_minutes' => 480,
            'max_renewals' => 1,
            'max_total_minutes' => 1440,
            'allows_extended' => false,
            'retest_interval_minutes' => 120,
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $hotWork = $this->upsertPermitType([
            'code' => 'hot_work',
            'name' => 'Hot Work',
            'description' => 'Ignition energy work including welding, cutting, grinding, and blasting (SA 9873-2).',
            'colour_token' => 'red',
            'sa_form_code' => '9873-2',
            'requires_gas_test' => true,
            'requires_approver' => true,
            'requires_joint_inspection' => true,
            'default_validity_minutes' => 480,
            'max_renewals' => 1,
            'max_total_minutes' => 1440,
            'allows_extended' => false,
            'retest_interval_minutes' => 60,
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $coldWork = $this->upsertPermitType([
            'code' => 'cold_work',
            'name' => 'Cold Work',
            'description' => 'Hazardous work without ignition energy (SA 9873-3).',
            'colour_token' => 'blue',
            'sa_form_code' => '9873-3',
            'requires_gas_test' => false,
            'requires_approver' => false,
            'requires_joint_inspection' => true,
            'default_validity_minutes' => 480,
            'max_renewals' => 1,
            'max_total_minutes' => 1440,
            'allows_extended' => false,
            'retest_interval_minutes' => null,
            'sort_order' => 30,
            'is_active' => true,
        ]);

        $confinedSpace = $this->upsertPermitType([
            'code' => 'confined_space',
            'name' => 'Confined Space Entry',
            'description' => 'Entry into spaces not designed for continuous occupancy (SA 9873-4).',
            'colour_token' => 'green',
            'sa_form_code' => '9873-4',
            'requires_gas_test' => true,
            'requires_approver' => true,
            'requires_joint_inspection' => true,
            'default_validity_minutes' => 480,
            'max_renewals' => 1,
            'max_total_minutes' => 1440,
            'allows_extended' => true,
            'retest_interval_minutes' => 30,
            'sort_order' => 40,
            'is_active' => true,
        ]);

        $this->upsertPermitType([
            'code' => 'excavation',
            'name' => 'Excavation',
            'description' => 'Digging and earthwork with utility clearance and edge protection.',
            'colour_token' => 'orange',
            'sa_form_code' => null,
            'requires_gas_test' => false,
            'requires_approver' => false,
            'requires_joint_inspection' => true,
            'default_validity_minutes' => 480,
            'max_renewals' => 1,
            'max_total_minutes' => 1440,
            'allows_extended' => false,
            'retest_interval_minutes' => null,
            'sort_order' => 50,
            'is_active' => true,
        ]);

        $this->upsertPermitType([
            'code' => 'electrical',
            'name' => 'Electrical Work',
            'description' => 'Live or de-energized electrical isolation work.',
            'colour_token' => 'purple',
            'sa_form_code' => null,
            'requires_gas_test' => false,
            'requires_approver' => false,
            'requires_joint_inspection' => true,
            'default_validity_minutes' => 480,
            'max_renewals' => 1,
            'max_total_minutes' => 1440,
            'allows_extended' => false,
            'retest_interval_minutes' => null,
            'sort_order' => 60,
            'is_active' => false,
        ]);

        $this->upsertPermitType([
            'code' => 'work_at_height',
            'name' => 'Work at Height',
            'description' => 'Fall-from-height work requiring harness and anchor points.',
            'colour_token' => 'cyan',
            'sa_form_code' => null,
            'requires_gas_test' => false,
            'requires_approver' => false,
            'requires_joint_inspection' => true,
            'default_validity_minutes' => 480,
            'max_renewals' => 1,
            'max_total_minutes' => 1440,
            'allows_extended' => false,
            'retest_interval_minutes' => null,
            'sort_order' => 70,
            'is_active' => true,
        ]);

        $this->seedHotWorkPack($hotWork, $documentTypes);
        $this->seedConfinedSpacePack($confinedSpace, $documentTypes);
        $this->seedEquipmentOpeningPack($equipmentOpening, $documentTypes);
        $this->seedColdWorkPack($coldWork);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertPermitType(array $attributes): PermitType
    {
        return PermitType::query()->updateOrCreate(
            ['code' => $attributes['code']],
            $attributes,
        );
    }

    /**
     * @param  array<string, WorkerDocumentType>  $documentTypes
     */
    private function seedHotWorkPack(PermitType $type, array $documentTypes): void
    {
        PermitTypeRole::query()->updateOrCreate(
            ['permit_type_id' => $type->id, 'role_code' => 'fire_watch'],
            [
                'label' => 'Fire Watch',
                'min_count' => 1,
                'is_mandatory' => true,
                'sort_order' => 10,
            ],
        );

        PermitTypeRole::query()->updateOrCreate(
            ['permit_type_id' => $type->id, 'role_code' => 'supervisor'],
            [
                'label' => 'Work Supervisor',
                'min_count' => 1,
                'is_mandatory' => true,
                'sort_order' => 20,
            ],
        );

        $gasChannels = [
            ['channel_code' => 'o2_pct', 'label' => 'Oxygen', 'unit' => '%vol', 'alarm_below' => 19.0, 'alarm_above' => 23.5, 'sort_order' => 10],
            ['channel_code' => 'lel_pct', 'label' => 'LEL', 'unit' => '%LEL', 'alarm_above' => 10.0, 'sort_order' => 20],
            ['channel_code' => 'h2s_ppm', 'label' => 'H₂S', 'unit' => 'ppm', 'alarm_above' => 10.0, 'sort_order' => 30],
            ['channel_code' => 'co_ppm', 'label' => 'CO', 'unit' => 'ppm', 'alarm_above' => 35.0, 'sort_order' => 40],
        ];

        foreach ($gasChannels as $channel) {
            PermitTypeGasChannel::query()->updateOrCreate(
                ['permit_type_id' => $type->id, 'channel_code' => $channel['channel_code']],
                array_merge($channel, [
                    'warn_below' => $channel['warn_below'] ?? null,
                    'warn_above' => $channel['warn_above'] ?? null,
                    'alarm_below' => $channel['alarm_below'] ?? null,
                    'alarm_above' => $channel['alarm_above'] ?? null,
                ]),
            );
        }

        $checklist = [
            ['code' => 'area_cleared', 'label' => 'Work area cleared of combustibles within 11 m (35 ft)', 'sort_order' => 10],
            ['code' => 'extinguisher_ready', 'label' => 'Fire extinguisher available and inspected', 'sort_order' => 20],
            ['code' => 'gas_test_complete', 'label' => 'Atmospheric gas test completed and within limits', 'sort_order' => 30],
            ['code' => 'fire_watch_assigned', 'label' => 'Dedicated fire watch assigned and briefed', 'sort_order' => 40],
        ];

        foreach ($checklist as $item) {
            PermitTypeChecklistItem::query()->updateOrCreate(
                ['permit_type_id' => $type->id, 'code' => $item['code']],
                [
                    'label' => $item['label'],
                    'is_mandatory' => true,
                    'sort_order' => $item['sort_order'],
                    'is_active' => true,
                ],
            );
        }

        $this->upsertDocRequirement($type, $documentTypes['fire_watch'], 'fire_watch');
        $this->upsertDocRequirement($type, $documentTypes['medical_fitness'], 'fire_watch');
        $this->upsertDocRequirement($type, $documentTypes['hot_work_welder'], 'supervisor');
        $this->upsertDocRequirement($type, $documentTypes['h2s_awareness'], null);
    }

    /**
     * @param  array<string, WorkerDocumentType>  $documentTypes
     */
    private function seedConfinedSpacePack(PermitType $type, array $documentTypes): void
    {
        PermitTypeRole::query()->updateOrCreate(
            ['permit_type_id' => $type->id, 'role_code' => 'entrant'],
            [
                'label' => 'Confined Space Entrant',
                'min_count' => 1,
                'is_mandatory' => true,
                'sort_order' => 10,
            ],
        );

        PermitTypeRole::query()->updateOrCreate(
            ['permit_type_id' => $type->id, 'role_code' => 'standby'],
            [
                'label' => 'Confined Space Standby',
                'min_count' => 1,
                'is_mandatory' => true,
                'sort_order' => 20,
            ],
        );

        $gasChannels = [
            ['channel_code' => 'o2_pct', 'label' => 'Oxygen', 'unit' => '%vol', 'alarm_below' => 19.5, 'alarm_above' => 23.5, 'sort_order' => 10],
            ['channel_code' => 'lel_pct', 'label' => 'LEL', 'unit' => '%LEL', 'alarm_above' => 10.0, 'sort_order' => 20],
            ['channel_code' => 'h2s_ppm', 'label' => 'H₂S', 'unit' => 'ppm', 'alarm_above' => 10.0, 'sort_order' => 30],
            ['channel_code' => 'co_ppm', 'label' => 'CO', 'unit' => 'ppm', 'alarm_above' => 35.0, 'sort_order' => 40],
        ];

        foreach ($gasChannels as $channel) {
            PermitTypeGasChannel::query()->updateOrCreate(
                ['permit_type_id' => $type->id, 'channel_code' => $channel['channel_code']],
                array_merge($channel, [
                    'warn_below' => $channel['warn_below'] ?? null,
                    'warn_above' => $channel['warn_above'] ?? null,
                    'alarm_below' => $channel['alarm_below'] ?? null,
                    'alarm_above' => $channel['alarm_above'] ?? null,
                ]),
            );
        }

        $checklist = [
            ['code' => 'isolation_verified', 'label' => 'Energy isolation and LOTO verified', 'sort_order' => 10],
            ['code' => 'rescue_plan', 'label' => 'Rescue plan and equipment in place', 'sort_order' => 20],
            ['code' => 'ventilation', 'label' => 'Ventilation/blowers operational where required', 'sort_order' => 30],
            ['code' => 'communication', 'label' => 'Entrant–standby communication tested', 'sort_order' => 40],
        ];

        foreach ($checklist as $item) {
            PermitTypeChecklistItem::query()->updateOrCreate(
                ['permit_type_id' => $type->id, 'code' => $item['code']],
                [
                    'label' => $item['label'],
                    'is_mandatory' => true,
                    'sort_order' => $item['sort_order'],
                    'is_active' => true,
                ],
            );
        }

        $this->upsertDocRequirement($type, $documentTypes['cse_entrant'], 'entrant');
        $this->upsertDocRequirement($type, $documentTypes['cse_standby'], 'standby');
        $this->upsertDocRequirement($type, $documentTypes['medical_fitness'], null);
        $this->upsertDocRequirement($type, $documentTypes['h2s_awareness'], null);
    }

    /**
     * @param  array<string, WorkerDocumentType>  $documentTypes
     */
    private function seedEquipmentOpeningPack(PermitType $type, array $documentTypes): void
    {
        $gasChannels = [
            ['channel_code' => 'o2_pct', 'label' => 'Oxygen', 'unit' => '%vol', 'alarm_below' => 19.5, 'alarm_above' => 23.5, 'sort_order' => 10],
            ['channel_code' => 'lel_pct', 'label' => 'LEL', 'unit' => '%LEL', 'alarm_above' => 10.0, 'sort_order' => 20],
            ['channel_code' => 'h2s_ppm', 'label' => 'H₂S', 'unit' => 'ppm', 'alarm_above' => 10.0, 'sort_order' => 30],
        ];

        foreach ($gasChannels as $channel) {
            PermitTypeGasChannel::query()->updateOrCreate(
                ['permit_type_id' => $type->id, 'channel_code' => $channel['channel_code']],
                array_merge($channel, [
                    'warn_below' => null,
                    'warn_above' => null,
                    'alarm_below' => $channel['alarm_below'] ?? null,
                    'alarm_above' => $channel['alarm_above'] ?? null,
                ]),
            );
        }

        $checklist = [
            ['code' => 'depressure', 'label' => 'System depressurized and drained', 'sort_order' => 10],
            ['code' => 'isolation', 'label' => 'Isolation points verified and locked', 'sort_order' => 20],
            ['code' => 'gas_test', 'label' => 'Atmospheric test confirms safe levels', 'sort_order' => 30],
        ];

        foreach ($checklist as $item) {
            PermitTypeChecklistItem::query()->updateOrCreate(
                ['permit_type_id' => $type->id, 'code' => $item['code']],
                [
                    'label' => $item['label'],
                    'is_mandatory' => true,
                    'sort_order' => $item['sort_order'],
                    'is_active' => true,
                ],
            );
        }

        $this->upsertDocRequirement($type, $documentTypes['loto_authorized'], null);
        $this->upsertDocRequirement($type, $documentTypes['medical_fitness'], null);
    }

    private function seedColdWorkPack(PermitType $type): void
    {
        $checklist = [
            ['code' => 'jsa_complete', 'label' => 'Job safety analysis completed', 'sort_order' => 10],
            ['code' => 'tools_inspected', 'label' => 'Tools and equipment inspected', 'sort_order' => 20],
            ['code' => 'area_barricaded', 'label' => 'Work area barricaded and signed', 'sort_order' => 30],
        ];

        foreach ($checklist as $item) {
            PermitTypeChecklistItem::query()->updateOrCreate(
                ['permit_type_id' => $type->id, 'code' => $item['code']],
                [
                    'label' => $item['label'],
                    'is_mandatory' => true,
                    'sort_order' => $item['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }

    private function upsertDocRequirement(
        PermitType $type,
        WorkerDocumentType $documentType,
        ?string $roleCode,
    ): void {
        PermitTypeDocumentRequirement::query()->updateOrCreate(
            [
                'permit_type_id' => $type->id,
                'worker_document_type_id' => $documentType->id,
                'role_code' => $roleCode,
            ],
            [
                'is_mandatory' => true,
                'must_be_verified' => true,
            ],
        );
    }
}
