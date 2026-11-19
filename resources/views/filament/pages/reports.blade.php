<x-filament-panels::page>
    <form wire:submit="$refresh" style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:end">
        <label>From<br><input type="date" wire:model="from" class="fi-input" style="border:1px solid #d6d3d1; border-radius:.5rem; padding:.4rem .6rem"></label>
        <label>To<br><input type="date" wire:model="to" class="fi-input" style="border:1px solid #d6d3d1; border-radius:.5rem; padding:.4rem .6rem"></label>
        <x-filament::button type="submit">Update</x-filament::button>
    </form>

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:1rem">
        @foreach ($summary as $label => $value)
            <x-filament::section compact>
                <div style="font-size:.8rem; opacity:.7">{{ $label }}</div>
                <div style="font-size:1.35rem; font-weight:600">{{ $value }}</div>
            </x-filament::section>
        @endforeach
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:1rem">
        <x-filament::section heading="Sales by channel">
            <table style="width:100%; font-size:.875rem"><thead><tr style="text-align:left"><th>Channel</th><th>Orders</th><th style="text-align:right">Total</th></tr></thead><tbody>
                @forelse ($byChannel as $row)
                    <tr><td>{{ $row->channel->label() }}</td><td>{{ $row->orders }}</td><td style="text-align:right">{{ \App\Support\Money::format((int) $row->total) }}</td></tr>
                @empty <tr><td colspan="3">No sales in this period.</td></tr> @endforelse
            </tbody></table>
        </x-filament::section>
        <x-filament::section heading="Top products">
            <table style="width:100%; font-size:.875rem"><thead><tr style="text-align:left"><th>Product</th><th>Qty</th><th style="text-align:right">Total</th></tr></thead><tbody>
                @forelse ($topProducts as $row)
                    <tr><td>{{ $row->name }} <span style="opacity:.6">{{ $row->sku }}</span></td><td>{{ $row->qty }}</td><td style="text-align:right">{{ \App\Support\Money::format((int) $row->total) }}</td></tr>
                @empty <tr><td colspan="3">No sales in this period.</td></tr> @endforelse
            </tbody></table>
        </x-filament::section>
        <x-filament::section heading="Affiliate performance">
            <table style="width:100%; font-size:.875rem"><thead><tr style="text-align:left"><th>Affiliate</th><th>Orders</th><th style="text-align:right">Earned</th><th style="text-align:right">Adjustments</th></tr></thead><tbody>
                @forelse ($affiliates as $row)
                    <tr><td>{{ $row->affiliate?->code }}</td><td>{{ $row->orders }}</td><td style="text-align:right">{{ \App\Support\Money::format((int) $row->earned) }}</td><td style="text-align:right">{{ \App\Support\Money::format((int) $row->adjustments) }}</td></tr>
                @empty <tr><td colspan="4">No affiliate commission in this period.</td></tr> @endforelse
            </tbody></table>
        </x-filament::section>
    </div>
</x-filament-panels::page>
