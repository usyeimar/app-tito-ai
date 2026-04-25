import { createContext, useContext } from 'react';
import type { Editor } from '@tiptap/react';
import type { EditorSaveStatus } from '../contracts/editor-contracts';

export type EditorContextValue = {
    editor: Editor | null;
    saveStatus: EditorSaveStatus;
};

export const EditorContext = createContext<EditorContextValue>({
    editor: null,
    saveStatus: 'saved',
});

export function useEditorContext() {
    return useContext(EditorContext);
}
