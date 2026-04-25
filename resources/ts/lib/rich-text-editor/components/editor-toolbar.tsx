import {
    Bold,
    Italic,
    Underline,
    Strikethrough,
    Code,
    List,
    ListOrdered,
    ListTodo,
    Quote,
    AlignLeft,
    AlignCenter,
    AlignRight,
    Link,
    Minus,
    Undo2,
    Redo2,
    Heading1,
    Heading2,
    Heading3,
    Pilcrow,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { useToolbarState } from '../hooks/use-toolbar-state';
import { useEditorContext } from '../providers/editor-context';

function ToolbarButton({
    onClick,
    active,
    disabled,
    title,
    children,
}: {
    onClick: () => void;
    active?: boolean;
    disabled?: boolean;
    title: string;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            title={title}
            className={cn(
                'inline-flex size-7 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:pointer-events-none disabled:opacity-50',
                active && 'bg-muted text-foreground',
            )}
        >
            {children}
        </button>
    );
}

function Divider() {
    return <div className="mx-0.5 h-5 w-px bg-border" />;
}

export function EditorToolbar() {
    const { editor } = useEditorContext();
    const state = useToolbarState(editor);

    if (!editor || !state) return null;

    const chain = () => editor.chain().focus();

    return (
        <div className="flex flex-wrap items-center gap-0.5 border-b border-border bg-muted/50 px-2 py-1.5">
            {/* Block type */}
            <ToolbarButton
                onClick={() => chain().setParagraph().run()}
                active={!state.currentHeading && !state.isCodeBlock}
                title="Párrafo"
            >
                <Pilcrow className="size-3.5" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() => chain().toggleHeading({ level: 1 }).run()}
                active={state.currentHeading === 1}
                title="Título 1"
            >
                <Heading1 className="size-3.5" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() => chain().toggleHeading({ level: 2 }).run()}
                active={state.currentHeading === 2}
                title="Título 2"
            >
                <Heading2 className="size-3.5" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() => chain().toggleHeading({ level: 3 }).run()}
                active={state.currentHeading === 3}
                title="Título 3"
            >
                <Heading3 className="size-3.5" />
            </ToolbarButton>

            <Divider />

            {/* Inline formatting */}
            <ToolbarButton onClick={() => chain().toggleBold().run()} active={state.isBold} title="Negrita (Ctrl+B)">
                <Bold className="size-3.5" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() => chain().toggleItalic().run()}
                active={state.isItalic}
                title="Cursiva (Ctrl+I)"
            >
                <Italic className="size-3.5" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() => chain().toggleUnderline().run()}
                active={state.isUnderline}
                title="Subrayado (Ctrl+U)"
            >
                <Underline className="size-3.5" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() => chain().toggleStrike().run()}
                active={state.isStrike}
                title="Tachado"
            >
                <Strikethrough className="size-3.5" />
            </ToolbarButton>
            <ToolbarButton onClick={() => chain().toggleCode().run()} active={state.isCode} title="Código inline">
                <Code className="size-3.5" />
            </ToolbarButton>

            <Divider />

            {/* Lists */}
            <ToolbarButton
                onClick={() => chain().toggleBulletList().run()}
                active={state.isBulletList}
                title="Lista con viñetas"
            >
                <List className="size-3.5" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() => chain().toggleOrderedList().run()}
                active={state.isOrderedList}
                title="Lista numerada"
            >
                <ListOrdered className="size-3.5" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() => chain().toggleTaskList().run()}
                active={state.isTaskList}
                title="Lista de tareas"
            >
                <ListTodo className="size-3.5" />
            </ToolbarButton>

            <Divider />

            {/* Alignment */}
            <ToolbarButton
                onClick={() => chain().setTextAlign('left').run()}
                active={state.isAlignLeft}
                title="Alinear izquierda"
            >
                <AlignLeft className="size-3.5" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() => chain().setTextAlign('center').run()}
                active={state.isAlignCenter}
                title="Centrar"
            >
                <AlignCenter className="size-3.5" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() => chain().setTextAlign('right').run()}
                active={state.isAlignRight}
                title="Alinear derecha"
            >
                <AlignRight className="size-3.5" />
            </ToolbarButton>

            <Divider />

            {/* Block elements */}
            <ToolbarButton
                onClick={() => chain().toggleBlockquote().run()}
                active={state.isBlockquote}
                title="Cita"
            >
                <Quote className="size-3.5" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() => {
                    const event = new CustomEvent('editor-open-link');
                    editor.view.dom.dispatchEvent(event);
                }}
                active={state.isLink}
                title="Enlace (Ctrl+K)"
            >
                <Link className="size-3.5" />
            </ToolbarButton>
            <ToolbarButton onClick={() => chain().setHorizontalRule().run()} title="Línea horizontal">
                <Minus className="size-3.5" />
            </ToolbarButton>

            <Divider />

            {/* History */}
            <ToolbarButton
                onClick={() => chain().undo().run()}
                disabled={!state.canUndo}
                title="Deshacer (Ctrl+Z)"
            >
                <Undo2 className="size-3.5" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() => chain().redo().run()}
                disabled={!state.canRedo}
                title="Rehacer (Ctrl+Shift+Z)"
            >
                <Redo2 className="size-3.5" />
            </ToolbarButton>
        </div>
    );
}
