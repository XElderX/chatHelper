<script setup>
import { ref, watch } from 'vue';
import { api } from './lib/api';
const props=defineProps({meta:Object});
const emit=defineEmits(['changed']);
const busy=ref(false), error=ref(''), notice=ref(''), models=ref([]);
async function run(fn){busy.value=true;error.value='';try{await fn();}catch(e){error.value=e.message;}finally{busy.value=false;}}
async function select(provider,account_id=null){await run(async()=>{await api('connections',{method:'PUT',body:{provider,account_id}});models.value=[];emit('changed');});}
async function connect(account_id=null){await run(async()=>{const {url}=await api('connections/chatgpt/start',{method:'POST',body:{account_id}});window.location.assign(url);});}
async function loadModels(){await run(async()=>{const result=await api('connections/chatgpt/models?account_id='+props.meta.active_account_id);models.value=result.models;});}
async function chooseModel(model){if(!model)return;await run(async()=>{await api('connections/chatgpt/model',{method:'PUT',body:{account_id:props.meta.active_account_id,model}});emit('changed');});}
async function disconnect(id){await run(async()=>{const result=await api('connections/chatgpt/disconnect',{method:'POST',body:{account_id:id}});notice.value=result.message;models.value=[];emit('changed');});}
watch(()=>props.meta.active_account_id,()=>{models.value=[];});
</script>
<template>
<section class="settings-card connection-settings">
<h2>AI connection</h2>
<p>Choose how your AI requests are billed. chatHelper never switches billing methods automatically.</p>
<p v-if="meta.connection_notice" role="status">{{meta.connection_notice}}</p>
<p v-if="notice" role="status">{{notice}}</p><p v-if="error" class="connection-error" role="alert">{{error}}</p>
<fieldset :disabled="busy">
<div class="connection-option"><h3>OpenAI API key <span v-if="meta.provider==='openai'">· Active</span></h3><p>Separately billed API usage. Set <code>OPENAI_API_KEY</code> and <code>OPENAI_MODEL</code> in your server’s <code>.env</code> file.</p><p>{{meta.api_key_configured?'API key configured; connectivity not checked.':'No API key configured.'}}</p><button class="primary" @click="select('openai')">Use API key</button></div>
<div class="connection-option"><h3>ChatGPT subscription <span v-if="meta.provider==='chatgpt'">· Active</span></h3><p>Eligible subscription usage through the official OpenAI integration. Available models and limits depend on your account and authorization.</p><button class="primary" @click="connect()">Continue with ChatGPT</button>
<div class="connected-account" v-for="account in meta.accounts||[]" :key="account.id"><strong>{{account.label}}</strong><small>Registration {{account.client_id}} · {{account.connected?(account.plan_authorized?'Authorized':'Subscription permission required'):'Signed out'}}</small><div class="connection-actions"><button class="text-btn" v-if="account.connected&&account.plan_authorized" @click="select('chatgpt',account.id)">{{meta.provider==='chatgpt'&&meta.active_account_id===account.id?'Selected account':'Use this account'}}</button><button class="text-btn" @click="connect(account.id)">Reconnect</button><button class="text-btn" v-if="account.connected" @click="disconnect(account.id)">Sign out</button></div><p v-if="account.model">Model: {{account.model}}</p></div>
<div v-if="meta.provider==='chatgpt'&&meta.active_account_id" class="connection-actions"><button class="text-btn" @click="loadModels">Load available models</button><label v-if="models.length">ChatGPT model <select aria-label="ChatGPT model" :value="meta.accounts?.find(a=>a.id===meta.active_account_id)?.model||''" @change="chooseModel($event.target.value)"><option value="" disabled>Choose a model</option><option v-for="model in models" :key="model.id" :value="model.id">{{model.label}}</option></select></label></div>
<p>After connecting, load your available models and choose one. Review usage and access in <a href="https://chatgpt.com/#settings" target="_blank" rel="noopener noreferrer">ChatGPT Settings</a>.</p></div>
<div class="connection-option"><h3>Local demo <span v-if="meta.provider==='mock'">· Active</span></h3><p>Limited demonstration rules. No AI requests or charges.</p><button class="text-btn" @click="select('mock')">Use local demo</button></div>
</fieldset><p v-if="busy" role="status">Updating connection…</p>
<p>Credentials stay on this computer, encrypted on the server. AI requests send text to your selected provider. Turn off live analysis to stop automatic requests.</p>
</section>
</template>
<style scoped>
fieldset{border:0;padding:0;margin:0;min-width:0}.connection-option{border-top:1px solid var(--border,#ddd);padding:20px 0}.connection-option h3{margin:0}.connected-account{margin-top:18px;padding:14px;background:var(--bg,#f3f5f3);border-radius:10px}.connected-account small{display:block;overflow-wrap:anywhere;margin:6px 0}.connection-actions{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-top:12px}.connection-actions select{max-width:100%;padding:8px}.connection-error{color:#b33}a{text-decoration:underline}button{cursor:pointer}
</style>
