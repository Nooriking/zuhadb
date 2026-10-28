{{--
    Person × period pivot. $matrix = ['periods' => [...], 'rows' => [...], 'totals' => [...]]
    $nameLabel: first column header
--}}
@php
    $periods = $matrix['periods'];
    $compact = count($periods) > 6;
    $fmt = function ($v) use ($compact) {
        $v = (float) $v;
        if ($v == 0) {
            return '—';
        }
        if ($compact && abs($v) >= 1000000) {
            return number_format($v / 1000000, 2) . 'M';
        }
        if ($compact && abs($v) >= 10000) {
            return number_format($v / 1000, 1) . 'K';
        }

        return number_format($v, 0);
    };
@endphp

@if (count($matrix['rows']) === 0)
    <div class="no-data">No data for this period</div>
@else
    <table class="tbl compact">
        <thead>
            <tr>
                <th style="width: 18%;">{{ $nameLabel }}</th>
                @foreach ($periods as $p)
                    <th class="r">{{ $p }}</th>
                @endforeach
                <th class="r" style="width: 10%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($matrix['rows'] as $row)
                <tr>
                    <td class="b">{{ $row['name'] }}</td>
                    @foreach ($periods as $p)
                        <td class="r">{{ $fmt($row['values'][$p] ?? 0) }}</td>
                    @endforeach
                    <td class="r b">{{ $fmt($row['total']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>TOTAL</td>
                @foreach ($periods as $p)
                    <td class="r">{{ $fmt($matrix['totals'][$p] ?? 0) }}</td>
                @endforeach
                <td class="r">{{ $fmt(array_sum($matrix['totals'])) }}</td>
            </tr>
        </tbody>
    </table>
    @if ($compact)
        <div class="small" style="color: #94a3b8; font-size: 6.5px;">K = thousand, M = million AFN</div>
    @endif
@endif
