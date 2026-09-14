<tr class="cr-row {{ $s->status === 'rejected' ? 'reject' : '' }}" x-data="{ row: { id: {{ $s->id }}, status: @js($s->status), watch: {{ $s->watch ? 'true' : 'false' }} } }">
    <td>
        <strong>{{ $s->symbol }}</strong>
        <small class="text-muted ml-1">{{ strtoupper($s->market_type) }}·{{ strtoupper($s->exchange) }}</small>
    </td>
    <td>
        <span class="badge {{ in_array($s->direction, ['long', 'buy'], true) ? 'badge-success' : 'badge-danger' }}">
            {{ strtoupper($s->direction) }}
        </span>
    </td>
    <td>
        <span class="score-badge {{ $s->score_quality }}">{{ $s->signal_score }}</span>
    </td>
    <td class="small">{{ rtrim(rtrim(number_format((float) $s->ai_probability, 2, '.', ''), '0'), '.') }}%</td>
    <td>{{ $s->risk_reward }}</td>
    <td class="small">${{ rtrim(number_format((float) $s->entry_price, 8, '.', ''), '0') }}</td>
    <td class="small">
        @if($s->isExpired())
            <span class="badge badge-secondary">expired</span>
        @elseif($s->status === 'rejected')
            <span class="badge badge-light">rejected</span>
        @elseif($s->status === 'canceled')
            <span class="badge badge-secondary">canceled</span>
        @elseif($s->status === 'executed')
            <span class="badge badge-success">executed</span>
        @elseif($s->watch)
            <span class="badge badge-warning">watching</span>
        @else
            <span class="badge badge-primary">{{ $s->status }}</span>
        @endif
    </td>
    <td class="small text-muted" x-text="window.timeago ? window.timeago('{{ optional($s->expires_at)->toIso8601String() }}') : '–'">–</td>
    <td class="text-right" style="white-space:nowrap;">
        <button class="btn btn-xs btn-outline-secondary" @click="$dispatch('open-signal', {{ $s->id }})" title="Analyze"><i class="fas fa-search"></i></button>
        @if(in_array($s->status, ['qualified', 'watching', 'entry_pending'], true))
            <a href="{{ route('scanner.signal.confirm', $s) }}" class="btn btn-xs btn-outline-success" title="Trade"><i class="fas fa-rocket"></i></a>
        @endif
        @if($s->isActive() && $s->status !== 'canceled')
            @if($s->watch)
                <form method="POST" action="{{ route('scanner.signal.unwatch', $s) }}" class="d-inline" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    <button type="submit" class="btn btn-xs btn-outline-warning" title="Unwatch" :disabled="submitting"><i class="fas fa-eye-slash"></i></button>
                </form>
            @else
                <form method="POST" action="{{ route('scanner.signal.watch', $s) }}" class="d-inline" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    <button type="submit" class="btn btn-xs btn-outline-info" title="Watch" :disabled="submitting"><i class="fas fa-eye"></i></button>
                </form>
            @endif
            <form method="POST" action="{{ route('scanner.signal.dismiss', $s) }}" class="d-inline" x-data="{ submitting: false }" @submit="submitting = true">
                @csrf
                <button type="submit" class="btn btn-xs btn-outline-danger" title="Dismiss" :disabled="submitting"><i class="fas fa-ban"></i></button>
            </form>
        @endif
    </td>
</tr>