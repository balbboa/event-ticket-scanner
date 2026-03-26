# QR Camera Scanning — Design Spec

**Date:** 2026-03-25
**Status:** Approved

---

## Context

The scanner page (`/events/{event}/scanner`) currently accepts ticket codes only through a text input. Staff must type or paste codes manually, which is slow and error-prone at a busy venue door. The QR codes are already generated server-side (`/attendees/{attendee}/qr`), but there is no way to read them on the scanner page.

This spec defines the implementation of camera-based QR scanning directly in the browser, enabling staff to point their phone or tablet at a ticket and have it check in automatically — zero taps required.

---

## Requirements

- Staff use **phones or tablets** (mobile browser with camera)
- Scanning is **fully automatic**: the camera runs continuously and fires the moment a QR code is detected — no tap needed
- A **1.5s cooldown** after each scan prevents the same ticket from being scanned twice; the cooldown resets on receipt of `scan-complete` (not on decode) to account for slow network round-trips
- If camera permission is **denied**, the page degrades gracefully: shows a clear error state in the viewfinder area and promotes the manual text input as the primary control
- The **camera stream is released** when the user navigates away, using Alpine's `destroy()` lifecycle hook
- All existing features (manual text input, manual lookup, recent scans, audio feedback) remain fully functional alongside camera scanning

---

## Library

**`html5-qrcode`** (npm, installed as `devDependency` to match project convention) — wraps the ZXing decoder, handles `getUserMedia`, camera selection, permission dialogs, and the decode loop. Chosen over `@zxing/browser` (heavier, more complex) and raw `jsQR` (requires manual camera lifecycle management).

The library injects its own DOM into the `#qr-reader` container (video, canvas, and UI controls). Its built-in controls (`#qr-reader__dashboard`, `#qr-reader__status_span`) must be hidden via CSS since the design uses custom overlay elements (scan line, corner brackets, status dot). Use `#qr-reader__dashboard { display: none !important; }` in the scanner's `<style>` block.

---

## Architecture

**3 frontend files** change, **1 backend file** changes:

| File | Change |
|------|--------|
| `package.json` | Add `html5-qrcode` to `devDependencies` |
| `resources/js/app.js` | Import `Html5Qrcode`, expose to `window` |
| `resources/views/livewire/ticket-scanner.blade.php` | Add `<div id="qr-reader">` + camera lifecycle in Alpine.js |
| `app/Livewire/TicketScanner.php` | Add `$code` parameter to `scan()` to avoid `$wire.set()` + `$wire.scan()` race condition |

### Why the backend change is needed

In Livewire 3, `$wire.set()` is asynchronous and batches a network request. Calling `$wire.scan()` immediately after does not guarantee the server has committed the new `ticketCode` before `scan()` executes. The safest pattern is to pass the decoded code directly as a method argument:

```js
$wire.scan(decodedText)   // server receives code as $code parameter
```

This means `scan()` gains an optional `$code` parameter:

```php
public function scan(?string $code = null): void
{
    if ($code !== null) {
        $this->ticketCode = $code;
    }
    // existing logic unchanged ...
}
```

Manual text input still calls `$wire.scan()` with no argument (using `$this->ticketCode` as today).

---

## State Machine

```
Page Load
  └─ request camera permission
       ├─ granted → [SCANNING]
       └─ denied  → [CAMERA ERROR] — cameraError = true, text input promoted

[SCANNING]          (scanCooldown = false)
  └─ QR code detected in frame
       └─ call $wire.scan(decodedText)
            └─ scanCooldown = true  ← locked until scan-complete received

[WAITING FOR RESPONSE]  (scanCooldown = true, camera continues running)
  └─ scan-complete event received from Livewire
       └─ play sound · show result card
            └─ [COOLDOWN — 1.5s timeout starts NOW]
                 └─ scanCooldown = false → [SCANNING]
```

The cooldown starts when `scan-complete` is received (not on decode), so a slow network round-trip cannot cause a race between two in-flight requests.

---

## UI Layout

The viewfinder is placed **above the stats cards**, making it the visual primary element on the page.

### Viewfinder (scanning state)
- Rounded card, purple border (`rgba(139,92,246,0.6)`)
- Animated horizontal scan line sweeping top→bottom
- Corner bracket targeting guides
- "Live" green dot indicator (top-right)
- "Point camera at QR code" hint (bottom)
- Library's own UI chrome hidden via CSS

### Viewfinder (success state)
- Border flashes **green** (`rgba(16,185,129,0.8)`) with green glow
- Shows ✅ checkmark + "Ticket accepted" message
- Thin cooldown progress bar sweeps across the bottom during the 1.5s cooldown
- After cooldown: border returns to purple, scanning resumes

