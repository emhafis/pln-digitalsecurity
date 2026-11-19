<template>
    <div class="min-h-screen bg-gray-100">
        <div class="relative mx-auto flex min-h-screen w-full max-w-md flex-col overflow-x-hidden bg-white">
            <div class="flex-1 pb-[calc(5.25rem+env(safe-area-inset-bottom))]">
                <slot />
            </div>
            <slot name="bottom" />
        </div>
    </div>
</template>
