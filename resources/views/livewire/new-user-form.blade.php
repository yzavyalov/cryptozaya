<div>
    <div class="card shadow-sm border rounded-4" style="width: 480px; background-color: #fff;">
        <div class="card-body p-4">
            <form action="{{ route('create-user') }}" method="post">
                @csrf
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0 text-dark fw-semibold">Add New User</h5>
                </div>

                {{-- Success message --}}
                @if (session()->has('success'))
                    <div
                        x-data="{ show: true }"
                        x-show="show"
                        x-transition
                        x-init="setTimeout(() => show = false, 3000)"
                        class="alert alert-success alert-dismissible fade show mt-2 rounded-3 shadow-sm"
                        role="alert"
                    >
                        <strong>✅</strong> {{ session('success') }}
                    </div>
                @endif

                {{-- Name field --}}
                <div class="mb-4">
                    <label for="name" class="form-label mb-1 text-secondary">User's Name</label>
                    <input type="text" id="name" name="name" class="form-control form-control-lg" placeholder="Enter user's name">
                    @error('name')
                    <div class="invalid-feedback d-block bg-danger-subtle text-danger-emphasis p-2 rounded mt-1">
                        {{ $message }}
                    </div>
                    @enderror
                </div>

                {{-- Email field --}}
                <div class="mb-4">
                    <label for="email" class="form-label mb-1 text-secondary">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control form-control-lg" placeholder="Enter user's email">
                    @error('email')
                    <div class="invalid-feedback d-block bg-danger-subtle text-danger-emphasis p-2 rounded mt-1">
                        {{ $message }}
                    </div>
                    @enderror
                    <div>
                        @if (session('success'))
                            <div class="alert alert-success mt-2">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="alert alert-danger mt-2">
                                {{ session('error') }}
                            </div>
                        @endif

                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg rounded-3">
                        Submit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Alpine.js (если ещё не подключён) --}}
<script src="//unpkg.com/alpinejs" defer></script>
