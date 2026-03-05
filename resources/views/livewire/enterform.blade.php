<div>
    <div class="card-body p-4">
        <img src="{{ asset('images/zaya_logo2.png') }}" alt="" style="width: 150px; height: auto;">
        <h4 class="text-center mb-4">
            {{ $codeSent ? 'Enter Code' : 'Sign In' }}
        </h4>

        {{-- Форма ввода email --}}
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
                        <div class="alert alert-danger mt-2">
                            {{ $message }}
                        </div>
                    @enderror
                    <div id="emailHelp" class="form-text">
                        We'll never share your email with anyone else.
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        Send Code
                    </button>
                </div>
            </form>
        @endif

        {{-- Форма ввода кода --}}
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
                        <div class="alert alert-danger mt-2">
                            {{ $message }}
                        </div>
                    @enderror

                    {{-- Таймер с автоматическим обновлением --}}
                    <div class="mt-2 text-muted fw-semibold" wire:poll.1s>
                        The code sent to you by email will be valid: {{ $this->formattedTime }}
                    </div>

                    {{-- Кнопка повторной отправки кода --}}
                    <div class="text-center"><button class="btn btn-outline-secondary" type="button" wire:click="resendCode">Send new code</button></div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        Submit
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
