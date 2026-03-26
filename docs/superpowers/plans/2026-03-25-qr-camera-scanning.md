# QR Camera Scanning Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add automatic camera-based QR code scanning to the scanner page so staff can check in attendees by pointing a phone at a ticket — zero taps required.

**Architecture:** Install `html5-qrcode` as a devDependency, expose it via `window` in `app.js`, then integrate it into the existing Alpine.js `x-data` object on the scanner blade. The camera decodes QR strings client-side and calls a slightly updated `scan(?string $scannedCode)` Livewire action directly — bypassing the `$wire.set()` + `$wire.scan()` race condition. A `scanCooldown` flag (locked on decode, released after `scan-complete` + 1.5s) prevents double-scanning.

**Tech Stack:** Laravel 13, Livewire 3, Alpine.js (via Livewire), html5-qrcode ^2.3.8, Vite, Tailwind CSS v4, PHPUnit

---

## File Map

| File | Action | What changes |
|------|--------|-------------|
| `package.json` | Modify | Add `html5-qrcode` to `devDependencies` |
| `resources/js/app.js` | Modify | Import `Html5Qrcode`, expose on `window` |
| `resources/views/livewire/ticket-scanner.blade.php` | Modify | Add viewfinder `<div>`, camera Alpine state + lifecycle, viewfinder overlay HTML, CSS overrides for library chrome |
| `app/Livewire/TicketScanner.php` | Modify | Add optional `?string $scannedCode` param to `scan()` |
| `tests/Feature/TicketScannerTest.php` | Modify | Add test for camera-initiated scan (passing code as argument) |

---

## Task 1: Add `html5-qrcode` package

**Files:**
- Modify: `package.json`

- [ ] **Step 1: Install the package**

```bash
npm install --save-dev html5-qrcode
```

Expected: `package.json` `devDependencies` now includes `"html5-qrcode": "^2.3.8"` (or similar), `package-lock.json` updated.

- [ ] **Step 2: Verify import resolves**

```bash
node -e "require('./node_modules/html5-qrcode/cjs/html5-qrcode.js'); console.log('ok')"
```

Expected: prints `ok` with no errors.

- [ ] **Step 3: Commit**

```bash
git add package.json package-lock.json
git commit -m "chore: install html5-qrcode devDependency"
```

---

## Task 2: Expose `Html5Qrcode` globally via Vite entry

**Files:**
- Modify: `resources/js/app.js`

- [ ] **Step 1: Add the import + window assignment**

Open `resources/js/app.js`. After the existing `import './bootstrap'` line, add:

```js
import { Html5Qrcode } from 'html5-qrcode'
window.Html5Qrcode = Html5Qrcode
```

- [ ] **Step 2: Verify the build compiles**

```bash
npm run build 2>&1 | tail -10
```

Expected: build succeeds with no errors. A bundle entry for `html5-qrcode` appears in the output.

- [ ] **Step 3: Commit**

```bash
git add resources/js/app.js
git commit -m "feat: expose Html5Qrcode on window via app.js"
```

---

## Task 3: Update `scan()` to accept an optional code argument

**Files:**
- Modify: `app/Livewire/TicketScanner.php`
- Modify: `tests/Feature/TicketScannerTest.php`

This prevents the `$wire.set()` + `$wire.scan()` race condition. Camera calls `$wire.scan(decodedText)`; manual input still calls `$wire.scan()` with no argument.

- [ ] **Step 1: Write the failing test**

Open (or create) `tests/Feature/TicketScannerTest.php`. Check if a test file already exists:

```bash
ls tests/Feature/
```

