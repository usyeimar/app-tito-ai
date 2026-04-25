import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import { useEditorContext } from '../providers/editor-context';
import { SLASH_COMMAND_KEY } from '../extensions/slash-commands';
import { slashCommandItems, type SlashCommandItem } from '../model/slash-command-items';

export function SlashCommandMenu() {
    const { editor } = useEditorContext();
    const [isOpen, setIsOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [range, setRange] = useState<{ from: number; to: number } | null>(null);
    const [selectedIndex, setSelectedIndex] = useState(0);
    const [coords, setCoords] = useState<{ top: number; left: number } | null>(null);
    const menuRef = useRef<HTMLDivElement>(null);

    const filtered = useMemo(() => {
        if (!query) return slashCommandItems;
        const q = query.toLowerCase();
        return slashCommandItems.filter(
            (item) => item.title.toLowerCase().includes(q) || item.description.toLowerCase().includes(q),
        );
    }, [query]);

    const executeCommand = useCallback(
        (item: SlashCommandItem) => {
            if (!editor || !range) return;
            // Delete the slash + query text
            editor.chain().focus().deleteRange(range).run();
            item.command(editor);
            setIsOpen(false);
        },
        [editor, range],
    );

    // Listen for slash-command events from the ProseMirror plugin
    useEffect(() => {
        if (!editor) return;

        const dom = editor.view.dom;

        const handleSlashCommand = (e: Event) => {
            const detail = (e as CustomEvent).detail;

            if (detail.type === 'update') {
                setQuery(detail.query ?? '');
                if (detail.range) setRange(detail.range);
                return;
            }

            switch (detail.type) {
                case 'ArrowDown':
                    setSelectedIndex((prev) => (prev + 1) % Math.max(filtered.length, 1));
                    break;
                case 'ArrowUp':
                    setSelectedIndex((prev) => (prev - 1 + Math.max(filtered.length, 1)) % Math.max(filtered.length, 1));
                    break;
                case 'Enter':
                    if (filtered[selectedIndex]) {
                        executeCommand(filtered[selectedIndex]);
                    }
                    break;
                case 'Escape':
                    setIsOpen(false);
                    editor.commands.focus();
                    break;
            }
        };

        dom.addEventListener('slash-command', handleSlashCommand);
        return () => dom.removeEventListener('slash-command', handleSlashCommand);
    }, [editor, filtered, selectedIndex, executeCommand]);

    // Watch plugin state for open/close
    useEffect(() => {
        if (!editor) return;

        const handleTransaction = () => {
            const pluginState = SLASH_COMMAND_KEY.getState(editor.state);
            if (!pluginState) return;

            if (pluginState.isOpen && !isOpen) {
                setIsOpen(true);
                setQuery(pluginState.query);
                setRange(pluginState.range);
                setSelectedIndex(0);

                // Calculate position
                if (pluginState.range) {
                    const coordsAtPos = editor.view.coordsAtPos(pluginState.range.from);
                    const editorRect = editor.view.dom.closest('.rounded-md')?.getBoundingClientRect();
                    if (editorRect) {
                        setCoords({
                            top: coordsAtPos.bottom - editorRect.top + 4,
                            left: coordsAtPos.left - editorRect.left,
                        });
                    }
                }
            } else if (!pluginState.isOpen && isOpen) {
                setIsOpen(false);
            }

            if (pluginState.isOpen) {
                setQuery(pluginState.query);
                setRange(pluginState.range);
            }
        };

        editor.on('transaction', handleTransaction);
        return () => {
            editor.off('transaction', handleTransaction);
        };
    }, [editor, isOpen]);

    // Reset selected index when filtered items change
    useEffect(() => {
        setSelectedIndex(0);
    }, [filtered.length]);

    // Scroll selected item into view
    useEffect(() => {
        if (!menuRef.current) return;
        const selected = menuRef.current.querySelector('[data-selected="true"]');
        selected?.scrollIntoView({ block: 'nearest' });
    }, [selectedIndex]);

    if (!isOpen || !coords || filtered.length === 0) return null;

    return (
        <div
            ref={menuRef}
            className="absolute z-50 w-56 overflow-hidden rounded-lg border border-border bg-popover shadow-md"
            style={{ top: coords.top, left: coords.left }}
        >
            <div className="max-h-64 overflow-y-auto p-1">
                {filtered.map((item, index) => (
                    <button
                        key={item.title}
                        type="button"
                        data-selected={index === selectedIndex}
                        className={cn(
                            'flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-left text-sm transition-colors',
                            index === selectedIndex ? 'bg-accent text-accent-foreground' : 'hover:bg-muted',
                        )}
                        onMouseEnter={() => setSelectedIndex(index)}
                        onClick={() => executeCommand(item)}
                    >
                        <div className="flex size-8 shrink-0 items-center justify-center rounded-md border border-border bg-background">
                            <item.icon className="size-4 text-muted-foreground" />
                        </div>
                        <div>
                            <div className="font-medium">{item.title}</div>
                            <div className="text-xs text-muted-foreground">{item.description}</div>
                        </div>
                    </button>
                ))}
            </div>
        </div>
    );
}
