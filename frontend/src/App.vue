<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { api } from './api';
import { samples } from './samples';
import Result from './components/Result.vue';

const languages = ['JavaScript', 'TypeScript', 'Python', 'PHP', 'Java', 'C', 'C++', 'C#', 'Go', 'Rust', 'SQL', 'HTML/CSS', 'Vue', '其他'];
const language = ref('JavaScript');
const errorMsg = ref('');
const code = ref('');
const status = ref(null);
const history = ref([]);
const loading = ref(false);
const elapsed = ref(0);
const failure = ref('');
const result = ref(null);
const analyzedCode = ref('');
let abort, timer, poll;

const ollamaOk = computed(() => !!(status.value?.ollama.online && status.value.ollama.model_ready));
const dbOk = computed(() => !!status.value?.history);
const canRun = computed(() => code.value.trim() && !loading.value && ollamaOk.value);

async function refreshStatus() {
    try {
        const first = !status.value;
        status.value = await api.status();
        if (status.value.history && (first || !history.value.length)) loadHistory();
    } catch {
        status.value = { ollama: { online: false, model: '', model_ready: false }, history: false };
    }
}
async function loadHistory() {
    try { history.value = await api.history(); } catch {}
}
function loadSample() {
    const s = samples[language.value] ?? samples.JavaScript;
    code.value = s.code;
    errorMsg.value = s.error;
}
async function run() {
    failure.value = '';
    loading.value = true;
    elapsed.value = 0;
    abort = new AbortController();
    timer = setInterval(() => elapsed.value++, 1000);
    try {
        const r = await api.analyze({ language: language.value, code: code.value, error_message: errorMsg.value || null }, abort.signal);
        analyzedCode.value = code.value;
        result.value = r.result;
        if (r.id) loadHistory();
        await nextTick();
        document.getElementById('result')?.scrollIntoView({ behavior: 'smooth' });
    } catch (e) {
        if (e.name !== 'AbortError') failure.value = e.message;
    } finally {
        clearInterval(timer);
        loading.value = false;
    }
}
const cancel = () => abort?.abort();
async function openHistory(id) {
    const a = await api.show(id);
    language.value = a.language;
    code.value = analyzedCode.value = a.code;
    errorMsg.value = a.error_message ?? '';
    result.value = a.result;
    await nextTick();
    document.getElementById('result')?.scrollIntoView({ behavior: 'smooth' });
}
async function removeHistory(id) {
    await api.remove(id);
    loadHistory();
}

onMounted(() => { refreshStatus(); poll = setInterval(refreshStatus, 10000); });
onUnmounted(() => clearInterval(poll));
</script>

<template>
    <main class="mx-auto max-w-[1180px] px-5 pb-24 pt-16">
        <p class="cp-kicker">// NIGHT_CITY_DEV_TERMINAL v1.0</p>
        <h1 class="cp-title mt-4">CODE://MASTER</h1>
        <p class="mt-5 max-w-[880px] text-lg leading-relaxed text-slate-300">
            貼上出問題的程式碼，本地 AI 會診斷錯誤、逐步教你怎麼修，並解釋背後的語法概念 – 全程在你自己的機器上跑，不連外部雲端。
        </p>

        <div class="cp-status mt-8">
            <span><i class="dot" :class="ollamaOk ? 'on' : 'off'"></i>本地 AI（{{ status?.ollama.model || '未設定' }}）：<b :class="ollamaOk ? 't-on' : 't-off'">{{ ollamaOk ? '在線' : '離線' }}</b></span>
            <span><i class="dot" :class="dbOk ? 'on' : 'off'"></i>歷史紀錄（MYSQL）：<b :class="dbOk ? 't-on' : ''">{{ dbOk ? '已連線' : '未啟用' }}</b></span>
        </div>

        <div v-if="status && !status.ollama.online" class="cp-alert mt-10" role="alert">
            <span>⚠</span>
            <p>偵測不到本地 <b>Ollama</b> 服務。請在終端機執行 <code>ollama serve</code>，並確認模型已下載（<code>ollama pull {{ status.ollama.model }}</code>）。</p>
        </div>
        <div v-else-if="status && !status.ollama.model_ready" class="cp-alert mt-10" role="alert">
            <span>⚠</span>
            <p>Ollama 已啟動，但找不到模型。請執行 <code>ollama pull {{ status.ollama.model }}</code>。</p>
        </div>

        <section class="cp-panel mt-10">
            <div class="flex items-center justify-between">
                <h2 class="cp-h2">[ SOURCE_INPUT ]</h2>
                <button class="cp-link" @click="loadSample">載入範例程式碼</button>
            </div>
            <div class="mt-5 grid gap-4 md:grid-cols-[270px_1fr]">
                <label class="cp-label">語言
                    <select v-model="language" class="cp-field"><option v-for="l in languages" :key="l">{{ l }}</option></select>
                </label>
                <label class="cp-label">錯誤訊息（選填，直接貼上 CONSOLE / TERMINAL 看到的錯誤）
                    <input v-model="errorMsg" class="cp-field" placeholder="例如：Uncaught TypeError: Cannot read properties of undefined..." />
                </label>
            </div>
            <label class="cp-label mt-5">貼上你的程式碼
                <textarea v-model="code" class="cp-field" spellcheck="false" placeholder="// 在這裡貼上出問題的程式碼片段..."></textarea>
            </label>
            <div class="mt-5 flex flex-wrap items-center gap-4">
                <button class="cp-btn" :disabled="!canRun" @click="run">{{ loading ? '[ ANALYZING... ]' : '[ RUN_DIAGNOSIS ]' }}</button>
                <button v-if="loading" class="cp-link" @click="cancel">取消</button>
                <span v-if="loading" class="cp-mono text-sm text-cyan-200">已等待 {{ elapsed }} 秒（模型首次載入可能需要 1–2 分鐘）</span>
            </div>
            <p v-if="failure" class="cp-error" role="alert">{{ failure }}</p>
        </section>

        <div id="result">
            <Result v-if="result" :result="result" :code="analyzedCode" />
        </div>

        <section v-if="dbOk && history.length" class="cp-panel mt-8">
            <h2 class="cp-h2">[ HISTORY ]</h2>
            <ul class="mt-4 space-y-2">
                <li v-for="h in history" :key="h.id" class="flex items-center gap-3">
                    <button class="cp-mono flex-1 truncate text-left text-sm text-cyan-100 hover:text-white" @click="openHistory(h.id)">
                        <span class="cp-chip">{{ h.language }}</span>{{ h.preview }} <span class="text-slate-400">{{ h.created_at }}</span>
                    </button>
                    <button class="cp-link" @click="removeHistory(h.id)">刪除</button>
                </li>
            </ul>
        </section>
    </main>
</template>
