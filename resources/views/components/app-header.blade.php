<header class="bg-white shadow-sm border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            {{ $slot }}
        </div>
        <div class="flex items-center gap-3 shrink-0">
            {{ $actions ?? '' }}
            <div class="flex items-center gap-2 border-l border-gray-200 pl-3">
                <a href="{{ route('dashboard.index') }}"
                   class="hidden sm:flex items-center gap-1.5 text-xs px-2.5 py-1.5 rounded-lg border transition-colors
                          {{ request()->routeIs('dashboard.*')
                              ? 'text-blue-700 border-blue-200 bg-blue-50'
                              : 'text-gray-600 border-gray-200 bg-white hover:bg-gray-50' }}">
                    Dashboard
                </a>
                <a href="{{ route('planilhas.index') }}"
                   class="hidden sm:flex items-center gap-1.5 text-xs px-2.5 py-1.5 rounded-lg border transition-colors
                          {{ request()->routeIs('planilhas.*')
                              ? 'text-blue-700 border-blue-200 bg-blue-50'
                              : 'text-gray-600 border-gray-200 bg-white hover:bg-gray-50' }}">
                    Planilhas
                </a>
                <a href="{{ route('ferramentas.index') }}"
                   class="hidden sm:flex items-center gap-1.5 text-xs px-2.5 py-1.5 rounded-lg border transition-colors
                          {{ request()->routeIs('ferramentas.*')
                              ? 'text-blue-700 border-blue-200 bg-blue-50'
                              : 'text-gray-600 border-gray-200 bg-white hover:bg-gray-50' }}">
                    Buscas específicas
                </a>
                <a href="{{ route('assinatura.index') }}"
                   title="{{ auth()->user()->subscribed('default') ? 'Assinatura ativa' : 'Sem assinatura ativa' }}"
                   class="hidden sm:flex items-center gap-1.5 text-xs px-2.5 py-1.5 rounded-lg border transition-colors
                          {{ auth()->user()->subscribed('default')
                              ? 'text-green-700 border-green-200 bg-green-50 hover:bg-green-100'
                              : 'text-amber-700 border-amber-200 bg-amber-50 hover:bg-amber-100' }}">
                    <span class="w-1.5 h-1.5 rounded-full shrink-0
                                 {{ auth()->user()->subscribed('default') ? 'bg-green-500' : 'bg-amber-400' }}"></span>
                    Assinatura
                </a>
                <a href="{{ route('profile.edit') }}"
                   class="text-xs text-gray-500 hidden sm:block hover:text-gray-800 truncate max-w-32 transition-colors">
                    {{ auth()->user()->name }}
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="text-xs text-gray-500 hover:text-red-600 border border-gray-200 hover:border-red-200 hover:bg-red-50 px-3 py-1.5 rounded-lg transition-colors">
                        Sair
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
