import { Extension } from '@tiptap/core';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import type { EditorView } from '@tiptap/pm/view';

export type SlashCommandState = {
    isOpen: boolean;
    query: string;
    range: { from: number; to: number } | null;
};

const SLASH_COMMAND_KEY = new PluginKey<SlashCommandState>('slashCommands');

function dispatchSlashEvent(view: EditorView, type: string, detail?: unknown) {
    const event = new CustomEvent('slash-command', { detail: { type, ...(detail as object) } });
    view.dom.dispatchEvent(event);
}

export const SlashCommands = Extension.create({
    name: 'slashCommands',

    addProseMirrorPlugins() {
        return [
            new Plugin<SlashCommandState>({
                key: SLASH_COMMAND_KEY,
                state: {
                    init: () => ({ isOpen: false, query: '', range: null }),
                    apply(tr, prev) {
                        const meta = tr.getMeta(SLASH_COMMAND_KEY);
                        if (meta) return meta;
                        if (!prev.isOpen) return prev;
                        // Close if selection changed externally
                        if (!tr.docChanged && tr.selectionSet) {
                            return { isOpen: false, query: '', range: null };
                        }
                        return prev;
                    },
                },
                props: {
                    handleKeyDown(view, event) {
                        const state = SLASH_COMMAND_KEY.getState(view.state);
                        if (!state?.isOpen) return false;

                        if (['ArrowDown', 'ArrowUp', 'Enter', 'Escape'].includes(event.key)) {
                            event.preventDefault();
                            dispatchSlashEvent(view, event.key);
                            return true;
                        }
                        return false;
                    },
                    handleTextInput(view, from, to, text) {
                        const state = SLASH_COMMAND_KEY.getState(view.state);

                        if (text === '/') {
                            // Check if at start of line or after whitespace
                            const $pos = view.state.doc.resolve(from);
                            const textBefore = $pos.parent.textBetween(0, $pos.parentOffset, undefined, '\ufffc');
                            if (textBefore === '' || textBefore.endsWith(' ')) {
                                const tr = view.state.tr.setMeta(SLASH_COMMAND_KEY, {
                                    isOpen: true,
                                    query: '',
                                    range: { from: from, to: to + 1 },
                                } satisfies SlashCommandState);
                                view.dispatch(tr);
                                return false; // Let the "/" be typed
                            }
                        }

                        if (state?.isOpen && state.range) {
                            // Update query as user types after "/"
                            requestAnimationFrame(() => {
                                const { state: newState } = view;
                                const $pos = newState.doc.resolve(newState.selection.from);
                                const textBefore = $pos.parent.textBetween(
                                    0,
                                    $pos.parentOffset,
                                    undefined,
                                    '\ufffc',
                                );
                                const slashIdx = textBefore.lastIndexOf('/');
                                if (slashIdx === -1) {
                                    const tr = newState.tr.setMeta(SLASH_COMMAND_KEY, {
                                        isOpen: false,
                                        query: '',
                                        range: null,
                                    } satisfies SlashCommandState);
                                    view.dispatch(tr);
                                    return;
                                }
                                const query = textBefore.slice(slashIdx + 1);
                                const absoluteFrom = $pos.start() + slashIdx;
                                const tr = newState.tr.setMeta(SLASH_COMMAND_KEY, {
                                    isOpen: true,
                                    query,
                                    range: { from: absoluteFrom, to: newState.selection.from },
                                } satisfies SlashCommandState);
                                view.dispatch(tr);
                            });
                        }

                        return false;
                    },
                },
                view() {
                    return {
                        update(view) {
                            const state = SLASH_COMMAND_KEY.getState(view.state);
                            if (!state?.isOpen) return;

                            // Recalculate query on doc changes (e.g. backspace)
                            const { selection } = view.state;
                            const $pos = view.state.doc.resolve(selection.from);
                            const textBefore = $pos.parent.textBetween(
                                0,
                                $pos.parentOffset,
                                undefined,
                                '\ufffc',
                            );
                            const slashIdx = textBefore.lastIndexOf('/');
                            if (slashIdx === -1) {
                                const tr = view.state.tr.setMeta(SLASH_COMMAND_KEY, {
                                    isOpen: false,
                                    query: '',
                                    range: null,
                                } satisfies SlashCommandState);
                                view.dispatch(tr);
                                return;
                            }
                            const query = textBefore.slice(slashIdx + 1);
                            const absoluteFrom = $pos.start() + slashIdx;
                            dispatchSlashEvent(view, 'update', {
                                query,
                                range: { from: absoluteFrom, to: selection.from },
                            });
                        },
                    };
                },
            }),
        ];
    },
});

export { SLASH_COMMAND_KEY };
