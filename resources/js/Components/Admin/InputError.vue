
<script setup>
import { computed } from 'vue';

const props = defineProps({
    messages: {
        type: [String, Array],
        default: '',
    },
});

const isArray = computed(() => Array.isArray(props.messages));
</script>

<template>
    <div v-if="messages" class="text-sm text-red-600 mt-2">
        <template v-if="isArray">
            <p v-for="(message, index) in messages" :key="index">
                {{ message }}
            </p>
        </template>

        <p v-else>
            {{ messages }}
        </p>
    </div>
</template>
