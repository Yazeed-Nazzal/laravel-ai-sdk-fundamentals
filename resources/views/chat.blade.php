<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><title>Chat · Order support</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#e7ece9] font-sans text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    <main data-conversations-url="{{ route('support-conversations.index') }}" data-send-url="{{ route('support-conversations.store') }}" class="mx-auto flex h-dvh max-w-6xl overflow-hidden bg-white md:my-[4dvh] md:h-[92dvh] md:rounded-2xl md:shadow-xl dark:bg-slate-900">
        <aside class="hidden w-80 shrink-0 flex-col border-r border-slate-200 md:flex dark:border-slate-800">
            <header class="flex items-center justify-between px-6 py-6">
                <h1 class="text-2xl font-semibold tracking-tight">Chats<span class="text-emerald-600">.</span></h1>
                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Support</span>
            </header>
            <p class="px-6 pb-4 text-xs font-medium uppercase tracking-widest text-slate-400">Your conversations</p>
            <div id="conversation-list" class="flex min-h-0 flex-col gap-2 overflow-y-auto px-3"></div><div class="mt-auto flex flex-col gap-4 border-t border-slate-100 p-5 dark:border-slate-800">
                <a href="{{ route('order-support.show') }}" class="text-sm text-slate-500 hover:text-emerald-700 dark:text-slate-400">← Back to order support</a>
                <div class="flex items-center gap-3">
                    <div class="flex size-9 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold dark:bg-slate-800">{{ mb_substr(auth()->user()->name, 0, 1) }}</div>
                    <p class="truncate text-sm font-medium">{{ auth()->user()->name }}</p>
                </div>
            </div>
        </aside>

        <section class="flex min-w-0 flex-1 flex-col" aria-label="Order support conversation">
            <header class="flex shrink-0 items-center gap-3 border-b border-slate-100 bg-white px-4 py-4 sm:px-6 dark:border-slate-800 dark:bg-slate-900">
                <a href="{{ route('order-support.show') }}" aria-label="Back to order support" class="p-1 text-slate-500 md:hidden">←</a>
                <div class="relative flex size-11 shrink-0 items-center justify-center rounded-full bg-emerald-700 text-sm font-semibold text-white">
                    OS
                    <span class="absolute right-0 bottom-0 size-3 rounded-full border-2 border-white bg-emerald-400 dark:border-slate-900"></span>
                </div>
                <div class="flex-1">
                    <h2 class="text-sm font-semibold">Order support</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Your personal order assistant</p>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button class="rounded-lg px-3 py-2 text-xs text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">Sign out</button>
                </form>
            </header>
            <div class="border-b border-amber-100 bg-amber-50 px-4 py-2 text-center text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                Order status support · Your conversations are saved to your account.
            </div>
            <div class="flex items-center gap-2 border-b border-slate-200 bg-white px-4 py-2 dark:border-slate-800 dark:bg-slate-900">
                <label for="conversation-select" class="sr-only">Select conversation</label>
                <select id="conversation-select" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-transparent p-2 text-sm dark:border-slate-700"><option value="">New conversation</option></select>
                <button id="new-conversation" type="button" class="shrink-0 rounded-lg bg-emerald-700 px-3 py-2 text-sm text-white">+ New chat</button>
            </div>
            <p id="chat-error" role="alert" class="hidden bg-red-50 px-4 py-2 text-sm text-red-700"></p>
            <div id="chat-messages" role="log" aria-label="Messages" aria-live="polite" class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto bg-[#f0f2ed] bg-[radial-gradient(#cdd6ce_0.7px,transparent_0.7px)] bg-size-[22px_22px] px-4 py-6 sm:px-8 dark:bg-slate-950 dark:bg-[radial-gradient(#24332e_0.7px,transparent_0.7px)]">
                <span class="self-center rounded-lg bg-white/90 px-3 py-1 text-[10px] font-medium uppercase tracking-wider text-slate-500 shadow-sm dark:bg-slate-800 dark:text-slate-400">Today</span>
                <div class="max-w-[85%] self-start rounded-2xl rounded-tl-sm bg-white px-4 py-3 shadow-sm sm:max-w-[75%] dark:bg-slate-800">
                    <p class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-400">Order support</p>
                    <p class="mt-1 text-sm leading-relaxed">Hi {{ auth()->user()->name }}! 👋<br>Let’s check on your order. What can I help you with today?</p>
                    <p class="mt-2 text-right text-[10px] text-slate-400">Welcome</p>
                </div>
            </div>
            <footer class="shrink-0 border-t border-slate-100 bg-white px-4 pt-3 pb-4 sm:px-6 dark:border-slate-800 dark:bg-slate-900">
                <form id="chat-form" class="flex items-end gap-3">
                    <label for="chat-input" class="sr-only">Your message</label>
                    <textarea id="chat-input" rows="1" required maxlength="2000" placeholder="Type a message…" class="max-h-32 min-h-12 flex-1 resize-none rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-800"></textarea>
                    <button type="submit" aria-label="Send message" class="flex size-12 shrink-0 items-center justify-center rounded-full bg-emerald-700 text-white transition hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">
                        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4 20-7Z"/><path d="M22 2 11 13"/></svg>
                    </button>
                </form>
                <p class="mt-2 text-center text-[10px] text-slate-400">Enter to send · Shift + Enter for a new line</p>
            </footer>
        </section>
    </main>
</body>
</html>
