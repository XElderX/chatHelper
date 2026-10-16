import { test,expect } from 'vitest';
import { createApp,nextTick } from 'vue';
import { transferableAbortController } from 'node:util';
import App from '../../resources/js/App.vue';
const run=process.env.INTEGRATION_URL?test:test.skip;
run('Vue → Laravel: create, analyze, accept, autosave, reopen and rewrite',async()=>{
 const base=process.env.INTEGRATION_URL, nativeFetch=globalThis.fetch;
 const rejected=await nativeFetch(base+'/api/documents',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json'},body:JSON.stringify({title:'No token'})});expect(rejected.status).toBe(419);
 const response=await nativeFetch(base);const html=await response.text();
 const csrf=html.match(/name="csrf-token" content="([^"]+)"/)[1];
 const cookies=response.headers.getSetCookie().map(c=>c.split(';')[0]).join('; ');
 document.head.innerHTML=`<meta name="csrf-token" content="${csrf}">`;
 document.body.innerHTML='<div id="mount"></div>';
 globalThis.fetch=(path,options={})=>{const controller=transferableAbortController(); if(options.signal?.aborted)controller.abort(); options.signal?.addEventListener('abort',()=>controller.abort(),{once:true});return nativeFetch(base+path,{...options,signal:controller.signal,headers:{...options.headers,Cookie:cookies}});};
 await globalThis.fetch('/api/writing/settings',{method:'PUT',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({mode:'grammar',live_analysis:true,dark_mode:false})});
 const app=createApp(App);app.mount('#mount');
 async function until(fn){for(let i=0;i<150;i++){await nextTick();if(fn())return;await new Promise(r=>setTimeout(r,30));}throw new Error('Timed out: '+document.body.textContent.slice(-2000));}
 function button(text,root=document){return [...root.querySelectorAll('button')].find(b=>b.textContent.trim()===text);}
 try{
  await until(()=>button('Try the writing studio'));
  button('Try the writing studio').click();
  await until(()=>document.querySelector('.issue-spelling'));
  expect(document.querySelector('.writing-surface').textContent).toContain('I is excited');
  document.querySelector('.issue-spelling').dispatchEvent(new MouseEvent('click',{bubbles:true}));
  await until(()=>document.querySelector('.suggestion-popover'));
  button('Accept',document.querySelector('.suggestion-popover')).click();
  await until(()=>document.querySelector('.save-status').textContent.trim()==='Saved');
  expect(document.querySelector('.writing-surface').textContent).toContain('the first draft');
  const title=document.querySelector('[aria-label="Document title"]');title.value='Integration verified draft';title.dispatchEvent(new Event('input',{bubbles:true}));
  await until(()=>document.querySelector('.save-status').textContent.trim()==='Saved');
  const mode=document.querySelector('[aria-label="Writing mode"]');mode.value='natural';mode.dispatchEvent(new Event('change',{bubbles:true}));
  await until(()=>document.querySelector('.editor-footer').textContent.includes('Natural'));
  // Access the real ProseMirror editor via its DOM view; make a selection through its API.
  const view=document.querySelector('.writing-surface').editor.view;
  const {TextSelection}=await import('@tiptap/pm/state');
  view.dispatch(view.state.tr.setSelection(TextSelection.create(view.state.doc,1,view.state.doc.content.size-1)));
  await until(()=>!button('Rewrite selection').disabled);
  button('Rewrite selection').click();
  await until(()=>document.querySelector('.rewrite-modal'));
  button('Accept rewrite').click();
  await until(()=>document.querySelector('.save-status').textContent.trim()==='Saved');
  expect(document.querySelector('.writing-surface').textContent).toContain('I am excited');
  document.querySelector('[aria-label="Back to documents"]').click();
  await until(()=>document.querySelector('.doc-open'));
  const doc=[...document.querySelectorAll('.doc-open')].find(b=>b.textContent.includes('Integration verified draft'));doc.click();
  await until(()=>document.querySelector('.writing-surface'));
  expect(document.querySelector('.writing-surface').textContent).toContain('I am excited');
  button('Settings').click();await until(()=>document.querySelector('.settings-page'));
  const toggles=document.querySelectorAll('.settings-page input[type=checkbox]');toggles[1].click();await until(()=>document.documentElement.classList.contains('dark'));
  expect(document.querySelector('.error-banner')).toBeNull();
 }finally{app.unmount();globalThis.fetch=nativeFetch;document.documentElement.classList.remove('dark');}
},20000);
