{{-- $items: list of ['k' => label, 'v' => value, 's' => sub text, 'tone' => blue|green|red|violet|amber] --}}
<table class="kpis">
    @foreach (array_chunk($items, 4) as $row)
        <tr>
            @foreach ($row as $item)
                <td class="kpi {{ $item['tone'] ?? '' }}">
                    <div class="k">{{ $item['k'] }}</div>
                    <div class="v">{{ $item['v'] }}</div>
                    @if (! empty($item['s']))
                        <div class="s">{{ $item['s'] }}</div>
                    @endif
                </td>
            @endforeach
            @for ($i = count($row); $i < 4; $i++)
                <td class="kpi empty"></td>
            @endfor
        </tr>
    @endforeach
</table>
