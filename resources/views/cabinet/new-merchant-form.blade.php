@extends('cabinet.layout.template')

@section('content')
   <div>
       <div class="container py-3">

           <h3 class="mb-4 fw-bold">Add New Merchant</h3>

           <div class="card shadow-sm p-4">

               <form action="{{ route('create-merchant') }}" method="post">
                   @csrf

                   {{-- Success message --}}
                   @if (session()->has('success'))
                       <div
                           x-data="{ show: true }"
                           x-show="show"
                           x-transition
                           x-init="setTimeout(() => show = false, 3000)"
                           class="alert alert-success mb-3 rounded shadow-sm"
                       >
                           ✅ {{ session('success') }}
                       </div>
                   @endif

                   @if (session('error'))
                       <div class="alert alert-danger mb-3 rounded shadow-sm">
                           {{ session('error') }}
                       </div>
                   @endif

                   {{-- Input + Button --}}
                   <div class="row g-2 align-items-end mb-3">

                       <div class="col">
                           <label class="form-label fw-semibold">
                               Merchant's Name
                           </label>
                           <input
                               type="text"
                               name="name"
                               class="form-control"
                               placeholder="Enter merchant name"
                               value="{{ old('name') }}"
                           >
                       </div>

                       <div class="col">
                           <label class="form-label fw-semibold">
                               Callback url
                           </label>
                           <input
                               type="text"
                               name="cburl"
                               class="form-control"
                               placeholder="Enter callback url"
                               value="{{ old('cburl') }}"
                           >
                       </div>

                       <div class="col-auto">
                           <button type="submit" class="btn btn-primary px-4">
                               Create
                           </button>
                       </div>

                   </div>

                   @error('name')
                   <div class="text-danger small mt-1">
                       {{ $message }}
                   </div>
                   @enderror

               </form>

           </div>
       </div>

   </div>
@endsection
