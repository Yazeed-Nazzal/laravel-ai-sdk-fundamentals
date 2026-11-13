<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Technical support queue</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 p-4 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
<main class="mx-auto flex max-w-6xl flex-col gap-6 py-6">
    <header class="flex flex-wrap items-center justify-between gap-4">
        <div><p class="text-sm text-emerald-700 dark:text-emerald-400">Support workspace</p><h1 class="text-3xl font-semibold">Technical support issues</h1></div>
        <a href="{{ route('technical-support.chat') }}" class="text-sm text-emerald-700 dark:text-emerald-400">Report an issue</a>
    </header>
    <p class="text-sm text-slate-500">Highest priority first, oldest first within each priority. All open issues remain in the queue, even when human review was not requested.</p>
    <nav aria-label="Filter status" class="flex gap-4 text-sm text-emerald-700 dark:text-emerald-400">
        <a href="{{ route('technical-support.index') }}">All</a>
        <a href="{{ route('technical-support.index', ['status'=>'open']) }}">Open</a>
        <a href="{{ route('technical-support.index', ['status'=>'solved']) }}">Solved</a>
    </nav>
    @if(session('status'))<p role="status" class="text-sm text-emerald-700 dark:text-emerald-400">{{ session('status') }}</p>@endif
    @if($errors->any())<p role="alert" class="text-red-600">{{ $errors->first() }}</p>@endif
    @forelse($issues as $issue)
        <article class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center gap-3"><h2 class="font-semibold">Issue #{{ $issue->id }}</h2><span class="rounded-full bg-emerald-50 px-3 py-1 text-xs text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ $issue->priority }} priority · {{ $issue->status }}</span></div>
            <p class="whitespace-pre-wrap break-words">{{ $issue->message }}</p>
            <dl class="grid gap-2 text-sm text-slate-600 sm:grid-cols-2 dark:text-slate-400">
                <div><dt class="font-medium">Customer</dt><dd>{{ $issue->user->name }} · {{ $issue->user->email }}</dd></div>
                <div><dt class="font-medium">Category</dt><dd>{{ $issue->category }}</dd></div>
                <div><dt class="font-medium">Reported order (unverified)</dt><dd>{{ $issue->reported_order_id ?? 'Not provided' }}</dd></div>
                <div><dt class="font-medium">Human review</dt><dd>{{ $issue->requires_human ? 'Required' : 'Not requested by classifier' }}</dd></div>
                <div><dt class="font-medium">Reported</dt><dd>{{ $issue->created_at->format('Y-m-d H:i') }}</dd></div>
            </dl>
            @if($issue->conversation)
                <details>
                    <summary class="cursor-pointer text-sm">Conversation history</summary>
                    @foreach($issue->conversation->messages->sortBy('id') as $message)
                        <p dir="auto" class="mt-2 whitespace-pre-wrap break-words text-sm"><strong>{{ $message->role === 'user' ? 'Customer' : 'Support assistant' }}:</strong> {{ $message->content }}</p>
                    @endforeach
                </details>
            @endif
            {{-- The staff gate protects both the queue and this update route. --}}
            <form action="{{ route('technical-support.update', $issue) }}" method="POST">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="{{ $issue->status === 'open' ? 'solved' : 'open' }}">
                <button class="rounded-xl bg-emerald-700 px-4 py-2 text-sm text-white hover:bg-emerald-800">{{ $issue->status === 'open' ? 'Mark solved' : 'Reopen issue' }}</button>
            </form>
        </article>
    @empty
        <p class="rounded-2xl bg-white p-8 text-center text-slate-500 dark:bg-slate-900">No issues match this filter.</p>
    @endforelse
    {{ $issues->links() }}
</main>
</body>
</html>
