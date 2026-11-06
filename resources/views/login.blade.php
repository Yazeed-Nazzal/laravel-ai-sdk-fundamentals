<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · Order support</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 px-4 py-10 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <main class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h1 class="text-2xl font-semibold">Welcome back</h1>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Sign in to ask about your orders.</p>
        <form action="{{ route('login.store') }}" method="POST" class="mt-6 flex flex-col gap-4">
            @csrf
            <div class="flex flex-col gap-2">
                <label for="email" class="text-sm font-medium">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="username" autofocus class="rounded-xl border border-slate-300 bg-transparent p-3 focus:outline-2 focus:outline-indigo-500 dark:border-slate-700" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')
                    <p id="email-error" role="alert" class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex flex-col gap-2">
                <label for="password" class="text-sm font-medium">Password</label>
                <input id="password" name="password" type="password" required maxlength="255" autocomplete="current-password" class="rounded-xl border border-slate-300 bg-transparent p-3 focus:outline-2 focus:outline-indigo-500 dark:border-slate-700" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                @error('password')
                    <p id="password-error" role="alert" class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-3 font-medium text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500">Sign in</button>
        </form>
    </main>
</body>
</html>
