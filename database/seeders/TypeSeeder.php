<?php

namespace Database\Seeders;

use App\Models\Type;
use Illuminate\Database\Seeder;

/**
 * The managed dropdown lists in ref.types. Idempotent: an existing
 * (type_group, type_name) row is left alone, so re-running never undoes
 * changes made under Settings > Types.
 */
class TypeSeeder extends Seeder
{
    public function run(): void
    {
        $lists = [
            Type::DEPARTMENT => [
                'Access Control',
                'Administration',
                'Commercial Layers',
                'Engineering',
                'Finance',
                'HR',
                'HSE',
                'Human Resources',
                'Information Technology',
                'Livestock',
                'Logistics',
                'Production',
                'Projects',
                'Purchasing',
                'QA',
                'Sales',
                'Supply Chain',
                'Veterinary',
            ],
            Type::SITE => [
                'Aiyub Farm',
                'Amras Farm',
                'B2 Farm',
                'B2B Farm',
                'CD',
                'Coloisuva',
                'Dry Goods',
                'Feed Mill',
                'FG',
                'Genr8 Farm',
                'Jaiambamma WH',
                'Korociriciri Admin',
                'Korociriciri Packhouse',
                'Lakena',
                'Lalita Bhindi WH',
                'LBE Container Yard',
                'Lautoka',
                'Lautoka WH',
                'Lohar2 Farm',
                'Lohar Farm',
                'Manoca 1 Farm',
                'Manoca 3 Farm',
                'Mahendra Farm',
                'Silverstone',
                'Waila',
                'Waila Container Yard',
            ],
        ];

        foreach ($lists as $group => $names) {
            foreach (array_values($names) as $order => $name) {
                Type::firstOrCreate(
                    ['type_group' => $group, 'type_name' => $name],
                    ['sort_order' => ($order + 1) * 10],
                );
            }
        }
    }
}
