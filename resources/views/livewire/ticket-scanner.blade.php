<div
    class="aurora-bg min-h-screen text-white"
    x-data="{
        show: false,
        lookupOpen: false,
        _dismissTimer: null,
        qrScanner: null,
        scanCooldown: false,
        cameraError: false,
        viewfinderState: 'scanning',
        init() {
            this.startCamera()
            $wire.on('scan-complete', (event) => {
                this.show = false;
                this.$nextTick(() => {
                    this.show = true;
                    clearTimeout(this._dismissTimer);
                    this._dismissTimer = setTimeout(() => {
                        this.show = false;
                        $wire.dismissResult();
                    }, 4000);

                    const status = event[0]?.status ?? event?.status ?? '';

                    // Drive viewfinder state
                    this.viewfinderState = status === 'success' ? 'success' : 'error'

                    // Independent 1.5s cooldown — releases scanner for next ticket
                    setTimeout(() => {
                        this.scanCooldown = false
                        this.viewfinderState = 'scanning'
                    }, 1500)

                    if (status === 'success') {
                        this.playSuccess();
                    } else {
                        this.playError();
                    }
                });
            });
        },
        startCamera() {
            if (!window.Html5Qrcode) return
            this.qrScanner = new window.Html5Qrcode('qr-reader')
            this.qrScanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 220, height: 220 } },
                (decodedText) => {
                    if (this.scanCooldown) return
                    this.scanCooldown = true
                    $wire.scan(decodedText)
                }
            ).catch(() => {
                this.cameraError = true
                this.viewfinderState = 'denied'
            })
        },
        stopCamera() {
            this.qrScanner?.stop().catch(() => {})
        },
        destroy() {
            this.stopCamera()
        },
        playSuccess() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                [440, 660].forEach((freq, i) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.frequency.value = freq;
                    osc.type = 'sine';
                    gain.gain.setValueAtTime(0.18, ctx.currentTime + i * 0.12);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.12 + 0.18);
                    osc.start(ctx.currentTime + i * 0.12);
                    osc.stop(ctx.currentTime + i * 0.12 + 0.18);
                });
            } catch(e) {}
        },
        playError() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                [180, 160].forEach((freq, i) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.frequency.value = freq;
                    osc.type = 'sawtooth';
                    gain.gain.setValueAtTime(0.15, ctx.currentTime + i * 0.15);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.15 + 0.12);
                    osc.start(ctx.currentTime + i * 0.15);
                    osc.stop(ctx.currentTime + i * 0.15 + 0.12);
                });
            } catch(e) {}
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

        /* Hide html5-qrcode injected chrome */
        #qr-reader__dashboard      { display: none !important; }
        #qr-reader__status_span    { display: none !important; }
        #qr-reader video           { border-radius: 12px; width: 100% !important; }

        /* Viewfinder scan line animation */
        @keyframes scanline {
            0%   { top: 15%; }
            50%  { top: 80%; }
            100% { top: 15%; }
        }
    </style>

    {{-- Aurora background orbs --}}
    <div class="aurora-orb-purple w-[500px] h-[500px] -top-40 -left-40 blur-3xl"></div>
    <div class="aurora-orb-cyan w-[400px] h-[400px] -bottom-32 -right-32 blur-3xl"></div>
    <div class="aurora-orb-green w-[300px] h-[300px] top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 blur-3xl"></div>

    {{-- Top Navigation Bar --}}
    <div class="relative z-10 border-b border-white/8 bg-black/20 backdrop-blur-md">
        <div class="max-w-2xl mx-auto px-4 h-14 flex items-center justify-between gap-4">
            <a
                href="{{ url('/admin/events') }}"
                class="flex items-center gap-2 text-sm text-slate-400 hover:text-white transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Events
            </a>

            <div class="flex items-center gap-3">
                <span class="text-sm text-slate-400">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('filament.admin.auth.logout') }}">
                    @csrf
                    <button type="submit" class="text-xs text-slate-500 hover:text-slate-300 transition-colors px-2 py-1 rounded border border-white/8 hover:border-white/20">
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="relative z-10 max-w-2xl mx-auto px-4 py-8">

        {{-- Header --}}
        <div class="mb-8">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-white tracking-tight">{{ $this->event->name }}</h1>
                    <p class="text-slate-400 mt-1">{{ $this->event->venue }}</p>
                    @if ($this->event->starts_at)
                        <p class="text-slate-500 text-sm mt-0.5">
                            {{ $this->event->starts_at->format('F j, Y · g:i A') }}
                            @if ($this->event->ends_at)
                                <span class="text-slate-600">–</span> {{ $this->event->ends_at->format('g:i A') }}
                            @endif
                        </p>
                    @endif
                </div>
                @php
                    $statusColors = [
                        'published' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
                        'draft'     => 'bg-amber-500/15 text-amber-400 border-amber-500/30',
                        'cancelled' => 'bg-red-500/15 text-red-400 border-red-500/30',
                    ];
                    $sc = $statusColors[$this->event->status] ?? 'bg-slate-500/15 text-slate-400 border-slate-500/30';
                @endphp
                <span class="mt-1 shrink-0 inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold uppercase tracking-wider border {{ $sc }}">
                    {{ $this->event->status }}
                </span>
            </div>
        </div>

        {{-- Camera Viewfinder --}}
        <div class="glass-card mb-8 overflow-hidden relative"
             style="min-height:220px"
             :style="viewfinderState === 'scanning' ? 'border:1.5px solid rgba(139,92,246,0.6);min-height:220px' : 'min-height:220px'">

            {{-- Normal scanning state --}}
            <div x-show="!cameraError" class="relative w-full" style="min-height:220px">
                <div id="qr-reader" class="w-full rounded-xl" style="min-height:220px;position:relative"></div>

                {{-- Custom overlay (corners, scan line, status) --}}
                <div class="absolute inset-0 pointer-events-none" x-show="viewfinderState === 'scanning'">
                    {{-- Corner brackets --}}
                    <div class="absolute top-3 left-3 w-5 h-5 border-t-2 border-l-2 border-purple-500 rounded-tl"></div>
                    <div class="absolute top-3 right-3 w-5 h-5 border-t-2 border-r-2 border-purple-500 rounded-tr"></div>
                    <div class="absolute bottom-3 left-3 w-5 h-5 border-b-2 border-l-2 border-purple-500 rounded-bl"></div>
                    <div class="absolute bottom-3 right-3 w-5 h-5 border-b-2 border-r-2 border-purple-500 rounded-br"></div>
                    {{-- Live dot --}}
                    <div class="absolute top-3 right-10 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400" style="box-shadow:0 0 6px #34d399"></span>
                        <span class="text-emerald-400 text-xs font-medium">Live</span>
                    </div>
                    {{-- Scan line --}}
                    <div class="absolute left-4 right-4" style="height:1.5px;background:linear-gradient(90deg,transparent,rgba(139,92,246,0.8),transparent);animation:scanline 2s linear infinite"></div>
                    {{-- Hint --}}
                    <div class="absolute bottom-3 left-0 right-0 text-center text-purple-400 text-xs">Point camera at QR code</div>
                </div>

                {{-- Success overlay --}}
                <div x-show="viewfinderState === 'success'" class="absolute inset-0 flex flex-col items-center justify-center gap-2 rounded-xl overflow-hidden" style="background:rgba(5,46,22,0.85);border:2px solid rgba(16,185,129,0.8);box-shadow:0 0 24px rgba(16,185,129,0.3)">
                    <span class="text-3xl">✅</span>
                    <span class="text-emerald-400 text-sm font-semibold">Ticket accepted</span>
                    {{-- 1.5s cooldown progress bar --}}
                    <div class="absolute bottom-0 left-0 right-0 h-1" style="background:rgba(255,255,255,0.08)">
                        <div class="h-full" style="background:linear-gradient(90deg,#6ee7b7,#06b6d4);animation:shrink 1.5s linear forwards"></div>
                    </div>
                </div>

                {{-- Error overlay --}}
                <div x-show="viewfinderState === 'error'" class="absolute inset-0 flex flex-col items-center justify-center gap-2 rounded-xl" style="background:rgba(69,10,10,0.85);border:2px solid rgba(239,68,68,0.8);box-shadow:0 0 24px rgba(239,68,68,0.25)">
                    <span class="text-3xl">✗</span>
                    <span class="text-red-400 text-sm font-semibold"
                          x-text="$wire.scanResult?.status === 'already_used' ? 'Already used' : ($wire.scanResult?.status === 'cancelled' ? 'Cancelled' : 'Invalid ticket')"></span>
                </div>
            </div>

            {{-- Camera denied / error fallback --}}
            <div x-show="cameraError" class="flex flex-col items-center justify-center gap-2 rounded-xl" style="min-height:100px;border:2px dashed rgba(239,68,68,0.4);background:rgba(239,68,68,0.06)">
                <span class="text-2xl">🚫</span>
                <span class="text-red-400 text-sm font-semibold">Camera access denied</span>
                <span class="text-slate-500 text-xs">Use manual entry below</span>
            </div>

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
        <div class="glass-card p-6 mb-4">
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

        {{-- Manual Lookup Toggle --}}
        <div class="mb-6" x-data>
            <button
                @click="$data.lookupOpen = !$data.lookupOpen"
                x-bind:@click="lookupOpen = !lookupOpen"
                @click="lookupOpen = !lookupOpen"
                class="flex items-center gap-2 text-sm text-slate-500 hover:text-slate-300 transition-colors"
            >
                <svg class="w-4 h-4 transition-transform duration-200" :class="lookupOpen ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                Manual attendee lookup
            </button>

            <div x-show="lookupOpen" x-transition class="mt-3 glass-card p-5">
                <div class="relative mb-4">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input
                        wire:model.live.debounce.300ms="searchQuery"
                        type="text"
                        placeholder="Search by name or email…"
                        class="w-full pl-10 pr-4 py-2.5 bg-black/40 border border-white/8 text-white placeholder-slate-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    >
                </div>

                @if (count($searchResults) > 0)
                    <div class="space-y-2">
                        @foreach ($searchResults as $result)
                            @php
                                $sBadge = match($result['status']) {
                                    'confirmed' => 'bg-cyan-500/15 text-cyan-400',
                                    'checked_in' => 'bg-purple-500/15 text-purple-400',
                                    'cancelled' => 'bg-red-500/15 text-red-400',
                                    default => 'bg-slate-500/15 text-slate-400',
                                };
                            @endphp
                            <div class="flex items-center justify-between gap-3 px-4 py-3 rounded-lg bg-white/4 border border-white/6">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-white truncate">{{ $result['name'] }}</p>
                                    <p class="text-xs text-slate-500 truncate">{{ $result['email'] }} · <span class="font-mono">{{ $result['ticket_code'] }}</span></p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="text-xs px-2 py-0.5 rounded font-medium {{ $sBadge }}">{{ $result['tier'] }}</span>
                                    @if ($result['status'] === 'confirmed')
                                        <button
                                            wire:click="checkInAttendee({{ $result['id'] }})"
                                            class="text-xs bg-gradient-to-r from-purple-600 to-cyan-600 hover:from-purple-500 hover:to-cyan-500 text-white font-semibold px-3 py-1.5 rounded-lg transition-all duration-150"
                                        >
                                            Check In
                                        </button>
                                    @else
                                        <span class="text-xs px-2 py-1 rounded {{ $sBadge }}">{{ $result['status'] }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @elseif (strlen($searchQuery) >= 2)
                    <p class="text-sm text-slate-500 text-center py-4">No attendees found for "{{ $searchQuery }}"</p>
                @endif
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
            <div x-show="show" x-if="show" class="rounded-xl border {{ $colors['border'] }} {{ $colors['bg'] }} backdrop-blur-xl overflow-hidden mb-6">
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

        {{-- Recent Scans Feed --}}
        @if (count($recentScans) > 0)
            <div class="glass-card p-5">
                <h2 class="text-xs font-semibold uppercase tracking-widest text-slate-500 mb-4">Recent Scans</h2>
                <div class="space-y-2">
                    @foreach ($recentScans as $scan)
                        @php
                            $scanBadge = match($scan['status']) {
                                'success'      => ['dot' => 'bg-purple-400', 'text' => 'text-purple-400', 'label' => 'Check-in'],
                                'already_used' => ['dot' => 'bg-amber-400',  'text' => 'text-amber-400',  'label' => 'Already used'],
                                'invalid'      => ['dot' => 'bg-red-400',    'text' => 'text-red-400',    'label' => 'Invalid'],
                                'cancelled'    => ['dot' => 'bg-red-400',    'text' => 'text-red-400',    'label' => 'Cancelled'],
                                default        => ['dot' => 'bg-slate-400',  'text' => 'text-slate-400',  'label' => $scan['status']],
                            };
                        @endphp
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="w-2 h-2 rounded-full shrink-0 {{ $scanBadge['dot'] }}"></span>
                                <span class="text-white truncate">{{ $scan['name'] ?? $scan['ticket_code'] }}</span>
                                @if ($scan['name'])
                                    <span class="text-slate-600 font-mono text-xs truncate">{{ $scan['ticket_code'] }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <span class="text-xs font-medium {{ $scanBadge['text'] }}">{{ $scanBadge['label'] }}</span>
                                <span class="text-slate-600 text-xs font-mono">{{ $scan['time'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</div>
