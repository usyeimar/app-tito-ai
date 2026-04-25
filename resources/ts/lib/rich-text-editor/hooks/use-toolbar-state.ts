import type { Editor } from '@tiptap/react';
import { useEditorState } from '@tiptap/react';

export type ToolbarState = {
    isBold: boolean;
    isItalic: boolean;
    isUnderline: boolean;
    isStrike: boolean;
    isCode: boolean;
    isBulletList: boolean;
    isOrderedList: boolean;
    isTaskList: boolean;
    isBlockquote: boolean;
    isCodeBlock: boolean;
    isAlignLeft: boolean;
    isAlignCenter: boolean;
    isAlignRight: boolean;
    isLink: boolean;
    currentHeading: number | null;
    canUndo: boolean;
    canRedo: boolean;
};

function shallowEqual(a: ToolbarState, b: ToolbarState | null): boolean {
    if (!b) return false;
    for (const key in a) {
        if (a[key as keyof ToolbarState] !== b[key as keyof ToolbarState]) return false;
    }
    return true;
}

export function useToolbarState(editor: Editor | null): ToolbarState | null {
    return useEditorState({
        editor,
        selector: ({ editor: e }) => {
            if (!e) return null;
            return {
                isBold: e.isActive('bold'),
                isItalic: e.isActive('italic'),
                isUnderline: e.isActive('underline'),
                isStrike: e.isActive('strike'),
                isCode: e.isActive('code'),
                isBulletList: e.isActive('bulletList'),
                isOrderedList: e.isActive('orderedList'),
                isTaskList: e.isActive('taskList'),
                isBlockquote: e.isActive('blockquote'),
                isCodeBlock: e.isActive('codeBlock'),
                isAlignLeft: e.isActive({ textAlign: 'left' }),
                isAlignCenter: e.isActive({ textAlign: 'center' }),
                isAlignRight: e.isActive({ textAlign: 'right' }),
                isLink: e.isActive('link'),
                currentHeading: e.isActive('heading', { level: 1 })
                    ? 1
                    : e.isActive('heading', { level: 2 })
                      ? 2
                      : e.isActive('heading', { level: 3 })
                        ? 3
                        : null,
                canUndo: e.can().undo(),
                canRedo: e.can().redo(),
            } satisfies ToolbarState;
        },
        equalityFn: shallowEqual,
    });
}
