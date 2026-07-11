<!DOCTYPE html>
<html   lang="en-US">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#dc2626">
    <title>TOMECO Mobile Login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
    <main class="flex min-h-full flex-col justify-center px-6 py-12 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-sm">
            <img
                src="{{ asset('pwa/icons/android/launchericon-192x192.png') }}"
                alt="TOMECO"
                class="mx-auto h-20 w-auto rounded-full"
            >
            <p class="mt-6 text-center text-xs font-semibold uppercase tracking-wide text-red-700">
                Traffic Operations Management, Enforcement & Control Office
            </p>
            <h2 class="mt-3 text-center text-2xl/9 font-bold tracking-tight text-gray-900">
                Sign in to your account
            </h2>
        </div>

        <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-sm">
            <form action="{{ route('mobile.login.store') }}" method="POST" class="space-y-6" novalidate>
                @csrf

                @if ($errors->any())
                    <div class="rounded-md bg-red-50 p-4 text-sm font-medium text-red-700" role="alert">
                        Please check your email address or password and try again.
                    </div>
                @endif

                <div>
                    <label for="email" class="block text-sm/6 font-medium text-gray-900">Email address</label>
                    <div class="mt-2">
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            inputmode="email"
                            autocomplete="email"
                            placeholder="name@example.com"
                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-red-600 sm:text-sm/6"
                        >
                    </div>
                    @error('email')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <label for="password" class="block text-sm/6 font-medium text-gray-900">Password</label>
                        <div class="text-sm">
                            <span class="font-semibold text-red-600">Forgot password?</span>
                        </div>
                    </div>
                    <div class="mt-2">
                        <input
                            id="password"
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-red-600 sm:text-sm/6"
                        >
                    </div>
                    @error('password')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <button
                        type="submit"
                        class="flex w-full justify-center rounded-md bg-red-600 px-3 py-1.5 text-sm/6 font-semibold text-white shadow-xs hover:bg-red-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600"
                    >
                        Sign in
                    </button>
                </div>
            </form>

            <p class="mt-10 text-center text-sm/6 text-gray-500">
                Need an account?
                <span class="font-semibold text-red-600">Contact the TOMECO administrator</span>
            </p>
        </div>
    </main>
</body>
</html>
