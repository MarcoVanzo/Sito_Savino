<x-filament-widgets::widget>
    <x-filament::section heading="Top 10 Prodotti">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400">#</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400">Prodotto</th>
                    <th class="text-right py-2 px-3 font-medium text-gray-500 dark:text-gray-400">Venduti</th>
                    <th class="text-right py-2 px-3 font-medium text-gray-500 dark:text-gray-400">Fatturato</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->getTopProducts() as $index => $product)
                    <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                        <td class="py-2 px-3 text-gray-400 dark:text-gray-500 font-mono">{{ $index + 1 }}</td>
                        <td class="py-2 px-3 font-medium text-gray-950 dark:text-white">{{ $product['name'] }}</td>
                        <td class="py-2 px-3 text-right text-gray-600 dark:text-gray-300">{{ $product['qty'] }}</td>
                        <td class="py-2 px-3 text-right font-semibold text-gray-950 dark:text-white">€{{ $product['revenue'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-6 text-center text-gray-400 dark:text-gray-500">
                            Nessun dato disponibile
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-widgets::widget>
