import { describe,it,expect,afterEach } from 'vitest';
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { blocksFromDoc,resolveSuggestion,applySuggestion,selectionSnapshot,applyRewrite } from '../../resources/js/lib/anchors';
import { Suggestions,decorate,suggestionKey } from '../../resources/js/lib/suggestions';
let editor;
function make(content){editor=new Editor({element:document.createElement('div'),extensions:[StarterKit.configure({trailingNode:false}),Suggestions],content});return editor;}
function suggestion(original='teh',start=0){return {id:'a',block_id:'0',original,replacement:'the',start,end:start+original.length,revision:4,category:'spelling'};}
afterEach(()=>editor?.destroy());
describe('safe suggestion anchors',()=>{
 it('replaces only the selected repeated occurrence',()=>{make('<p>teh teh</p>');expect(applySuggestion(editor,suggestion('teh',4),4)).toBe(true);expect(editor.getText()).toBe('teh the');});
 it('handles emoji UTF-16 positions',()=>{make('<p>😊 teh</p>');expect(applySuggestion(editor,suggestion('teh',3),4)).toBe(true);expect(editor.getText()).toBe('😊 the');});
 it('handles combining accents',()=>{make('<p>é teh</p>');expect(applySuggestion(editor,suggestion('teh',3),4)).toBe(true);expect(editor.getText()).toBe('é the');});
 it('rejects stale revisions',()=>{make('<p>teh</p>');expect(applySuggestion(editor,suggestion(),5)).toBe(false);expect(editor.getText()).toBe('teh');});
 it('rejects changed or out-of-bounds anchors',()=>{make('<p>the</p>');expect(resolveSuggestion(editor.state.doc,suggestion(),4)).toBeNull();expect(resolveSuggestion(editor.state.doc,suggestion('teh',99),4)).toBeNull();});
 it('supports deletion replacements',()=>{make('<p>teh word</p>');applySuggestion(editor,{...suggestion(),replacement:''},4);expect(editor.getText()).toBe(' word');});
 it('does not parse replacements as HTML',()=>{make('<p>teh</p>');applySuggestion(editor,{...suggestion(),replacement:'<img src=x onerror=alert(1)>'},4);expect(editor.getHTML()).toContain('&lt;img');expect(editor.getHTML()).not.toContain('<img');});
 it('maps formatting, nested blocks and hard breaks',()=>{make('<ul><li><p><strong>Hi</strong><br>teh</p></li></ul>');const [b]=blocksFromDoc(editor.state.doc);expect(b.text).toBe('Hi\nteh');expect(applySuggestion(editor,{...suggestion('teh',3),block_id:b.id},4)).toBe(true);expect(editor.getText()).toContain('the');});
 it('preserves bold formatting when replacing',()=>{make('<p><strong>teh</strong> cat</p>');applySuggestion(editor,suggestion(),4);expect(editor.getHTML()).toContain('<strong>the</strong>');});
 it('creates clickable decorations and clears them on edit',()=>{make('<p>teh</p>');decorate(editor,[suggestion()],4);expect(suggestionKey.getState(editor.state).find()).toHaveLength(1);editor.commands.insertContent('x');expect(suggestionKey.getState(editor.state).find()).toHaveLength(0);});
 it('undo restores accepted text',()=>{make('<p>teh</p>');applySuggestion(editor,suggestion(),4);editor.commands.undo();expect(editor.getText()).toBe('teh');});
 it('empty documents produce an empty block',()=>{make('<p></p>');expect(blocksFromDoc(editor.state.doc)[0].text).toBe('');});
});
describe('rewrite selection',()=>{
 it('applies only the snapshot selection',()=>{make('<p>Hello teh world.</p>');editor.commands.setTextSelection({from:7,to:10});const snap=selectionSnapshot(editor,4);expect(snap.text).toBe('teh');expect(applyRewrite(editor,snap,'the',4)).toBe(true);expect(editor.getText()).toBe('Hello the world.');});
 it('rejects stale rewrite snapshots',()=>{make('<p>teh</p>');editor.commands.setTextSelection({from:1,to:4});const snap=selectionSnapshot(editor,4);expect(applyRewrite(editor,snap,'the',5)).toBe(false);});
 it('rejects multi-paragraph selections',()=>{make('<p>one</p><p>two</p>');editor.commands.setTextSelection({from:1,to:8});expect(selectionSnapshot(editor,4)).toBeNull();});
});
