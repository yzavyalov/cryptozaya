<div>
    <div class="card-body p-4">
        <img src="{{ asset('images/zaya_logo2.png') }}" alt="" style="width: 150px; height: auto;">
        <h4 class="text-center mb-4">
            {{ $codeSent ? 'Enter Code' : 'Sign In' }}
        </h4>

        {{-- Email form --}}
        @if (!$codeSent)
            <form wire:submit.prevent="sendCode">
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email"
                           id="email"
                           class="form-control"
                           wire:model.defer="email"
                           required>

                    @error('email')
                    <div class="alert alert-danger mt-2">{{ $message }}</div>
                    @enderror

                    <div class="form-text">
                        We'll never share your email with anyone else.
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Send Code</button>
                </div>
            </form>
        @endif

        {{-- Code form --}}
        @if ($codeSent)
            <form wire:submit.prevent="verifyCode">
                <div class="mb-3">
                    <label for="code" class="form-label">Enter your code:</label>
                    <input type="text"
                           id="code"
                           name="code"
                           class="form-control"
                           wire:model.defer="code"
                           required>

                    @error('code')
                    <div class="alert alert-danger mt-2">{{ $message }}</div>
                    @enderror

                    {{-- Client-side timer (NO wire:poll) --}}
                    <div
                        class="mt-2 text-muted fw-semibold"
                        x-data="lwTimer({{ (int) ($expiresAt ?? 0) }})"
                        x-init="init()"
                        x-on:timer-reset.window="reset($event.detail.expiresAt)"
                    >
                        The code sent to you by email will be valid:
                        <span x-text="formatted"></span>
                    </div>

                    <div class="text-center mt-3">
                        <button class="btn btn-outline-secondary"
                                type="button"
                                wire:click="resendCode"
                                :disabled="secondsLeft > 0"
                                x-data
                                x-bind:disabled="$store?.timer?.secondsLeft > 0"
                        >
                            Send new code
                        </button>
                    </div>

                    {{-- Вариант попроще без store (ниже) --}}
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>

            {{-- Если не хочешь заморачиваться со store, используй кнопку так: --}}
            {{--
            <div class="text-center mt-3"
                 x-data="{}"
                 x-on:lw-timer-seconds.window="$refs.btn.disabled = ($event.detail.secondsLeft > 0)">
                <button x-ref="btn" class="btn btn-outline-secondary" type="button" wire:click="resendCode">
                    Send new code
                </button>
            </div>
            --}}
        @endif
    </div>

    {{-- Timer script (можно вынести в layout один раз) --}}
    <script>
        document.addEventListener('alpine:init', () => {
            // простой таймер для Livewire
            Alpine.data('lwTimer', (initialExpiresAt) => ({
                expiresAt: initialExpiresAt,
                secondsLeft: 0,
                formatted: '00:00',
                interval: null,

                init() {
                    this.start(this.expiresAt)
                },

                reset(newExpiresAt) {
                    this.start(parseInt(newExpiresAt || 0))
                },

                start(expiresAt) {
                    this.expiresAt = expiresAt
                    if (this.interval) clearInterval(this.interval)

                    const tick = () => {
                        const now = Math.floor(Date.now() / 1000)
                        const diff = Math.max(0, (this.expiresAt || 0) - now)
                        this.secondsLeft = diff

                        const m = Math.floor(diff / 60)
                        const s = diff % 60
                        this.formatted = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0')

                        // если хочешь управлять кнопкой через window event:
                        window.dispatchEvent(new CustomEvent('lw-timer-seconds', { detail: { secondsLeft: diff } }))

                        if (diff === 0 && this.interval) {
                            clearInterval(this.interval)
                            this.interval = null
                        }
                    }

                    tick()
                    this.interval = setInterval(tick, 1000)
                },
            }))
        })
    </script>
</div>
