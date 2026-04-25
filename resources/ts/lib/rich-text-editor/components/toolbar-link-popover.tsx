import { useCallback, useEffect, useState } from 'react';
import { ExternalLink, Unlink } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useEditorContext } from '../providers/editor-context';

export function ToolbarLinkPopover() {
    const { editor } = useEditorContext();
    const [isOpen, setIsOpen] = useState(false);
    const [url, setUrl] = useState('');

    const openPopover = useCallback(() => {
        if (!editor) return;
        const existing = editor.getAttributes('link').href ?? '';
        setUrl(existing);
        setIsOpen(true);
    }, [editor]);

    // Listen for Mod-k custom event
    useEffect(() => {
        if (!editor) return;
        const dom = editor.view.dom;
        const handler = () => openPopover();
        dom.addEventListener('editor-open-link', handler);
        return () => dom.removeEventListener('editor-open-link', handler);
    }, [editor, openPopover]);

    const setLink = useCallback(() => {
        if (!editor) return;
        if (url.trim()) {
            editor.chain().focus().setLink({ href: url.trim() }).run();
        }
        setIsOpen(false);
        setUrl('');
    }, [editor, url]);

    const unsetLink = useCallback(() => {
        if (!editor) return;
        editor.chain().focus().unsetLink().run();
        setIsOpen(false);
        setUrl('');
    }, [editor]);

    if (!isOpen) return null;

    return (
        <div className="absolute left-1/2 top-full z-50 mt-1 -translate-x-1/2">
            <div className="flex items-center gap-1.5 rounded-lg border border-border bg-popover p-2 shadow-md">
                <input
                    type="url"
                    value={url}
                    onChange={(e) => setUrl(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            setLink();
                        }
                        if (e.key === 'Escape') {
                            e.preventDefault();
                            setIsOpen(false);
                            editor?.commands.focus();
                        }
                    }}
                    placeholder="https://..."
                    className="h-7 w-56 rounded-md border border-input bg-background px-2 text-sm outline-none focus:ring-1 focus:ring-ring"
                    autoFocus
                />
                <button
                    type="button"
                    onClick={setLink}
                    className={cn(
                        'inline-flex size-7 items-center justify-center rounded-md text-sm transition-colors',
                        'bg-primary text-primary-foreground hover:bg-primary/90',
                    )}
                    title="Aplicar enlace"
                >
                    <ExternalLink className="size-3.5" />
                </button>
                <button
                    type="button"
                    onClick={unsetLink}
                    className="inline-flex size-7 items-center justify-center rounded-md text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    title="Quitar enlace"
                >
                    <Unlink className="size-3.5" />
                </button>
                <button
                    type="button"
                    onClick={() => {
                        setIsOpen(false);
                        editor?.commands.focus();
                    }}
                    className="inline-flex size-7 items-center justify-center rounded-md text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    title="Cancelar"
                >
                    ✕
                </button>
            </div>
        </div>
    );
}
