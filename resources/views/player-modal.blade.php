<div class="space-y-4">
    @if($isMinecraft)
        <div class="flex justify-center">
            <img src="{{ $avatar }}" alt="{{ $player }}" class="rounded-lg" style="width: 128px; height: 128px;">
        </div>
        
        <div class="text-center">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ $player }}</h3>
            <div class="mt-2">
                @if($isOp)
                    <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-500/10 dark:text-green-400 dark:ring-green-500/20">
                        {{ trans('player-counter::query.op') }}
                    </span>
                @else
                    <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20">
                        Player
                    </span>
                @endif
            </div>
        </div>
    @else
        <div class="text-center">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ $player }}</h3>
            @if($time)
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    <strong>Play Time:</strong> {{ \Carbon\CarbonInterval::seconds($time)->cascade()->forHumans() }}
                </p>
            @endif
        </div>
    @endif
</div>
