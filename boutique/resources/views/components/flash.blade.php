@props(['inline' => false])
@if (session('status'))
    <div class="toast bg-success-soft text-on-success-soft" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any() && ! $inline)
    <div class="toast bg-danger-soft text-on-danger-soft" role="alert">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif
