import type { Editor } from '@tiptap/react';
import {
    Heading1,
    Heading2,
    Heading3,
    List,
    ListOrdered,
    ListTodo,
    Quote,
    Code2,
    Minus,
    type LucideIcon,
} from 'lucide-react';

export type SlashCommandItem = {
    title: string;
    description: string;
    icon: LucideIcon;
    command: (editor: Editor) => void;
};

export const slashCommandItems: SlashCommandItem[] = [
    {
        title: 'Título 1',
        description: 'Encabezado grande',
        icon: Heading1,
        command: (editor) => editor.chain().focus().toggleHeading({ level: 1 }).run(),
    },
    {
        title: 'Título 2',
        description: 'Encabezado mediano',
        icon: Heading2,
        command: (editor) => editor.chain().focus().toggleHeading({ level: 2 }).run(),
    },
    {
        title: 'Título 3',
        description: 'Encabezado pequeño',
        icon: Heading3,
        command: (editor) => editor.chain().focus().toggleHeading({ level: 3 }).run(),
    },
    {
        title: 'Lista con viñetas',
        description: 'Lista desordenada',
        icon: List,
        command: (editor) => editor.chain().focus().toggleBulletList().run(),
    },
    {
        title: 'Lista numerada',
        description: 'Lista ordenada',
        icon: ListOrdered,
        command: (editor) => editor.chain().focus().toggleOrderedList().run(),
    },
    {
        title: 'Lista de tareas',
        description: 'Lista con checkboxes',
        icon: ListTodo,
        command: (editor) => editor.chain().focus().toggleTaskList().run(),
    },
    {
        title: 'Cita',
        description: 'Bloque de cita',
        icon: Quote,
        command: (editor) => editor.chain().focus().toggleBlockquote().run(),
    },
    {
        title: 'Código',
        description: 'Bloque de código',
        icon: Code2,
        command: (editor) => editor.chain().focus().toggleCodeBlock().run(),
    },
    {
        title: 'Separador',
        description: 'Línea horizontal',
        icon: Minus,
        command: (editor) => editor.chain().focus().setHorizontalRule().run(),
    },
];