Add this test (create the file if it doesn't exist, otherwise append to the existing class):

```php
<?php

namespace Tests\Feature;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\TicketTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketScannerTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(): Event
    {
        $event = Event::factory()->create(['capacity' => 100, 'status' => 'published']);
        $tier  = TicketTier::factory()->create(['event_id' => $event->id]);
        return $event;
    }

    /** @test */
    public function it_checks_in_attendee_when_code_passed_as_argument(): void
    {
        $user     = User::factory()->create();
        $event    = $this->makeEvent();
        $tier     = $event->ticketTiers()->first();
        $attendee = Attendee::factory()->create([
            'ticket_tier_id' => $tier->id,
            'status'         => 'confirmed',
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\TicketScanner::class, ['event' => $event])
            ->call('scan', $attendee->ticket_code)
            ->assertDispatched('scan-complete')
            ->assertSet('scanResult.status', 'success');

        $this->assertEquals('checked_in', $attendee->fresh()->status);
    }

    /** @test */
    public function it_checks_in_attendee_via_ticketCode_property(): void
    {
        $user     = User::factory()->create();
        $event    = $this->makeEvent();
        $tier     = $event->ticketTiers()->first();
        $attendee = Attendee::factory()->create([
            'ticket_tier_id' => $tier->id,
            'status'         => 'confirmed',
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\TicketScanner::class, ['event' => $event])
            ->set('ticketCode', $attendee->ticket_code)
            ->call('scan')
            ->assertDispatched('scan-complete')
            ->assertSet('scanResult.status', 'success');

        $this->assertEquals('checked_in', $attendee->fresh()->status);
    }
}
```

- [ ] **Step 2: Run the first test to verify it fails**

```bash
php artisan test --filter it_checks_in_attendee_when_code_passed_as_argument
```

Expected: FAIL — `scan()` does not accept a `$scannedCode` argument yet (or wrong argument count error).

> **Note:** If factories for `Event`, `TicketTier`, `Attendee` don't exist yet, check `database/factories/`. If they're missing, create minimal ones using `php artisan make:factory`. Peek at existing migrations for field names.

- [ ] **Step 3: Update `scan()` in `TicketScanner.php`**

Open `app/Livewire/TicketScanner.php`. Change the `scan()` signature from:

```php
public function scan(): void
{
    $code = strtoupper(trim($this->ticketCode));
    $this->ticketCode = '';
```

To:

```php
public function scan(?string $scannedCode = null): void
{
    if ($scannedCode !== null) {
        $this->ticketCode = $scannedCode;
    }
    $code = strtoupper(trim($this->ticketCode));
    $this->ticketCode = '';
```

No other changes — the rest of `scan()` is unchanged.

- [ ] **Step 4: Run both tests to verify they pass**

```bash
php artisan test --filter TicketScannerTest
```

Expected: 2 tests pass.

- [ ] **Step 5: Run full suite to verify no regressions**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/TicketScanner.php tests/Feature/TicketScannerTest.php
git commit -m "feat: scan() accepts optional scannedCode argument for camera integration"
```

---

## Task 4: Add camera viewfinder to the scanner blade

**Files:**
- Modify: `resources/views/livewire/ticket-scanner.blade.php`

This task integrates everything into the view: the `#qr-reader` container, Alpine state, camera lifecycle, viewfinder overlay, and CSS overrides to hide the library's injected chrome.

- [ ] **Step 1: Add new Alpine state properties to `x-data`**

Open `resources/views/livewire/ticket-scanner.blade.php`. The root `<div>` opens with `x-data="{ show: false, ... }"`. Add four new properties inside that object, after `_dismissTimer: null`:

```js
qrScanner: null,
scanCooldown: false,
cameraError: false,
viewfinderState: 'scanning',   // 'scanning' | 'success' | 'error' | 'denied'
```

- [ ] **Step 2: Add `startCamera()` and `stopCamera()` methods to `x-data`**

Still inside the `x-data` object, after the closing brace of `init()`, add:

```js
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
```

- [ ] **Step 3: Call `startCamera()` and `stopCamera()` from lifecycle hooks**

Inside `init()`, add `this.startCamera()` as the **first line** (before the `$wire.on('scan-complete', ...)` block).

Add a `destroy()` method after `stopCamera()`:

```js
destroy() {
    this.stopCamera()
},
```

- [ ] **Step 4: Update the `scan-complete` handler to drive viewfinder state and cooldown**

Inside the existing `$wire.on('scan-complete', ...)` callback, after extracting `status`, add two blocks:

```js
// 1. Drive viewfinder state (scanCooldown already true from decode callback)
this.viewfinderState = status === 'success' ? 'success' : 'error'

// 2. Independent 1.5s cooldown — releases scanner for the next ticket
//    This is SEPARATE from the 4s result-card dismiss timer below.
setTimeout(() => {
    this.scanCooldown = false
    this.viewfinderState = 'scanning'
}, 1500)
```

The existing 4s `setTimeout` block that dismisses the result card stays unchanged — it only handles `this.show = false` and `$wire.dismissResult()`. The cooldown and viewfinder reset run on their own 1.5s timer so staff can scan the next ticket after 1.5s even while the result card is still visible.

- [ ] **Step 5: Add the viewfinder HTML above the counter cards**

Locate the `{{-- Counter Cards --}}` comment. Insert the viewfinder block **above** it:

```blade
{{-- Camera Viewfinder --}}
<div class="glass-card mb-8 overflow-hidden relative" style="min-height:220px">

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
            <span class="text-red-400 text-sm font-semibold">Scan failed</span>
        </div>
    </div>

    {{-- Camera denied / error fallback --}}
    <div x-show="cameraError" class="flex flex-col items-center justify-center gap-2 rounded-xl" style="min-height:100px;border:2px dashed rgba(239,68,68,0.4);background:rgba(239,68,68,0.06)">
        <span class="text-2xl">🚫</span>
        <span class="text-red-400 text-sm font-semibold">Camera access denied</span>
        <span class="text-slate-500 text-xs">Use manual entry below</span>
    </div>

</div>
```

- [ ] **Step 6: Add CSS overrides and scanline animation to the `<style>` block**

The blade already has a `<style>` block with the `@keyframes shrink` animation. Add to it:

```css
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
```

- [ ] **Step 7: Build assets and do a manual smoke test**

```bash
npm run dev
```

Open `/events/{id}/scanner` in a mobile browser (or Chrome DevTools mobile emulation). Expected:
- Browser prompts for camera permission
- After granting: viewfinder appears with live camera feed, scan line animating, "Live" dot visible
- Library's own buttons/status text are not visible
- Pointing camera at a QR code (e.g. open `/attendees/{id}/qr` on another screen) triggers a scan automatically
- Viewfinder flashes green/red matching the scan result
- After ~1.5s the viewfinder returns to scanning state (result card stays visible until 4s)

- [ ] **Step 8: Commit**

```bash
git add resources/views/livewire/ticket-scanner.blade.php
git commit -m "feat: add camera QR viewfinder to scanner page with Alpine lifecycle"
```

---

## Task 5: End-to-end verification

- [ ] **Step 1: Run full test suite**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 2: Verify camera denied fallback**

In Chrome DevTools → Application → Permissions → Camera → Block for `localhost`. Reload `/events/{id}/scanner`. Expected: dashed red box with 🚫 message appears; manual text input works normally.

- [ ] **Step 3: Verify camera releases on navigation**

Grant camera access, open scanner page, confirm "Live" dot visible. Click "← Back to Events". Verify browser camera indicator light turns off.

- [ ] **Step 4: Final commit if anything was tweaked**

```bash
git add -p
git commit -m "fix: QR scanner adjustments from e2e testing"
```
