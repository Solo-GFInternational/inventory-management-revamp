<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">
            Add Inventory
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

        <form action="{{ route('inventory.store') }}" method="POST" class="mt-4">
            @csrf
            <div class="mb-4">
    <label class="block text-gray-700">Date</label>
    <input
    type="date"
    name="date"
    value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}"
    class="border rounded px-2 py-1 w-full"
    readonly
>
</div>

            <div class="mb-4">
    <label class="block text-gray-700">Item</label>
    <select name="item" class="border rounded px-2 py-1 w-full" required>
        <option value="">Select Item</option>

        <option value="Wireless Keyboard & Mouse">Wireless Keyboard & Mouse</option>
        <option value="Wired Keyboard & Mouse">Wired Keyboard & Mouse</option>
        <option value="Wireless Keyboard">Wireless Keyboard</option>
        <option value="Wireless Mouse">Wireless Mouse</option>
        <option value="Wired Keyboard">Wired Keyboard</option>
        <option value="Wired Mouse">Wired Mouse</option>
        <option value="Mouse Pad">Mouse Pad</option>
        <option value="Computer Connection">Computer Connection</option>
        <option value="Connection Adapters">Connection Adapters</option>
        <option value="Headphone">Headphone</option>
        <option value="External Drives">External Drives</option>
        <option value="Projector">Projector</option>
        <option value="Drop Down">Drop Down</option>
        <option value="Monitor">Monitor</option>
        <option value="Laptop Stand">Laptop Stand</option>
        <option value="Phone - Charger – Adapter">Phone - Charger – Adapter</option>
        <option value="Speaker">Speaker</option>
        <option value="Tablet">Tablet</option>
        <option value="WebCam">WebCam</option>
        <option value="Docking Station">Docking Station</option>
        <option value="Door Printer Card">Door Printer Card</option>
        <option value="Walkie Talkie">Walkie Talkie</option>
    </select>
</div>

            <div class="mb-2">
                <label>Quantity:</label>
                <input type="number" name="qty" class="border p-1 w-full" required>
            </div>
            <div class="mb-2">
                <label>Serial Number:</label>
                <input type="text" name="serial_number" class="border p-1 w-full">
            </div>
            <div class="mb-2">
                <label>Condition:</label>
                <select name="condition" class="border p-1 w-full" required>
                    <option value="new">New</option>
                    <option value="used">Used</option>
                </select>
            </div>
            <div class="mb-2">
                <label>Taken By:</label>
                <input type="text" name="taken_by" class="border p-1 w-full" required>
            </div>
            <div class="mb-4">
    <label class="block text-gray-700">Department</label>
    <select name="department" class="border rounded px-2 py-1 w-full" required>
        <option value="">Select Department</option>
        <option value="Access Control">Access Control</option>
        <option value="Administration">Administration</option>
        <option value="Commercial Layers">Commercial Layers</option>
        <option value="Engineering">Engineering</option>
        <option value="Finance">Finance</option>
        <option value="HR">HR</option>
        <option value="HSE">HSE</option>
        <option value="Human Resources">Human Resources</option>
        <option value="Information Technology">Information Technology</option>
        <option value="Livestock">Livestock</option>
        <option value="Logistics">Logistics</option>
        <option value="Production">Production</option>
        <option value="Projects">Projects</option>
        <option value="Purchasing">Purchasing</option>
        <option value="QA">QA</option>
        <option value="Sales">Sales</option>
        <option value="Supply Chain">Supply Chain</option>
        <option value="Veterinary">Veterinary</option>
    </select>
</div>

            <div class="mb-4">
    <label class="block text-gray-700">Site</label>
    <select name="site" class="border rounded px-2 py-1 w-full">
        <option value="">Select Site</option>
        <option value="Aiyub Farm" {{ old('site') == 'Aiyub Farm' ? 'selected' : '' }}>Aiyub Farm</option>
        <option value="Amras Farm" {{ old('site') == 'Amras Farm' ? 'selected' : '' }}>Amras Farm</option>
        <option value="B2 Farm" {{ old('site') == 'B2 Farm' ? 'selected' : '' }}>B2 Farm</option>
        <option value="B2B Farm" {{ old('site') == 'B2B Farm' ? 'selected' : '' }}>B2B Farm</option>
        <option value="CD" {{ old('site') == 'CD' ? 'selected' : '' }}>CD</option>
        <option value="Coloisuva" {{ old('site') == 'Coloisuva' ? 'selected' : '' }}>Coloisuva</option>
        <option value="Dry Goods" {{ old('site') == 'Dry Goods' ? 'selected' : '' }}>Dry Goods</option>
        <option value="Feed Mill" {{ old('site') == 'Feed Mill' ? 'selected' : '' }}>Feed Mill</option>
        <option value="FG" {{ old('site') == 'FG' ? 'selected' : '' }}>FG</option>
        <option value="Genr8 Farm" {{ old('site') == 'Genr8 Farm' ? 'selected' : '' }}>Genr8 Farm</option>
        <option value="Jaiambamma WH" {{ old('site') == 'Jaiambamma WH' ? 'selected' : '' }}>Jaiambamma WH</option>
        <option value="Korociriciri Admin" {{ old('site') == 'Korociriciri Admin' ? 'selected' : '' }}>Korociriciri Admin</option>
        <option value="Korociriciri Packhouse" {{ old('site') == 'Korociriciri Packhouse' ? 'selected' : '' }}>Korociriciri Packhouse</option>
        <option value="Lakena" {{ old('site') == 'Lakena' ? 'selected' : '' }}>Lakena</option>
        <option value="Lalita Bhindi WH" {{ old('site') == 'Lalita Bhindi WH' ? 'selected' : '' }}>Lalita Bhindi WH</option>
        <option value="LBE Container Yard" {{ old('site') == 'LBE Container Yard' ? 'selected' : '' }}>LBE Container Yard</option>
        <option value="Lautoka" {{ old('site') == 'Lautoka' ? 'selected' : '' }}>Lautoka</option>
        <option value="Lautoka WH" {{ old('site') == 'Lautoka WH' ? 'selected' : '' }}>Lautoka WH</option>
        <option value="Lohar2 Farm" {{ old('site') == 'Lohar2 Farm' ? 'selected' : '' }}>Lohar2 Farm</option>
        <option value="Lohar Farm" {{ old('site') == 'Lohar Farm' ? 'selected' : '' }}>Lohar Farm</option>
        <option value="Manoca 1 Farm" {{ old('site') == 'Manoca 1 Farm' ? 'selected' : '' }}>Manoca 1 Farm</option>
        <option value="Manoca 3 Farm" {{ old('site') == 'Manoca 3 Farm' ? 'selected' : '' }}>Manoca 3 Farm</option>
        <option value="Mahendra Farm" {{ old('site') == 'Mahendra Farm' ? 'selected' : '' }}>Mahendra Farm</option>
        <option value="Silverstone" {{ old('site') == 'Silverstone' ? 'selected' : '' }}>Silverstone</option>
        <option value="Waila" {{ old('site') == 'Waila' ? 'selected' : '' }}>Waila</option>
        <option value="Waila Container Yard" {{ old('site') == 'Waila Container Yard' ? 'selected' : '' }}>Waila Container Yard</option>
    </select>
</div>


            

            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded mt-2">
                Add Inventory
            </button>
        </form>
    </div>
</x-app-layout>
