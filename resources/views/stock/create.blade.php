<x-app-layout>
    <x-slot name="header">Add Stock</x-slot>

    <div class="p-6 max-w-md mx-auto bg-white rounded shadow">
        <form method="POST" action="{{ route('stock.store') }}">
            @csrf

            <div class="mb-4">
                
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

            </div>

            <div class="mb-4">
                <label class="block">Quantity</label>
                <input type="number" name="qty" class="border w-full px-2 py-1" required>
            </div>

            <button class="bg-blue-500 text-white px-4 py-2 rounded mt-2">
                Save
            </button>
        </form>
    </div>
</x-app-layout>
