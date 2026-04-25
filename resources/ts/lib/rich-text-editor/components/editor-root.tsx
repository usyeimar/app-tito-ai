import { useMemo } from 'react';
import { useEditor } from '@tiptap/react';
import { cn } from '@/lib/utils';
import { configureExtensions } from '../extensions/configure-extensions';
import { EditorContext } from '../providers/editor-context';
import { EditorToolbar } from './editor-toolbar';
import { EditorContentArea } from './editor-content-area';
import { SlashCommandMenu } from './slash-command-menu';
import { ToolbarLinkPopover } from './toolbar-link-popover';

type EditorRootProps = {
    content?: string;
    onUpdate?: (text: string) => void;
    onUpdateHtml?: (html: string) => void;
    placeholder?: string;
    className?: string;
    minHeight?: string;
    children?: React.ReactNode;
};

function EditorRoot({
    content,
    onUpdate,
    onUpdateHtml,
    placeholder,
    className,
    minHeight,
    children,
}: EditorRootProps) {
    const extensions = useMemo(() => configureExtensions({ placeholder }), [placeholder]);

    const editor = useEditor({
        immediatelyRender: false,
        shouldRerenderOnTransaction: false,
        extensions,
        content,
        onUpdate: ({ editor: e }) => {
            onUpdate?.(e.getText());
            onUpdateHtml?.(e.getHTML());
        },
        editorProps: {
            attributes: {
                class: cn(
                    'prose prose-sm dark:prose-invert max-w-none focus:outline-none p-3',
                    '[&_ul]:list-disc [&_ol]:list-decimal [&_li]:ml-4',
                    '[&_ul[data-type=taskList]]:list-none [&_ul[data-type=taskList]_li]:ml-0',
                ),
            },
        },
    });

    const ctx = useMemo(() => ({ editor, saveStatus: 'saved' as const }), [editor]);

    return (
        <EditorContext.Provider value={ctx}>
            <div className={cn('relative rounded-md border border-border bg-background overflow-hidden', className)}>
                {children ?? (
                    <>
                        <div className="relative">
                            <EditorToolbar />
                            <ToolbarLinkPopover />
                        </div>
                        <EditorContentArea minHeight={minHeight} />
                        <SlashCommandMenu />
                    </>
                )}
            </div>
        </EditorContext.Provider>
    );
}

export const RichTextEditor = {
    Root: EditorRoot,
    Toolbar: EditorToolbar,
    Content: EditorContentArea,
};
