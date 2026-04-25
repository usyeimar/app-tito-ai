import { EditorContent } from '@tiptap/react';
import { cn } from '@/lib/utils';
import { useEditorContext } from '../providers/editor-context';

export function EditorContentArea({
    className,
    minHeight = '200px',
}: {
    className?: string;
    minHeight?: string;
}) {
    const { editor } = useEditorContext();

    return (
        <EditorContent
            editor={editor}
            className={cn('overflow-y-auto', className)}
            style={{ minHeight }}
        />
    );
}
