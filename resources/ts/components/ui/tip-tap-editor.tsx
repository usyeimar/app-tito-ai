import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import { cn } from '@/lib/utils';

type Props = {
    content: string;
    onChange: (content: string) => void;
    placeholder?: string;
    className?: string;
    minHeight?: string;
};

export function TipTapEditor({
    content,
    onChange,
    placeholder = 'Escribe algo...',
    className,
    minHeight = '200px',
}: Props) {
    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: false,
                codeBlock: false,
                blockquote: false,
                horizontalRule: false,
            }),
            Placeholder.configure({
                placeholder,
            }),
        ],
        content,
        onUpdate: ({ editor }) => {
            onChange(editor.getText());
        },
        editorProps: {
            attributes: {
                class: cn(
                    ' prose prose-sm dark:prose-invert max-w-none focus:outline-none p-3',
                    '[&_ul]:list-disc [&_ol]:list-decimal [&_li]:ml-4',
                ),
            },
        },
    });

    return (
        <div
            className={cn(
                'rounded-md border border-border bg-background overflow-hidden',
                className,
            )}
        >
            <div
                className="border-b border-border bg-muted/50 px-3 py-2 flex items-center gap-2"
            >
                <button
                    type="button"
                    onClick={() => editor?.chain().focus().toggleBold().run()}
                    className={cn(
                        'size-7 rounded text-sm font-semibold hover:bg-muted',
                        editor?.isActive('bold') && 'bg-muted',
                    )}
                >
                    B
                </button>
                <button
                    type="button"
                    onClick={() => editor?.chain().focus().toggleItalic().run()}
                    className={cn(
                        'size-7 rounded text-sm italic hover:bg-muted',
                        editor?.isActive('italic') && 'bg-muted',
                    )}
                >
                    I
                </button>
                <button
                    type="button"
                    onClick={() => editor?.chain().focus().toggleBulletList().run()}
                    className={cn(
                        'size-7 rounded text-xs font-medium hover:bg-muted',
                        editor?.isActive('bulletList') && 'bg-muted',
                    )}
                >
                    •
                </button>
                <button
                    type="button"
                    onClick={() => editor?.chain().focus().toggleOrderedList().run()}
                    className={cn(
                        'size-7 rounded text-xs font-medium hover:bg-muted',
                        editor?.isActive('orderedList') && 'bg-muted',
                    )}
                >
                    1.
                </button>
            </div>
            <EditorContent
                editor={editor}
                className="overflow-y-auto"
                style={{ minHeight }}
            />
        </div>
    );
}