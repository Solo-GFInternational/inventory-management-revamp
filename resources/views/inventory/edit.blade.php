<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">
            Edit Inventory
        </h2>
    </x-slot>

    <div class="p-6">
        <a href="{{ route('inventory.index') }}"
           class="bg-gray-500 text-white px-4 py-2 rounded">
            Back to List
        </a>

        @if($errors->any())
            <div class="mt-4 text-red-500">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('inventory.update', $inventory->id) }}" method="POST" class="mt-4">
            @csrf
            @method('PUT')
            <div class="mb-2">
                <label>Date:</label>
                <input type="date" name="date" class="border p-1 w-full" value="{{ $inventory->date }}" required>
            </div>
            <div class="mb-4">
    <label class="block text-gray-700">Item</label>
    <select name="item" class="border rounded px-2 py-1 w-full" required>
        <option value="">Select Item</option>

        @php
            $items = [
                'Wireless Keyboard & Mouse',
                'Wired Keyboard & Mouse',
                'Wireless Keyboard',
                'Wireless Mouse',
                'Wired Keyboard',
                'Wired Mouse',
                'Mouse Pad',
                'Computer Connection',
                'Connection Adapters',
                'Headphone',
                'External Drives',
                'Projector',
                'Drop Down',
                'Monitor',
                'Laptop Stand',
                'Phone - Charger – Adapter',
                'Speaker',
                'Tablet',
                'WebCam',
                'Docking Station',
                'Door Printer Card',
                'Walkie Talkie',
            ];
        @endphp

        @foreach($items as $item)
            <option value="{{ $item }}" {{ $inventory->item == $item ? 'selected' : '' }}>
                {{ $item }}
            </option>
        @endforeach
    </select>
</div>

            <div class="mb-2">
                <label>Quantity:</label>
                <input type="number" name="qty" class="border p-1 w-full" value="{{ $inventory->qty }}" required>
            </div>
            <div class="mb-2">
                <label>Serial Number:</label>
                <input type="text" name="serial_number" class="border p-1 w-full" value="{{ $inventory->serial_number }}">
            </div>
            <div class="mb-2">
                <label>Condition:</label>
                <select name="condition" class="border p-1 w-full" required>
                    <option value="new" {{ $inventory->condition == 'new' ? 'selected' : '' }}>New</option>
                    <option value="used" {{ $inventory->condition == 'used' ? 'selected' : '' }}>Used</option>
                </select>
            </div>
            <div class="mb-2">
                <label>Taken By:</label>
                <input type="text" name="taken_by" class="border p-1 w-full" value="{{ $inventory->taken_by }}" required>
            </div>
            <div class="mb-4">
    <label class="block text-gray-700">Department</label>
    <select name="department" class="border rounded px-2 py-1 w-full" required>
        <option value="">Select Department</option>
        <option value="Access Control" {{ $inventory->department == 'Access Control' ? 'selected' : '' }}>Access Control</option>
        <option value="Administration" {{ $inventory->department == 'Administration' ? 'selected' : '' }}>Administration</option>
        <option value="Commercial Layers" {{ $inventory->department == 'Commercial Layers' ? 'selected' : '' }}>Commercial Layers</option>
        <option value="Engineering" {{ $inventory->department == 'Engineering' ? 'selected' : '' }}>Engineering</option>
        <option value="Finance" {{ $inventory->department == 'Finance' ? 'selected' : '' }}>Finance</option>
        <option value="HR" {{ $inventory->department == 'HR' ? 'selected' : '' }}>HR</option>
        <option value="HSE" {{ $inventory->department == 'HSE' ? 'selected' : '' }}>HSE</option>
        <option value="Human Resources" {{ $inventory->department == 'Human Resources' ? 'selected' : '' }}>Human Resources</option>
        <option value="Information Technology" {{ $inventory->department == 'Information Technology' ? 'selected' : '' }}>Information Technology</option>
        <option value="Livestock" {{ $inventory->department == 'Livestock' ? 'selected' : '' }}>Livestock</option>
        <option value="Logistics" {{ $inventory->department == 'Logistics' ? 'selected' : '' }}>Logistics</option>
        <option value="Production" {{ $inventory->department == 'Production' ? 'selected' : '' }}>Production</option>
        <option value="Projects" {{ $inventory->department == 'Projects' ? 'selected' : '' }}>Projects</option>
        <option value="Purchasing" {{ $inventory->department == 'Purchasing' ? 'selected' : '' }}>Purchasing</option>
        <option value="QA" {{ $inventory->department == 'QA' ? 'selected' : '' }}>QA</option>
        <option value="Sales" {{ $inventory->department == 'Sales' ? 'selected' : '' }}>Sales</option>
        <option value="Supply Chain" {{ $inventory->department == 'Supply Chain' ? 'selected' : '' }}>Supply Chain</option>
        <option value="Veterinary" {{ $inventory->department == 'Veterinary' ? 'selected' : '' }}>Veterinary</option>
    </select>