### Viewfinder (error/already-used state)
- Border flashes **red** (`rgba(239,68,68,0.8)`) with red glow
- Shows ✗ + brief status ("Already used" / "Invalid" / "Cancelled")
- Same 1.5s cooldown before resuming

### Camera denied fallback (`cameraError: true`)
- Viewfinder area replaced with a dashed red border box: 🚫 "Camera access denied — Use manual entry below"
- Manual text input is visually promoted (larger, prominent label)
- All other features (stats, lookup, recent scans) unchanged

---

## Implementation Details

### `package.json`
```json
"devDependencies": {
    "html5-qrcode": "^2.3.8",
    ...
}
```

### `resources/js/app.js`
```js
import { Html5Qrcode } from 'html5-qrcode'
window.Html5Qrcode = Html5Qrcode
```

### Alpine.js `x-data` additions

```js
qrScanner: null,
scanCooldown: false,
cameraError: false,
viewfinderState: 'scanning',   // 'scanning' | 'success' | 'error' | 'denied'

init() {
    this.startCamera()

    $wire.on('scan-complete', (event) => {
        const status = event[0]?.status ?? event?.status ?? ''

        // scanCooldown is already true (set by startCamera decode callback).
        // Only update viewfinderState here — do not re-assign scanCooldown.
        this.viewfinderState = status === 'success' ? 'success' : 'error'

        // existing: show result card, play sounds ...

        setTimeout(() => {
            this.scanCooldown = false
            this.viewfinderState = 'scanning'
        }, 1500)
    })
},

destroy() {
    this.stopCamera()   // Alpine lifecycle — called when component leaves DOM
},

startCamera() {
    this.qrScanner = new window.Html5Qrcode('qr-reader')
    this.qrScanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 220, height: 220 } },
        (decodedText) => {
            if (this.scanCooldown) return
            this.scanCooldown = true   // lock immediately to prevent double-fire
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

Note: `scanCooldown` is set to `true` immediately on decode (to prevent a second QR read firing before the server responds) but is only released after `scan-complete` + 1.5s. This means the lock is held for the full round-trip + 1.5s — the safe conservative approach.

### `app/Livewire/TicketScanner.php` — `scan()` signature change

```php
public function scan(?string $scannedCode = null): void
{
    if ($scannedCode !== null) {
        $this->ticketCode = $scannedCode;
    }
    $code = strtoupper(trim($this->ticketCode));
    $this->ticketCode = '';
    // ... rest of existing logic unchanged
}
```

### Viewfinder element + CSS

```html
<div id="qr-reader" class="w-full rounded-xl" style="min-height:220px;position:relative"></div>
```

```css
/* Hide html5-qrcode injected UI chrome */
#qr-reader__dashboard { display: none !important; }
#qr-reader__status_span { display: none !important; }
#qr-reader video { border-radius: 12px; }
```

---

## Attendee QR Code Delivery

The QR code contains only the ticket code string (e.g. `EVT-ABCD1234`). Attendees receive their QR code via:

1. **Email** — organisers send the QR PNG (from `/attendees/{id}/qr`) in the confirmation email
2. **Admin download** — existing "Download QR" action on attendee edit page

No changes needed to QR generation.

---

## Error Handling

| Scenario | Handling |
|----------|----------|
| Camera permission denied | `cameraError = true` → dashed red box, "Use manual entry", text input promoted |
| No camera hardware | Same as above |
| QR decode fails (bad frame) | Silent — library retries on next frame automatically |
| Decoded string is not a ticket code | `$wire.scan(code)` returns "invalid" — existing error card + sound |
| Network timeout on scan | Livewire error handling; `scanCooldown` stays true until scan-complete or page reload |
| Double-scan (same ticket within cooldown) | `scanCooldown` guard prevents second fire |
| Double-scan after cooldown | Server returns "already used" — amber card, red viewfinder flash |

---

## Testing Checklist

1. Open `/events/{id}/scanner` on a phone — browser requests camera permission
2. Grant permission → viewfinder appears with animated scan line and "Live" dot; library UI chrome not visible
3. Point at QR code PNG (`/attendees/{id}/qr`) → ticket scans automatically, no tap
4. Viewfinder flashes green → success sound → result card → recent scans update
5. Point at same QR code immediately → nothing fires (cooldown active)
6. Point at same QR after cooldown expires → "already used" amber card, red viewfinder flash
7. Point at a QR code containing random text → "invalid" card
8. Deny camera permission → dashed red box shown, manual text input works normally
9. Navigate away → camera stream stops (browser camera indicator light turns off)
10. Manual text input still works alongside camera (pass no argument to `$wire.scan()`)
11. Run `php artisan test` — no regressions
