import React from 'react';
import { Head, router } from '@inertiajs/react';
import { MoreHorizontal, Plus, Search, Sparkles, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { webGet, webPost, webDelete, TenantApiError } from '@/lib/tenant-api';
import { cn } from '@/lib/utils';
import type { Agent, TenantSummary } from '@/types/agent';
import type { BreadcrumbItem } from '@/types';

import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type Props = {
    tenant: TenantSummary;
};

export default function AgentsIndex({ tenant }: Props) {
    const [agents, setAgents] = React.useState<Agent[]>([]);
    const [search, setSearch] = React.useState('');
    const [createOpen, setCreateOpen] = React.useState(false);
    const [name, setName] = React.useState('');
    const [description, setDescription] = React.useState('');
    const [language, setLanguage] = React.useState('es-CO');
    const [submitting, setSubmitting] = React.useState(false);
    const [formError, setFormError] = React.useState<string | null>(null);

    const fetchAgents = React.useCallback(async (searchTerm?: string) => {
        try {
            const params = new URLSearchParams();
            if (searchTerm) params.set('filter[search]', searchTerm);
            const qs = params.toString();
            const result = await webGet<{ data: Agent[] }>(
                tenant.slug,
                `/agents${qs ? `?${qs}` : ''}`,
            );
            setAgents(result.data);
        } catch (err) {
            console.error('Failed to fetch agents', err);
        }
    }, [tenant.slug]);

    React.useEffect(() => {
        fetchAgents();
    }, [fetchAgents]);

    React.useEffect(() => {
        const timeout = setTimeout(() => {
            fetchAgents(search || undefined);
        }, 300);
        return () => clearTimeout(timeout);
    }, [search, fetchAgents]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: tenant.name, href: `/${tenant.slug}/agents` },
        { title: 'Asistentes', href: `/${tenant.slug}/agents` },
    ];

    const filtered = agents;

    const handleCreate = async (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        setFormError(null);
        try {
            const result = await webPost<{ data: Agent }>(
                tenant.slug,
                '/agents',
                {
                    name,
                    slug: null,
                    description: description || null,
                    language,
                },
            );
            setCreateOpen(false);
            setName('');
            setDescription('');
            // Navigate to the new agent
            router.visit(`/${tenant.slug}/agents/${result.data.id}`);
        } catch (err) {
            const message =
                err instanceof TenantApiError
                    ? err.message
                    : 'No se pudo crear el asistente';
            setFormError(message);
        } finally {
            setSubmitting(false);
        }
    };

    const handleDelete = async (agent: Agent) => {
        if (!confirm(`¿Eliminar el asistente "${agent.name}"?`)) return;
        try {
            await webDelete(tenant.slug, `/agents/${agent.id}`);
            router.visit(`/${tenant.slug}/agents`);
        } catch (err) {
            alert(
                err instanceof TenantApiError
                    ? err.message
                    : 'No se pudo eliminar el asistente',
            );
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Asistentes" />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <header className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Asistentes
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Crea y gestiona los asistentes de IA del workspace.
                        </p>
                    </div>
                    <Button
                        onClick={() => setCreateOpen(true)}
                        className="gap-2"
                    >
                        <Plus className="size-4" />
                        Nuevo asistente
                    </Button>
                </header>

                <div className="relative max-w-sm">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar por nombre o slug..."
                        className="pl-9"
                    />
                </div>

                {filtered.length === 0 ? (
                    <EmptyState onCreate={() => setCreateOpen(true)} />
                ) : (
                    <div className="rounded-xl border border-border bg-card shadow-sm">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[40%]">Nombre</TableHead>
                                    <TableHead>Slug</TableHead>
                                    <TableHead>Idioma</TableHead>
                                    <TableHead className="text-right">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filtered.map((agent) => (
                                    <TableRow key={agent.id} className="cursor-pointer" onClick={() => router.visit(`/${tenant.slug}/agents/${agent.id}`)}>
                                        <TableCell>
                                            <div className="flex items-center gap-3">
                                                <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                    <Sparkles className="size-4" />
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="truncate font-medium">{agent.name}</p>
                                                    <p className="truncate text-xs text-muted-foreground line-clamp-1 max-w-[200px]">
                                                        {agent.description || 'Sin descripción'}
                                                    </p>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-muted-foreground">
                                            {agent.slug}
                                        </TableCell>
                                        <TableCell>
                                            <span className="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-xs font-medium">
                                                {agent.language}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div onClick={(e) => e.stopPropagation()}>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        render={
                                                            <Button variant="ghost" size="icon" className="h-8 w-8">
                                                                <MoreHorizontal className="size-4" />
                                                            </Button>
                                                        }
                                                    />
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem onClick={() => handleDelete(agent)}>
                                                            <Trash2 className="mr-2 size-4" />
                                                            Eliminar
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>

            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Crear asistente</DialogTitle>
                        <DialogDescription>
                            Define un nombre y un idioma. Podrás configurar el
                            modelo y la voz desde el detalle del asistente.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={handleCreate}
                        className="mt-2 flex flex-col gap-4"
                    >
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="agent-name">Nombre</Label>
                            <Input
                                id="agent-name"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                placeholder="Ej: Asistente comercial"
                                autoFocus
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="agent-description">
                                Descripción
                            </Label>
                            <Textarea
                                id="agent-description"
                                value={description}
                                onChange={(e) => setDescription(e.target.value)}
                                placeholder="¿Para qué sirve este asistente?"
                                rows={3}
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="agent-language">Idioma</Label>
                            <Select
                                value={language}
                                onValueChange={setLanguage}
                            >
                                <SelectTrigger id="agent-language" className="w-full">
                                    <SelectValue placeholder="Selecciona un idioma" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="es-CO">Español (Colombia)</SelectItem>
                                    <SelectItem value="es-ES">Español (España)</SelectItem>
                                    <SelectItem value="es-MX">Español (México)</SelectItem>
                                    <SelectItem value="en-US">English (US)</SelectItem>
                                    <SelectItem value="pt-BR">Portugués (Brasil)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        {formError && (
                            <p className="text-xs text-destructive">
                                {formError}
                            </p>
                        )}
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCreateOpen(false)}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={submitting || !name.trim()}
                            >
                                {submitting ? 'Creando...' : 'Crear asistente'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

function EmptyState({ onCreate }: { onCreate: () => void }) {
    return (
        <div className="flex flex-1 flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-border bg-muted/20 p-12 text-center">
            <div className="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                <Sparkles className="size-6" />
            </div>
            <h2 className="text-base font-semibold">Aún no tienes asistentes</h2>
            <p className="max-w-sm text-sm text-muted-foreground">
                Crea tu primer asistente de IA para empezar a recibir y hacer
                llamadas desde la web.
            </p>
            <Button onClick={onCreate} className="mt-2 gap-2">
                <Plus className="size-4" />
                Crear primer asistente
            </Button>
        </div>
    );
}
