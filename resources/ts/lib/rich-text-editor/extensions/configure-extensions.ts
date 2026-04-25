import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import { TextStyle } from '@tiptap/extension-text-style';
import Color from '@tiptap/extension-color';
import Highlight from '@tiptap/extension-highlight';
import TextAlign from '@tiptap/extension-text-align';
import TaskList from '@tiptap/extension-task-list';
import TaskItem from '@tiptap/extension-task-item';
import type { Extensions } from '@tiptap/react';
import { SlashCommands } from './slash-commands';
import { CustomKeymaps } from './custom-keymaps';

export function configureExtensions({
    placeholder = 'Escribe algo...',
}: {
    placeholder?: string;
} = {}): Extensions {
    return [
        StarterKit.configure({
            heading: { levels: [1, 2, 3] },
            codeBlock: true,
            blockquote: true,
            horizontalRule: true,
        }),
        Placeholder.configure({ placeholder }),
        Underline,
        Link.configure({ openOnClick: false, autolink: true, linkOnPaste: true }),
        TextStyle,
        Color,
        Highlight.configure({ multicolor: true }),
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        TaskList,
        TaskItem.configure({ nested: true }),
        SlashCommands,
        CustomKeymaps,
    ];
}