</div>

            <div class="mb-4">
    <label class="block text-gray-700">Site</label>
    <select name="site" class="border rounded px-2 py-1 w-full">
        <option value="">Select Site</option>
        <option value="Aiyub Farm" {{ $inventory->site == 'Aiyub Farm' ? 'selected' : '' }}>Aiyub Farm</option>
        <option value="Amras Farm" {{ $inventory->site == 'Amras Farm' ? 'selected' : '' }}>Amras Farm</option>
        <option value="B2 Farm" {{ $inventory->site == 'B2 Farm' ? 'selected' : '' }}>B2 Farm</option>
        <option value="B2B Farm" {{ $inventory->site == 'B2B Farm' ? 'selected' : '' }}>B2B Farm</option>
        <option value="CD" {{ $inventory->site == 'CD' ? 'selected' : '' }}>CD</option>
        <option value="Coloisuva" {{ $inventory->site == 'Coloisuva' ? 'selected' : '' }}>Coloisuva</option>
        <option value="Dry Goods" {{ $inventory->site == 'Dry Goods' ? 'selected' : '' }}>Dry Goods</option>
        <option value="Feed Mill" {{ $inventory->site == 'Feed Mill' ? 'selected' : '' }}>Feed Mill</option>
        <option value="FG" {{ $inventory->site == 'FG' ? 'selected' : '' }}>FG</option>
        <option value="Genr8 Farm" {{ $inventory->site == 'Genr8 Farm' ? 'selected' : '' }}>Genr8 Farm</option>
        <option value="Jaiambamma WH" {{ $inventory->site == 'Jaiambamma WH' ? 'selected' : '' }}>Jaiambamma WH</option>
        <option value="Korociriciri Admin" {{ $inventory->site == 'Korociriciri Admin' ? 'selected' : '' }}>Korociriciri Admin</option>
        <option value="Korociriciri Packhouse" {{ $inventory->site == 'Korociriciri Packhouse' ? 'selected' : '' }}>Korociriciri Packhouse</option>
        <option value="Lakena" {{ $inventory->site == 'Lakena' ? 'selected' : '' }}>Lakena</option>
        <option value="Lalita Bhindi WH" {{ $inventory->site == 'Lalita Bhindi WH' ? 'selected' : '' }}>Lalita Bhindi WH</option>
        <option value="LBE Container Yard" {{ $inventory->site == 'LBE Container Yard' ? 'selected' : '' }}>LBE Container Yard</option>
        <option value="Lautoka" {{ $inventory->site == 'Lautoka' ? 'selected' : '' }}>Lautoka</option>
        <option value="Lautoka WH" {{ $inventory->site == 'Lautoka WH' ? 'selected' : '' }}>Lautoka WH</option>
        <option value="Lohar2 Farm" {{ $inventory->site == 'Lohar2 Farm' ? 'selected' : '' }}>Lohar2 Farm</option>
        <option value="Lohar Farm" {{ $inventory->site == 'Lohar Farm' ? 'selected' : '' }}>Lohar Farm</option>
        <option value="Manoca 1 Farm" {{ $inventory->site == 'Manoca 1 Farm' ? 'selected' : '' }}>Manoca 1 Farm</option>
        <option value="Manoca 3 Farm" {{ $inventory->site == 'Manoca 3 Farm' ? 'selected' : '' }}>Manoca 3 Farm</option>
        <option value="Mahendra Farm" {{ $inventory->site == 'Mahendra Farm' ? 'selected' : '' }}>Mahendra Farm</option>
        <option value="Silverstone" {{ $inventory->site == 'Silverstone' ? 'selected' : '' }}>Silverstone</option>
        <option value="Waila" {{ $inventory->site == 'Waila' ? 'selected' : '' }}>Waila</option>
        <option value="Waila Container Yard" {{ $inventory->site == 'Waila Container Yard' ? 'selected' : '' }}>Waila Container Yard</option>
    </select>
</div>


            

            <button type="submit" class="text-blue-500 hover:underline px-2 py-1 border rounded">
                Update Inventory
            </button>
        </form>
    </div>
</x-app-layout>
