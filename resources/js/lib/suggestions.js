import { Extension } from '@tiptap/core';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import { Decoration, DecorationSet } from '@tiptap/pm/view';
import { resolveSuggestion } from './anchors';
export const suggestionKey=new PluginKey('writing-suggestions');
export const Suggestions=Extension.create({
  name:'writingSuggestions',
  addOptions(){return {onSelect:()=>{}};},
  addProseMirrorPlugins(){
    const onSelect=this.options.onSelect;
    return [new Plugin({key:suggestionKey,state:{init:()=>DecorationSet.empty,apply(tr,old){
      const meta=tr.getMeta(suggestionKey);
      if(meta){return DecorationSet.create(tr.doc,meta.items.flatMap(s=>{
        const range=resolveSuggestion(tr.doc,s,meta.revision);
        return range?[Decoration.inline(range.from,range.to,{class:'issue issue-'+s.category,'data-suggestion-id':s.id})]:[];
      }));}
      return tr.docChanged?DecorationSet.empty:old;
    }},props:{decorations(state){return this.getState(state);},handleDOMEvents:{click(view,event){const el=event.target.closest('[data-suggestion-id]');if(el){onSelect(el.dataset.suggestionId);return true;}return false;}}}})];
  },
});
export function decorate(editor,items,revision){editor.view.dispatch(editor.state.tr.setMeta(suggestionKey,{items,revision}));}
