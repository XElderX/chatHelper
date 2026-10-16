import { spawn, spawnSync } from 'node:child_process';
import { mkdtempSync, writeFileSync, rmSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { randomBytes } from 'node:crypto';
import { createServer } from 'node:net';
if(existsSync('bootstrap/cache/config.php'))throw new Error('Run php artisan config:clear before integration tests to avoid cached database configuration.');
const folder=mkdtempSync(join(tmpdir(),'chathelper-test-'));
const database=join(folder,'database.sqlite');writeFileSync(database,'');
const reservation=createServer();await new Promise(resolve=>reservation.listen(0,'127.0.0.1',resolve));
const port=reservation.address().port;await new Promise(resolve=>reservation.close(resolve));
const env={...process.env,APP_ENV:'local',APP_KEY:'base64:'+randomBytes(32).toString('base64'),APP_DEBUG:'false',DB_CONNECTION:'sqlite',DB_DATABASE:database,DB_URL:'',AI_PROVIDER:'mock',CACHE_STORE:'array',SESSION_DRIVER:'file'};
let server;
try{
 const migrate=spawnSync('php',['artisan','migrate','--force'],{env,stdio:'inherit'});if(migrate.status!==0)throw new Error('Integration database migration failed.');
 server=spawn('php',['artisan','serve','--no-reload','--host=127.0.0.1',`--port=${port}`],{env,stdio:'ignore'});
 server.on('error',error=>console.error(error.message));
 const base=`http://127.0.0.1:${port}`;let ready=false;
 for(let i=0;i<40;i++){
  if(server.exitCode!==null)throw new Error('Integration server failed to start.');
  try{const response=await fetch(base);if(response.ok && (await response.text()).includes('csrf-token')){ready=true;break;}}catch{}
  await new Promise(resolve=>setTimeout(resolve,100));
 }
 if(!ready)throw new Error('Integration server did not become ready.');
 const tests=spawn(process.execPath,['node_modules/vitest/vitest.mjs','run','tests/frontend/workflow.test.js'],{env:{...env,INTEGRATION_URL:base},stdio:'inherit'});
 process.exitCode=await new Promise(resolve=>tests.on('exit',code=>resolve(code??1)));
}finally{
 if(server&&server.exitCode===null){server.kill('SIGTERM');await new Promise(resolve=>{server.once('exit',resolve);setTimeout(resolve,2000).unref();});}
 rmSync(folder,{recursive:true,force:true});
}
