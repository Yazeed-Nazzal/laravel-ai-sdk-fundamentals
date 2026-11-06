<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order support</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 px-4 py-10 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <main class="mx-auto flex max-w-2xl flex-col gap-6">
        <header class="flex flex-col gap-2">
            <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">Your order assistant</p>
            <h1 class="text-3xl font-semibold tracking-tight">Order support</h1>
            <p class="text-slate-600 dark:text-slate-400">Ask about your order and see the assistant’s response below.</p>
        </header>
        <a href="{{ route('direct-assistant.show') }}" class="text-sm text-indigo-600 hover:underline dark:text-indigo-400">Try the direct assistant</a>

            <div class="flex items-center justify-between gap-4">
                <p class="text-sm text-slate-600 dark:text-slate-400">Signed in as {{ auth()->user()->name }}</p>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400">Sign out</button>
                </form>
            </div>
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <form id="order-support-form" action="{{ route('order-support') }}" method="POST" class="flex flex-col gap-4">
                    @csrf
                    <label for="message" class="font-medium">Your question</label>
                    <textarea id="message" name="message" rows="4" required maxlength="2000" placeholder="What is the status of order 2?" class="w-full resize-y rounded-xl border border-slate-300 bg-transparent p-3 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700"></textarea>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Include your order ID. Each question is sent independently.</p>
                    <button id="send-message" type="submit" class="self-start rounded-xl bg-indigo-600 px-5 py-3 font-medium text-white hover:bg-indigo-700 disabled:cursor-wait disabled:opacity-60">Send message</button>
                    <p id="chat-error" role="alert" class="hidden text-sm text-red-600 dark:text-red-400"></p>
                </form>
            </section>
            <section aria-labelledby="response-heading" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 id="response-heading" class="font-semibold">Assistant response</h2>
                <p id="agent-response" role="status" aria-live="polite" class="mt-3 whitespace-pre-wrap break-words text-slate-600 dark:text-slate-300">Your response will appear here.</p>
            </section>

    </main>
</body>
</html>
