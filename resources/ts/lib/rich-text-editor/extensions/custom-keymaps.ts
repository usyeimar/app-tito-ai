import { Extension } from '@tiptap/core';

export const CustomKeymaps = Extension.create({
    name: 'customKeymaps',

    addKeyboardShortcuts() {
        return {
            'Mod-k': ({ editor }) => {
                const event = new CustomEvent('editor-open-link');
                editor.view.dom.dispatchEvent(event);
                return true;
            },
        };
    },
});
