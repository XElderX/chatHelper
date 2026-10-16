// ProseMirror and JavaScript both use UTF-16 code-unit positions.
export function blocksFromDoc(doc) {
  const blocks=[];
  doc.descendants((node,pos)=>{
    if(node.isTextblock){
      blocks.push({id:String(pos),text:node.textBetween(0,node.content.size,'','\n'),pos:pos+1});
      return false;
    }
  });
  return blocks;
}
export function resolveSuggestion(doc,s,revision) {
  if(s.revision!==revision || !Number.isInteger(s.start) || !Number.isInteger(s.end)) return null;
  const block=blocksFromDoc(doc).find(b=>b.id===s.block_id);
  if(!block || s.start<0 || s.end<=s.start || s.end>block.text.length || block.text.slice(s.start,s.end)!==s.original) return null;
  return {from:block.pos+s.start,to:block.pos+s.end};
}
export function applySuggestion(editor,s,revision) {
  const range=resolveSuggestion(editor.state.doc,s,revision);
  if(!range) return false;
  // insertText treats model output as plain text, never HTML.
  editor.view.dispatch(editor.state.tr.insertText(s.replacement,range.from,range.to));
  return true;
}
export function selectionSnapshot(editor,revision) {
  const {from,to,$from,$to}=editor.state.selection;
  if(from===to || !$from.sameParent($to) || !$from.parent.isTextblock) return null;
  return {from,to,revision,text:editor.state.doc.textBetween(from,to,'','\n')};
}
export function applyRewrite(editor,snapshot,replacement,revision) {
  if(snapshot.revision!==revision || editor.state.doc.textBetween(snapshot.from,snapshot.to,'','\n')!==snapshot.text) return false;
  editor.view.dispatch(editor.state.tr.insertText(replacement,snapshot.from,snapshot.to));
  return true;
}
