<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\Stock;
use App\Models\Type;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local demo data. Safe to re-run: users and stock are matched by
 * email / item name, and sample inventory is only added to an empty table.
 * Needs TypeSeeder first (departments and sites).
 *
 * Sample inventory is inserted directly (not through StockLedger), so the
 * stock counts below are what is on the shelf after these issues.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@demo.test'],
            ['name' => 'Demo Admin', 'password' => Hash::make('password'), 'role' => 'admin']
        );

        $user = User::updateOrCreate(
            ['email' => 'user@demo.test'],
            ['name' => 'Demo User', 'password' => Hash::make('password'), 'role' => 'user']
        );

        $stock = [
            'Wireless Mouse' => 15,
            'Wireless Keyboard & Mouse' => 8,
            'WebCam' => 5,
            'Connection Adapters' => 20,
            'Laptop Bag' => 6,
            'Headphone' => 3,
            'Printer' => 2,
        ];

        foreach ($stock as $item => $qty) {
            Stock::updateOrCreate(['item' => $item], ['qty' => $qty]);
        }

        if (Inventory::exists()) {
            return;
        }

        $stockId = fn (string $item) => Stock::where('item', $item)->value('id');
        $typeId = fn (string $group, string $name) => Type::group($group)->where('type_name', $name)->value('id');

        $rows = [
            ['Laptop Bag', 'BAG-0001', 'Alex Example', 'Finance', 'Dry Goods', $admin, 'approved'],
            ['Wireless Mouse', 'MSE-0042', 'Sam Example', 'Information Technology', 'Dry Goods', $admin, 'approved'],
            ['Printer', 'PRN-0007', 'Jordan Example', 'Engineering', 'Feed Mill', $admin, 'approved'],
            ['Headphone', 'HDP-0003', 'Taylor Example', 'Sales', 'Lautoka', $user, 'pending'],
        ];

        foreach ($rows as [$item, $serial, $takenBy, $department, $site, $givenBy, $status]) {
            Inventory::create([
                'date' => now()->toDateString(),
                'stock_id' => $stockId($item),
                'qty' => 1,
                'serial_number' => $serial,
                'condition' => 'new',
                'taken_by' => $takenBy,
                'department_id' => $typeId(Type::DEPARTMENT, $department),
                'site_id' => $typeId(Type::SITE, $site),
                'given_by' => $givenBy->id,
                'status' => $status,
                'approval_type' => $status === 'pending' ? 'new' : null,
            ]);
        }
    }
}
