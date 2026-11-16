<script setup>
import { computed, ref } from 'vue';

const props = defineProps({ result: Object, code: String });
const lines = computed(() => props.code.split(/\r?\n/));
const bad = computed(() => new Set((props.result.errors || []).map((e) => e.line)));
const copied = ref(false);

async function copy() {
    await navigator.clipboard.writeText(props.result.fixed_code);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
}
</script>

<template>
    <section class="cp-panel mt-8 space-y-8">
        <div>
            <h2 class="cp-h2">[ DIAGNOSIS ]</h2>
            <p class="mt-3 text-lg leading-relaxed">
                <span class="cp-chip" :class="{ bad: result.has_error }">{{ result.has_error ? 'ERROR_DETECTED' : 'NO_ERROR' }}</span>
                {{ result.summary }}
            </p>
        </div>

        <div v-if="result.errors.length">
            <h3 class="cp-h3">// 問題位置</h3>
            <pre class="cp-pre"><span v-for="(l, i) in lines" :key="i" class="cp-ln" :class="{ hit: bad.has(i + 1) }"><b>{{ i + 1 }}</b>{{ l }}</span></pre>
            <div class="mt-4 space-y-3">
                <div v-for="(e, i) in result.errors" :key="i" class="cp-card">
                    <span class="cp-chip bad">{{ e.line > 0 ? 'LINE ' + e.line : 'GENERAL' }}</span><b>{{ e.title }}</b>
                    <p>{{ e.cause }}</p>
                </div>
            </div>
        </div>

        <div v-if="result.steps.length">
            <h3 class="cp-h3">// 一步一步修正</h3>
            <div class="space-y-4">
                <div v-for="(s, i) in result.steps" :key="i" class="cp-card step">
                    <span class="cp-chip">STEP {{ String(i + 1).padStart(2, '0') }}</span><b>{{ s.title }}</b>
                    <p>{{ s.explanation }}</p>
                    <div v-if="s.before || s.after" class="mt-3 grid gap-3 md:grid-cols-2">
                        <pre class="cp-pre before">{{ s.before }}</pre>
                        <pre class="cp-pre after">{{ s.after }}</pre>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="result.fixed_code">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="cp-h3 !mb-0">// 修正後完整程式碼</h3>
                <button class="cp-link" @click="copy">{{ copied ? '已複製' : '複製程式碼' }}</button>
            </div>
            <pre class="cp-pre after">{{ result.fixed_code }}</pre>
        </div>

        <div v-if="result.concepts.length">
            <h3 class="cp-h3">// 語法與觀念</h3>
            <div class="grid gap-4 md:grid-cols-2">
                <div v-for="(c, i) in result.concepts" :key="i" class="cp-card" style="border-left-color: var(--ye)">
                    <b>{{ c.name }}</b>
                    <p>{{ c.explanation }}</p>
                    <pre v-if="c.example" class="cp-pre mt-3">{{ c.example }}</pre>
                </div>
            </div>
        </div>
    </section>
</template>
