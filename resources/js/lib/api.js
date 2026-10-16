export async function api(path, {method='GET', body, signal}={}) {
  const response=await fetch('/api/'+path,{method,signal,credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:body===undefined?undefined:JSON.stringify(body)});
  if(!response.ok){const data=await response.json().catch(()=>({})); throw new Error(response.status===419?'Your session expired. Copy any unsaved text, then reload.':data.message||`Request failed (${response.status}).`);}
  return response.status===204?null:response.json();
}
