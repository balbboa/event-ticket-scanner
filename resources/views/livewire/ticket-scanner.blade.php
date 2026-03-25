<div
    class="aurora-bg min-h-screen text-white"
    x-data="{
        show: false,
        _dismissTimer: null,
        init() {
            $wire.on('scan-complete', () => {
                this.show = false;
                this.$nextTick(() => {
                    this.show = true;
                    clearTimeout(this._dismissTimer);
                    this._dismissTimer = setTimeout(() => {
                        this.show = false;
                        $wire.dismissResult();
                    }, 4000);
                });
            });
        }
    }"
>
    <style>
        @keyframes shrink {
            from { width: 100%; }
            to { width: 0%; }
        }
        .progress-shrink {
            animation: shrink 4s linear forwards;
        }
    </style>

    {{-- Aurora background orbs --}}
    <div class="aurora-orb-purple w-[500px] h-[500px] -top-40 -left-40 blur-3xl"></div>
    <div class="aurora-orb-cyan w-[400px] h-[400px] -bottom-32 -right-32 blur-3xl"></div>
    <div class="aurora-orb-green w-[300px] h-[300px] top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 blur-3xl"></div>

    <div class="relative z-10 max-w-2xl mx-auto px-4 py-8">

        {{-- Header --}}
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white tracking-tight">{{ $this->event->name }}</h1>
            <p class="text-slate-400 mt-1">{{ $this->event->venue }}</p>
        </div>

        {{-- Counter Cards --}}
        <div class="grid grid-cols-3 gap-4 mb-8" wire:poll.5000ms="refreshCounters">

            {{-- Checked In --}}
            <div class="glass-card p-4">
                <p class="text-xs font-semibold uppercase tracking-widest text-purple-400 mb-1">Checked In</p>
                <p class="text-3xl font-bold text-purple-300">{{ $checkedIn }}</p>
                <p class="text-xs text-slate-500 mt-1">of {{ $totalCapacity }}</p>
                <div class="mt-3 h-1.5 bg-white/5 rounded-full overflow-hidden">
                    @php
                        $fillPct = $totalCapacity > 0 ? round($checkedIn / $totalCapacity * 100, 1) : 0;
                    @endphp
                    <div class="h-full rounded-full transition-all duration-500 bg-gradient-to-r from-purple-500 to-cyan-500" style="width: {{ $fillPct }}%"></div>
                </div>
            </div>

            {{-- Confirmed --}}
            <div class="glass-card p-4">
                <p class="text-xs font-semibold uppercase tracking-widest text-cyan-400 mb-1">Confirmed</p>
                <p class="text-3xl font-bold text-cyan-300">{{ $confirmed }}</p>
                <p class="text-xs text-slate-500 mt-1">awaiting</p>
            </div>

            {{-- Remaining --}}
            <div class="glass-card p-4">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-400 mb-1">Remaining</p>
                @php $remaining = max(0, $totalCapacity - $checkedIn - $confirmed); @endphp
                <p class="text-3xl font-bold text-slate-300">{{ $remaining }}</p>
                <p class="text-xs text-slate-500 mt-1">capacity left</p>
            </div>

        </div>

        {{-- Scan Form --}}
        <div class="glass-card p-6 mb-6">
            <label for="ticket-code" class="block text-sm font-medium text-slate-300 mb-2">Ticket Code</label>
            <div class="flex gap-3">
                <input
                    id="ticket-code"
                    wire:model="ticketCode"
                    wire:keydown.enter="scan"
                    type="text"
                    autofocus
                    placeholder="e.g. EVT-ABCD1234"
                    class="flex-1 min-w-0 font-mono bg-black/40 border border-white/8 text-white placeholder-slate-600 rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                >
                <button
                    wire:click="scan"
                    class="shrink-0 bg-gradient-to-r from-purple-600 to-cyan-600 hover:from-purple-500 hover:to-cyan-500 text-white font-semibold px-6 py-3 rounded-lg text-sm transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-purple-400"
                >
                    Scan
                </button>
            </div>
        </div>

        {{-- Result Card --}}
        @if ($showResult && $scanResult)
            @php
                $status = $scanResult['status'];
                $colorMap = [
                    'success'      => ['border' => 'border-purple-500/60', 'bg' => 'bg-purple-950/60', 'text' => 'text-purple-300', 'bar' => 'bg-gradient-to-r from-purple-500 to-cyan-500', 'badge' => 'bg-purple-500/20 text-purple-300'],
                    'already_used' => ['border' => 'border-amber-500/60',  'bg' => 'bg-amber-950/60',  'text' => 'text-amber-300',   'bar' => 'bg-amber-500',   'badge' => 'bg-amber-500/20 text-amber-300'],
                    'invalid'      => ['border' => 'border-red-500/60',    'bg' => 'bg-red-950/60',    'text' => 'text-red-300',     'bar' => 'bg-red-500',     'badge' => 'bg-red-500/20 text-red-300'],
                    'cancelled'    => ['border' => 'border-red-500/60',    'bg' => 'bg-red-950/60',    'text' => 'text-red-300',     'bar' => 'bg-red-500',     'badge' => 'bg-red-500/20 text-red-300'],
                ];
                $colors = $colorMap[$status] ?? $colorMap['invalid'];
            @endphp
            <div x-show="show" x-if="show" class="rounded-xl border {{ $colors['border'] }} {{ $colors['bg'] }} backdrop-blur-xl overflow-hidden">
                {{-- Auto-dismiss progress bar --}}
                <div class="h-1 {{ $colors['bar'] }} progress-shrink"></div>

                <div class="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1">
                            <p class="text-lg font-bold {{ $colors['text'] }} mb-1">{{ $scanResult['message'] }}</p>

                            @if ($scanResult['name'])
                                <p class="text-white font-semibold text-xl mt-2">{{ $scanResult['name'] }}</p>
                            @endif

                            @if ($scanResult['tier'])
                                <span class="inline-block mt-1 px-2 py-0.5 rounded text-xs font-medium {{ $colors['badge'] }}">
                                    {{ $scanResult['tier'] }}
                                </span>
                            @endif

                            @if (!empty($scanResult['checked_in_at']))
                                <p class="text-slate-400 text-sm mt-2">Checked in at: {{ $scanResult['checked_in_at'] }}</p>
                            @endif
                        </div>

                        <button
                            wire:click="dismissResult"
                            @click="show = false"
                            class="text-slate-500 hover:text-slate-300 transition-colors flex-shrink-0 mt-1"
                            aria-label="Dismiss"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>
