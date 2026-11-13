<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><title>Technical support · Structured Outputs</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#e7ece9] font-sans text-slate-800 dark:bg-slate-950 dark:text-slate-100">
<main class="mx-auto flex h-dvh max-w-6xl overflow-hidden bg-white md:my-[4dvh] md:h-[92dvh] md:rounded-2xl md:shadow-xl dark:bg-slate-900">
    <aside class="hidden w-80 shrink-0 flex-col border-r border-slate-200 p-6 md:flex dark:border-slate-800">
        <h1 class="text-2xl font-semibold">Issues<span class="text-emerald-600">.</span></h1>
        <p class="mt-3 text-sm text-slate-500">Tell us what happened. We will ask for any missing details before creating your ticket.</p>
        <a href="{{ route('chat.show') }}" class="mt-auto text-sm text-emerald-700 dark:text-emerald-400">Back to agent chat</a>
        <p class="mt-4 text-sm">{{ auth()->user()->name }}</p>
    </aside>
    <section class="flex min-w-0 flex-1 flex-col" aria-label="Technical support chat">
        <header class="flex items-center gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <span class="flex size-11 items-center justify-center rounded-full bg-emerald-700 text-sm font-semibold text-white">TS</span>
            <div class="flex-1"><h2 class="font-semibold">Technical support</h2><p class="text-xs text-slate-500">Report an issue and track its status</p></div>
            <a href="{{ route('chat.show') }}" class="text-sm text-emerald-700 dark:text-emerald-400">Agent chat</a>
            @can('manage-support-issues')
                <a href="{{ route('technical-support.index') }}" class="text-sm text-emerald-700 dark:text-emerald-400">Staff queue</a>
            @endcan
        </header>
        <div class="flex items-center justify-between gap-3 bg-amber-50 px-4 py-2 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-200">
            <p>One issue per conversation. Your ticket number appears when your report is complete.</p>
            <button id="new-issue" type="button" data-reset-url="{{ route('technical-support.reset') }}" class="shrink-0 underline disabled:opacity-50">New report</button>
        </div>
        <div id="issue-messages" role="log" aria-live="polite" class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto bg-[#f0f2ed] bg-[radial-gradient(#cdd6ce_0.7px,transparent_0.7px)] bg-size-[22px_22px] px-4 py-6 sm:px-8 dark:bg-slate-950">
            <p class="self-start rounded-2xl bg-white px-4 py-3 text-sm shadow-sm dark:bg-slate-800">Hi {{ auth()->user()->name }}! Describe your issue and include the order ID if available.</p>
            {{-- Blade escapes customer text. Only the signed-in customer's tickets reach this view. --}}
            @forelse($issues as $issue)
                <article class="flex flex-col gap-2 rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-800">
                    <p class="whitespace-pre-wrap break-words text-sm">{{ $issue->message }}</p>
                    <p class="text-sm text-emerald-700 dark:text-emerald-400">Thank you! Your support ticket is #{{ $issue->id }}. Our support team will review your issue.</p>
                </article>
            @empty
                <p id="no-issues" class="text-center text-xs text-slate-500">No issues reported yet.</p>
            @endforelse
            {{ $issues->links() }}
            <div id="issue-draft" class="flex flex-col gap-4">
                @foreach($draft as $message)
                    <p dir="auto" @class([
                        'max-w-[85%] whitespace-pre-wrap break-words rounded-2xl px-4 py-3 text-sm shadow-sm',
                        'self-end bg-emerald-100 dark:bg-emerald-900' => $message['role'] === 'user',
                        'self-start bg-white dark:bg-slate-800' => $message['role'] === 'assistant',
                    ])>{{ $message['content'] }}</p>
                @endforeach
            </div>
        </div>
        <footer class="border-t border-slate-100 p-4 dark:border-slate-800">
            <p id="issue-error" role="alert" class="mb-2 text-sm text-red-600"></p>
            <form id="issue-form" action="{{ route('technical-support.store') }}" class="flex items-end gap-3">
                @csrf
                <label for="issue-input" class="sr-only">Describe your issue</label>
                <textarea id="issue-input" name="message" rows="2" required maxlength="2000" placeholder="I was charged twice for order 105…" class="min-w-0 flex-1 resize-none rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-800"></textarea>
                <button class="rounded-full bg-emerald-700 px-5 py-3 text-sm text-white hover:bg-emerald-800 disabled:opacity-50">Send message</button>
            </form>
            <p class="mt-2 text-center text-xs text-slate-500">Your replies stay in this conversation until your ticket is created.</p>
        </footer>
    </section>
</main>
</body>
</html>
