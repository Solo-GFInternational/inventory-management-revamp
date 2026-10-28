<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local demo data. Safe to re-run: users and stock are matched by
 * email / item name, and sample inventory is only added to an empty table.
 */
class DemoSeeder extends Seeder
{
    public function run()
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

        $rows = [
            ['item' => 'Laptop Bag', 'serial_number' => 'BAG-0001', 'taken_by' => 'Alex Example', 'department' => 'Finance', 'site' => 'Dry Goods', 'given_by' => $admin->id, 'status' => 'approved', 'approval_type' => null],
            ['item' => 'Wireless Mouse', 'serial_number' => 'MSE-0042', 'taken_by' => 'Sam Example', 'department' => 'Information Technology', 'site' => 'Dry Goods', 'given_by' => $admin->id, 'status' => 'approved', 'approval_type' => null],
            ['item' => 'Printer', 'serial_number' => 'PRN-0007', 'taken_by' => 'Jordan Example', 'department' => 'Engineering', 'site' => 'Feed Mill', 'given_by' => $admin->id, 'status' => 'approved', 'approval_type' => null],
            ['item' => 'Headphone', 'serial_number' => 'HDP-0003', 'taken_by' => 'Taylor Example', 'department' => 'Exports', 'site' => 'Dry Goods', 'given_by' => $user->id, 'status' => 'pending', 'approval_type' => 'new'],
        ];

        foreach ($rows as $row) {
            Inventory::create($row + ['date' => now()->toDateString(), 'qty' => 1, 'condition' => 'new']);
        }
    }
}
