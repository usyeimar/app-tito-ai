import { useState, useEffect, useMemo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowUpRight,
    BarChart3,
    Bot,
    Brain,
    Calendar,
    Clock,
    ExternalLink,
    FileText,
    Globe,
    Grid3X3,
    Headphones,
    HelpCircle,
    Import,
    LayoutGrid,
    ListFilter,
    Menu,
    MessageSquare,
    Mic,
    PanelRight,
    Pen,
    Phone,
    PhoneForwarded,
    PhoneIncoming,
    PhoneOff,
    Play,
    Plus,
    Save,
    Search,
    Settings,
    Share2,
    Sparkles,
    Timer,
    Trash2,
    User,
    Volume2,
    Webhook,
    Wrench,
    X,
    Zap,
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { Slider } from '@/components/ui/slider';
import { Switch } from '@/components/ui/switch';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { AgentTestCall } from '@/components/agents/test-call';
import { tenantApi, TenantApiError } from '@/lib/tenant-api';
import { cn } from '@/lib/utils';
import type { Agent, TenantSummary } from '@/types/agent';
import type { BreadcrumbItem } from '@/types';

type Props = {
    tenant: TenantSummary;
    agent: Agent | null;
    agents: Agent[];
};

const COST_PER_MINUTE = 0.098;

export default function AgentShow({
    tenant,
    agent: initialAgent,
    agents,
}: Props) {
    const [agent, setAgent] = useState<Agent | null>(initialAgent ?? agents[0] ?? null);
    const [activeTab, setActiveTab] = useState('agent');
    const [searchQuery, setSearchQuery] = useState('');
    const [isSaving, setIsSaving] = useState(false);
    const [leftSheetOpen, setLeftSheetOpen] = useState(false);
    const [rightSheetOpen, setRightSheetOpen] = useState(false);
    const [testCallOpen, setTestCallOpen] = useState(false);
    const [newAgentOpen, setNewAgentOpen] = useState(false);
    const [newAgentMode, setNewAgentMode] = useState<'auto' | 'prebuilt'>('auto');
    const [newAgentForm, setNewAgentForm] = useState({
        name: '',
        languages: [] as string[],
        objective: '',
        next_steps: '',
        faqs: '',
    });
    const [selectedTemplate, setSelectedTemplate] = useState<string | null>(null);
    const [isCreating, setIsCreating] = useState(false);

    useEffect(() => {
        setAgent(initialAgent ?? agents[0] ?? null);
    }, [initialAgent, agents]);

    const filteredAgents = useMemo(() => {
        if (!searchQuery.trim()) return agents;
        const query = searchQuery.toLowerCase();
        return agents.filter(
            (a) =>
                a.name.toLowerCase().includes(query) ||
                a.slug.toLowerCase().includes(query),
        );
    }, [agents, searchQuery]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: tenant.name, href: `/${tenant.slug}/dashboard` },
        { title: 'Agent Setup', href: `/${tenant.slug}/agents` },
        ...(agent
            ? [
                {
                    title: agent.name,
                    href: `/${tenant.slug}/agents/${agent.id}`,
                },
            ]
            : []),
    ];

    const handleSave = async () => {
        if (!agent) return;
        setIsSaving(true);
        try {
            const result = await tenantApi<{ data: Agent }>(
                tenant.slug,
                `/ai/agents/${agent.id}`,
                {
                    method: 'PATCH',
                    body: agent,
                },
            );
            setAgent(result.data);
        } catch (err) {
            console.error(err);
        } finally {
            setIsSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!agent) return;
        if (!confirm(`¿Eliminar el agente "${agent.name}"?`)) return;
        try {
            await tenantApi(tenant.slug, `/ai/agents/${agent.id}`, {
                method: 'DELETE',
            });
            router.visit(`/${tenant.slug}/agents`);
        } catch (err) {
            alert(
                err instanceof TenantApiError
                    ? err.message
                    : 'No se pudo eliminar el agente',
            );
        }
    };

    const agentTemplates = [
        { id: 'recruitment', name: 'Recruitment Agent', description: 'AI agents that screen, interview, and onboard candidates at scale' },
        { id: 'lead-qualification', name: 'Lead Qualification Agent', description: 'Calls every lead to ask qualifying questions, answer FAQs, and warmly introduce the business' },
        { id: 'onboarding', name: 'Onboarding Agent', description: 'Conducts personalized guidance calls to warmly onboard new users' },
        { id: 'cart-abandonment', name: 'Cart Abandonment Agent', description: 'Calls customers with abandoned items in carts, recovering sales' },
        { id: 'customer-support', name: 'Customer Support Agent', description: 'Provides 24/7 inbound call answering for FAQs and customer triage' },
        { id: 'reminder', name: 'Reminder Agent', description: 'Automates all reminders, from EMIs and collections to form filling deadlines' },
        { id: 'announcement', name: 'Announcement Agent', description: 'Keeps users engaged with all feature upgrades and product launches' },
        { id: 'front-desk', name: 'Front Desk Agent', description: 'Answers every call to handle clinic, hotel, and office scheduling' },
        { id: 'survey', name: 'Survey Agent', description: 'Automated NPS, feedback & product surveys with detailed personalised questioning' },
        { id: 'cod-confirmation', name: 'COD Confirmation Agent', description: 'Handles a variety of last mile logistics tasks, saving human effort' },
    ];

    const handleCreateAgent = async () => {
        setIsCreating(true);
        try {
            const body = newAgentMode === 'auto'
                ? {
                    name: newAgentForm.name,
                    languages: newAgentForm.languages,
                    objective: newAgentForm.objective,
                    next_steps: newAgentForm.next_steps,
                    faqs: newAgentForm.faqs,
                }
                : {
                    template: selectedTemplate,
                };

            const result = await tenantApi<{ data: Agent }>(
                tenant.slug,
                '/ai/agents',
                { method: 'POST', body },
            );
            setNewAgentOpen(false);
            setNewAgentForm({ name: '', languages: [], objective: '', next_steps: '', faqs: '' });
            setSelectedTemplate(null);
            router.visit(`/${tenant.slug}/agents/${result.data.id}`);
        } catch (err) {
            alert(
                err instanceof TenantApiError
                    ? err.message
                    : 'No se pudo crear el agente',
            );
        } finally {
            setIsCreating(false);
        }
    };

    const handleCreateFromScratch = async () => {
        setIsCreating(true);
        try {
            const result = await tenantApi<{ data: Agent }>(
                tenant.slug,
                '/ai/agents',
                {
                    method: 'POST',
                    body: { from_scratch: true },
                },
            );
            setNewAgentOpen(false);
            setNewAgentForm({ name: '', languages: [], objective: '', next_steps: '', faqs: '' });
            setSelectedTemplate(null);
            router.visit(`/${tenant.slug}/agents/${result.data.id}`);
        } catch (err) {
            alert(
                err instanceof TenantApiError
                    ? err.message
                    : 'No se pudo crear el agente',
            );
        } finally {
            setIsCreating(false);
        }
    };

    const toggleLanguage = (lang: string) => {
        setNewAgentForm((prev) => ({
            ...prev,
            languages: prev.languages.includes(lang)
                ? prev.languages.filter((l) => l !== lang)
                : [...prev.languages, lang],
        }));
    };

    const components = [
        { name: 'Transcriber', color: 'bg-emerald-500', active: true },
        { name: 'LLM', color: 'bg-orange-500', active: true },
        { name: 'Voice', color: 'bg-slate-700', active: true },
        { name: 'Telephony', color: 'bg-amber-500', active: true },
        { name: 'Platform', color: 'bg-blue-600', active: true },
    ];

    const agentListContent = (
        <>
            <div className="flex gap-2 px-4 pb-4">
                <Button
                    variant="outline"
                    size="sm"
                    className="flex-1 gap-1.5"
                >
                    <Import className="size-3.5" />
                    Import
                </Button>
                <Button
                    size="sm"
                    className="flex-1 gap-1.5"
                    onClick={() => {
                        setNewAgentOpen(true);
                        setLeftSheetOpen(false);
                    }}
                >
                    <Plus className="size-3.5" />
                    New Agent
                </Button>
            </div>

            <div className="px-4 pb-4">
                <div className="relative">
                    <Search className="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder="Search agents..."
                        className="h-9 pl-8 text-sm"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                    />
                </div>
            </div>

            <div className="flex flex-col gap-1 overflow-y-auto px-2 pb-4">
                {filteredAgents.map((a) => (
                    <Link
                        key={a.id}
                        href={`/${tenant.slug}/agents/${a.id}`}
                        onClick={() => setLeftSheetOpen(false)}
                        className={cn(
                            'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors',
                            a.id === agent?.id
                                ? 'bg-accent text-accent-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                        )}
                    >
                        <Avatar className="size-7">
                            <AvatarFallback className="bg-primary/10 text-xs text-primary">
                                {a.name.charAt(0).toUpperCase()}
                            </AvatarFallback>
                        </Avatar>
                        <span className="truncate font-medium">
                            {a.name}
                        </span>
                    </Link>
                ))}
            </div>
        </>
    );

    const rightSidebarContent = (
        <>
            <div className="space-y-3">
                <Button className="w-full gap-2" size="lg">
                    <Phone className="size-4" />
                    Get call from agent
                </Button>

                <Button
                    variant="outline"
                    className="w-full gap-2"
                    size="lg"
                >
                    <PhoneIncoming className="size-4" />
                    Set inbound agent
                </Button>

                <Button
                    variant="link"
                    className="h-auto w-full py-0 text-xs"
                >
                    Purchase phone numbers
                    <ArrowUpRight className="ml-1 size-3" />
                </Button>
            </div>

            <Separator className="my-4" />

            <Button
                variant="outline"
                className="w-full justify-between"
            >
                See all call logs
                <ArrowUpRight className="size-4" />
            </Button>

            <Separator className="my-4" />

            <div className="flex gap-2">
                <Button
                    className="flex-1 gap-2"
                    onClick={handleSave}
                    disabled={isSaving}
                >
                    <Save className="size-4" />
                    {isSaving ? 'Saving...' : 'Save agent'}
                </Button>
                <Button
                    variant="outline"
                    size="icon"
                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                    onClick={handleDelete}
                >
                    <Trash2 className="size-4" />
                </Button>
            </div>

            <p className="mt-2 text-xs text-muted-foreground">
                Updated a month ago
            </p>

            <Separator className="my-4" />

            <Button variant="outline" className="w-full gap-2">
                <MessageSquare className="size-4" />
                Chat with agent
            </Button>

            <p className="mt-2 text-xs text-muted-foreground">
                Chat is the fastest way to test and refine.
            </p>

            <Separator className="my-4" />

            <Button
                variant="outline"
                className="w-full gap-2"
                onClick={() => setTestCallOpen(true)}
            >
                <Headphones className="size-4" />
                Test via browser
                <Badge
                    variant="secondary"
                    className="ml-auto text-[10px]"
                >
                    BETA
                </Badge>
            </Button>
            <p className="mt-2 text-xs text-muted-foreground">
                For best experience, use &quot;Get call from agent&quot;
            </p>
        </>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={agent?.name ?? 'Agent Setup'} />
            <TooltipProvider>
                <div className="flex h-[calc(100vh-4rem)] flex-col">
                    {/* Main Content - 3 Columns */}
                    <div className="flex flex-1 overflow-hidden">
                        {/* Left Sidebar - Desktop */}
                        <aside className="hidden w-64 shrink-0 flex-col border-r border-border bg-card lg:flex">
                            <div className="p-4">
                                <h2 className="text-sm font-semibold">
                                    Your Agents
                                </h2>
                            </div>
                            {agentListContent}
                        </aside>

                        {/* Left Sidebar - Mobile Sheet */}
                        <Sheet open={leftSheetOpen} onOpenChange={setLeftSheetOpen}>
                            <SheetContent side="left" className="w-72 p-0">
                                <SheetHeader className="border-b border-border">
                                    <SheetTitle>Your Agents</SheetTitle>
                                </SheetHeader>
                                <div className="flex flex-col overflow-hidden pt-4">
                                    {agentListContent}
                                </div>
                            </SheetContent>
                        </Sheet>

                        {/* Center Column - Agent Configuration */}
                        <main className="flex-1 overflow-y-auto bg-background">
                            {/* Mobile toolbar */}
                            <div className="flex items-center justify-between border-b border-border px-4 py-2 lg:hidden">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-9"
                                    onClick={() => setLeftSheetOpen(true)}
                                >
                                    <Menu className="size-5" />
                                </Button>
                                {agent && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-9"
                                        onClick={() => setRightSheetOpen(true)}
                                    >
                                        <PanelRight className="size-5" />
                                    </Button>
                                )}
                            </div>

                            <div className="p-4 lg:p-6">
                                {!agent ? (
                                    <div className="flex min-h-[calc(100vh-12rem)] flex-col items-center justify-center px-4 text-center">
                                        {/* <div className="relative">
                                        <div className="absolute -inset-4 rounded-full bg-gradient-to-br " />
                                        <div className="relative flex size-20 items-center justify-center rounded-2xl border border-border bg-card shadow-sm">
                                        </div>
                                    </div> */}
                                        <Bot className="size-9 text-muted-foreground/70" />

                                        <h3 className="mt-6 text-xl font-semibold tracking-tight">
                                            No agent selected
                                        </h3>
                                        <p className="mt-2 max-w-sm text-sm leading-relaxed text-muted-foreground">
                                            {agents.length === 0
                                                ? 'Create your first AI agent to start automating calls, qualifying leads, and more.'
                                                : 'Select an agent from the list to view and edit its configuration.'}
                                        </p>
                                        <div className="mt-6 flex flex-col items-center gap-3 sm:flex-row">
                                            {agents.length === 0 && (
                                                <Button
                                                    className="gap-2"
                                                    onClick={() => setNewAgentOpen(true)}
                                                >
                                                    <Plus className="size-4" />
                                                    Create your first agent
                                                </Button>
                                            )}
                                            {agents.length > 0 && (
                                                <>
                                                    <Button
                                                        className="gap-2 lg:hidden"
                                                        onClick={() => setLeftSheetOpen(true)}
                                                    >
                                                        <Menu className="size-4" />
                                                        View agents
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        className="gap-2"
                                                        onClick={() => setNewAgentOpen(true)}
                                                    >
                                                        <Plus className="size-4" />
                                                        New agent
                                                    </Button>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                ) : (
                                    <>
                                        {/* Agent Header */}
                                        <div className="mb-6">
                                            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                                <div>
                                                    <h2 className="text-xl font-semibold sm:text-2xl">
                                                        {agent.name}
                                                    </h2>
                                                    <div className="mt-2 flex flex-wrap items-center gap-2 sm:gap-3">
                                                        <div className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                                            <span className="inline-flex size-4 items-center justify-center rounded-full border border-muted-foreground/30 text-[10px]">
                                                                $
                                                            </span>
                                                            Cost per min: ~ $
                                                            {COST_PER_MINUTE}
                                                        </div>
                                                        <Badge
                                                            variant="outline"
                                                            className="gap-1.5 border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-400"
                                                        >
                                                            <span className="size-1.5 rounded-full bg-emerald-500" />
                                                            India Routing
                                                        </Badge>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="gap-1.5"
                                                    >
                                                        <FileText className="size-3.5" />
                                                        Agent ID
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="gap-1.5"
                                                    >
                                                        <Share2 className="size-3.5" />
                                                        Share
                                                    </Button>
                                                </div>
                                            </div>

                                            {/* Component Progress Bar */}
                                            <div className="mt-4 flex h-2 overflow-hidden rounded-full">
                                                {components.map((comp, idx) => (
                                                    <div
                                                        key={comp.name}
                                                        className={cn(
                                                            comp.color,
                                                            'flex-1',
                                                            idx > 0 && 'ml-0.5',
                                                        )}
                                                        title={comp.name}
                                                    />
                                                ))}
                                            </div>
                                            <div className="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                                {components.map((comp) => (
                                                    <div
                                                        key={comp.name}
                                                        className="flex items-center gap-1"
                                                    >
                                                        <span
                                                            className={cn(
                                                                comp.color,
                                                                'size-1.5 rounded-full',
                                                            )}
                                                        />
                                                        <span className="text-muted-foreground">
                                                            {comp.name}
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>

                                        {/* Tabs */}
                                        <Tabs
                                            value={activeTab}
                                            onValueChange={setActiveTab}
                                            className="w-full"
                                        >
                                            <TabsList className="mb-6 flex w-full overflow-x-auto md:grid md:grid-cols-8">
                                                <TabsTrigger
                                                    value="agent"
                                                    className="gap-1.5"
                                                >
                                                    <Bot className="size-3.5" />
                                                    <span className="hidden sm:inline">Agent</span>
                                                </TabsTrigger>
                                                <TabsTrigger
                                                    value="llm"
                                                    className="gap-1.5"
                                                >
                                                    <Brain className="size-3.5" />
                                                    <span className="hidden sm:inline">LLM</span>
                                                </TabsTrigger>
                                                <TabsTrigger
                                                    value="audio"
                                                    className="gap-1.5"
                                                >
                                                    <Mic className="size-3.5" />
                                                    <span className="hidden sm:inline">Audio</span>
                                                </TabsTrigger>
                                                <TabsTrigger
                                                    value="engine"
                                                    className="gap-1.5"
                                                >
                                                    <Settings className="size-3.5" />
                                                    <span className="hidden sm:inline">Engine</span>
                                                </TabsTrigger>
                                                <TabsTrigger
                                                    value="call"
                                                    className="gap-1.5"
                                                >
                                                    <Phone className="size-3.5" />
                                                    <span className="hidden sm:inline">Call</span>
                                                </TabsTrigger>
                                                <TabsTrigger
                                                    value="tools"
                                                    className="gap-1.5"
                                                >
                                                    <Wrench className="size-3.5" />
                                                    <span className="hidden sm:inline">Tools</span>
                                                </TabsTrigger>
                                                <TabsTrigger
                                                    value="analytics"
                                                    className="gap-1.5"
                                                >
                                                    <ArrowUpRight className="size-3.5" />
                                                    <span className="hidden sm:inline">Analytics</span>
                                                </TabsTrigger>
                                                <TabsTrigger
                                                    value="inbound"
                                                    className="gap-1.5"
                                                >
                                                    <PhoneIncoming className="size-3.5" />
                                                    <span className="hidden sm:inline">Inbound</span>
                                                </TabsTrigger>
                                            </TabsList>

                                            <TabsContent
                                                value="agent"
                                                className="space-y-6"
                                            >
                                                {/* Welcome Message */}
                                                <div className="rounded-xl border border-border bg-card">
                                                    <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                                                        <MessageSquare className="size-4 text-muted-foreground" />
                                                        <h3 className="font-medium">
                                                            Agent Welcome Message
                                                        </h3>
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <HelpCircle className="size-3.5 cursor-help text-muted-foreground" />
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>
                                                                    Mensaje inicial que el
                                                                    agente dirá al comenzar
                                                                    la llamada
                                                                </p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </div>
                                                    <div className="p-4">
                                                        <Textarea
                                                            placeholder="Hi, this is {agent_name}, ready to help you..."
                                                            className="min-h-[80px] resize-none border-0 bg-transparent p-0 focus-visible:ring-0"
                                                            value={
                                                                agent.brain_config
                                                                    ?.welcome_message || ''
                                                            }
                                                            onChange={(e) =>
                                                                setAgent({
                                                                    ...agent,
                                                                    brain_config: {
                                                                        ...agent.brain_config,
                                                                        welcome_message:
                                                                            e.target.value,
                                                                    },
                                                                })
                                                            }
                                                        />
                                                        <p className="mt-2 text-xs text-muted-foreground">
                                                            You can define variables using{' '}
                                                            {'{'}variable_name{'}'}
                                                        </p>
                                                    </div>
                                                </div>

                                                {/* Agent Prompt */}
                                                <div className="rounded-xl border border-border bg-card">
                                                    <div className="flex items-center justify-between border-b border-border px-4 py-3">
                                                        <div className="flex items-center gap-2">
                                                            <FileText className="size-4 text-muted-foreground" />
                                                            <h3 className="font-medium">
                                                                Agent Prompt
                                                            </h3>
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <HelpCircle className="size-3.5 cursor-help text-muted-foreground" />
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    <p>
                                                                        Instrucciones de
                                                                        comportamiento para
                                                                        el agente
                                                                    </p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </div>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            className="gap-1.5"
                                                        >
                                                            <Sparkles className="size-3.5" />
                                                            AI Edit
                                                        </Button>
                                                    </div>
                                                    <div className="p-4">
                                                        <Textarea
                                                            placeholder="Define el comportamiento, personalidad y objetivos del agente..."
                                                            className="min-h-[200px] resize-none border-0 bg-transparent p-0 focus-visible:ring-0"
                                                            value={
                                                                agent.brain_config
                                                                    ?.system_prompt || ''
                                                            }
                                                            onChange={(e) =>
                                                                setAgent({
                                                                    ...agent,
                                                                    brain_config: {
                                                                        ...agent.brain_config,
                                                                        system_prompt:
                                                                            e.target.value,
                                                                    },
                                                                })
                                                            }
                                                        />
                                                    </div>
                                                </div>
                                            </TabsContent>

                                            {/* LLM Tab */}
                                            <TabsContent value="llm" className="space-y-6">
                                                {/* Choose LLM Model */}
                                                <div className="rounded-xl border border-border bg-card">
                                                    <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                                                        <Brain className="size-4 text-muted-foreground" />
                                                        <h3 className="font-medium">Choose LLM model</h3>
                                                    </div>
                                                    <div className="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2">
                                                        <div className="space-y-2">
                                                            <Label>Provider</Label>
                                                            <Select
                                                                value={agent.brain_config?.provider || 'openai'}
                                                                onValueChange={(val) =>
                                                                    setAgent({
                                                                        ...agent,
                                                                        brain_config: {
                                                                            ...agent.brain_config,
                                                                            provider: val as string,
                                                                        },
                                                                    })
                                                                }
                                                            >
                                                                <SelectTrigger className="w-full">
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem value="openai">openai</SelectItem>
                                                                    <SelectItem value="anthropic">anthropic</SelectItem>
                                                                    <SelectItem value="google">google</SelectItem>
                                                                    <SelectItem value="groq">groq</SelectItem>
                                                                    <SelectItem value="deepseek">deepseek</SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div className="space-y-2">
                                                            <Label>Model</Label>
                                                            <Select
                                                                value={agent.brain_config?.model || 'gpt-4o'}
                                                                onValueChange={(val) =>
                                                                    setAgent({
                                                                        ...agent,
                                                                        brain_config: {
                                                                            ...agent.brain_config,
                                                                            model: val as string,
                                                                        },
                                                                    })
                                                                }
                                                            >
                                                                <SelectTrigger className="w-full">
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem value="gpt-4o">gpt-4o</SelectItem>
                                                                    <SelectItem value="gpt-4o-mini">gpt-4o-mini</SelectItem>
                                                                    <SelectItem value="gpt-4.1-mini">gpt-4.1-mini</SelectItem>
                                                                    <SelectItem value="claude-sonnet-4-5-20250514">claude-sonnet-4-5</SelectItem>
                                                                    <SelectItem value="gemini-2.0-flash">gemini-2.0-flash</SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Model Parameters */}
                                                <div className="rounded-xl border border-border bg-card">
                                                    <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                                                        <Settings className="size-4 text-muted-foreground" />
                                                        <h3 className="font-medium">Model Parameters</h3>
                                                    </div>
                                                    <div className="grid grid-cols-1 gap-6 p-4 sm:grid-cols-2">
                                                        <div className="space-y-3">
                                                            <div className="flex items-center justify-between">
                                                                <Label>Tokens generated on each LLM output</Label>
                                                                <span className="rounded-md border border-border px-2 py-0.5 text-sm font-medium tabular-nums">
                                                                    {agent.brain_config?.max_tokens ?? 1536}
                                                                </span>
                                                            </div>
                                                            <Slider
                                                                value={[agent.brain_config?.max_tokens ?? 1536]}
                                                                onValueChange={([val]) =>
                                                                    setAgent({
                                                                        ...agent,
                                                                        brain_config: {
                                                                            ...agent.brain_config,
                                                                            max_tokens: val,
                                                                        },
                                                                    })
                                                                }
                                                                min={50}
                                                                max={4096}
                                                                step={1}
                                                            />
                                                            <p className="text-xs text-muted-foreground">
                                                                Increasing tokens enables longer responses to be queued for speech generation but increases latency
                                                            </p>
                                                        </div>
                                                        <div className="space-y-3">
                                                            <div className="flex items-center justify-between">
                                                                <Label>Temperature</Label>
                                                                <span className="rounded-md border border-border px-2 py-0.5 text-sm font-medium tabular-nums">
                                                                    {(agent.brain_config?.temperature ?? 0.6).toFixed(2)}
                                                                </span>
                                                            </div>
                                                            <Slider
                                                                value={[agent.brain_config?.temperature ?? 0.6]}
                                                                onValueChange={([val]) =>
                                                                    setAgent({
                                                                        ...agent,
                                                                        brain_config: {
                                                                            ...agent.brain_config,
                                                                            temperature: parseFloat(val.toFixed(2)),
                                                                        },
                                                                    })
                                                                }
                                                                min={0}
                                                                max={2}
                                                                step={0.01}
                                                            />
                                                            <p className="text-xs text-muted-foreground">
                                                                Increasing temperature enables heightened creativity, but increases chance of deviation from prompt.
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </TabsContent>

                                            {/* Audio Tab */}
                                            <TabsContent value="audio" className="space-y-6">
                                                {/* Language */}
                                                <div className="rounded-xl border border-border bg-card">
                                                    <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                                                        <Globe className="size-4 text-muted-foreground" />
                                                        <h3 className="font-medium">Configure Language</h3>
                                                    </div>
                                                    <div className="p-4">
                                                        <div className="max-w-xs space-y-2">
                                                            <Label>Language</Label>
                                                            <Select
                                                                value={agent.language || 'es'}
                                                                onValueChange={(val) =>
                                                                    setAgent({
                                                                        ...agent,
                                                                        language: val as string,
                                                                    })
                                                                }
                                                            >
                                                                <SelectTrigger className="w-full">
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem value="es">Spanish</SelectItem>
                                                                    <SelectItem value="en">English</SelectItem>
                                                                    <SelectItem value="pt">Portuguese</SelectItem>
                                                                    <SelectItem value="fr">French</SelectItem>
                                                                    <SelectItem value="de">German</SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Speech-to-Text */}
                                                <div className="rounded-xl border border-border bg-card">
                                                    <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                                                        <Mic className="size-4 text-muted-foreground" />
                                                        <h3 className="font-medium">Speech-to-Text</h3>
                                                    </div>
                                                    <div className="space-y-4 p-4">
                                                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                            <div className="space-y-2">
                                                                <Label>Provider</Label>
                                                                <Select
                                                                    value={agent.runtime_config?.stt_provider || 'deepgram'}
                                                                    onValueChange={(val) =>
                                                                        setAgent({
                                                                            ...agent,
                                                                            runtime_config: {
                                                                                ...agent.runtime_config,
                                                                                stt_provider: val as string,
                                                                            },
                                                                        })
                                                                    }
                                                                >
                                                                    <SelectTrigger className="w-full">
                                                                        <SelectValue />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        <SelectItem value="deepgram">deepgram</SelectItem>
                                                                        <SelectItem value="google">google</SelectItem>
                                                                        <SelectItem value="azure">azure</SelectItem>
                                                                        <SelectItem value="whisper">whisper</SelectItem>
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>
                                                            <div className="space-y-2">
                                                                <Label>Model</Label>
                                                                <Select
                                                                    value={agent.runtime_config?.stt_model || 'nova-2'}
                                                                    onValueChange={(val) =>
                                                                        setAgent({
                                                                            ...agent,
                                                                            runtime_config: {
                                                                                ...agent.runtime_config,
                                                                                stt_model: val as string,
                                                                            },
                                                                        })
                                                                    }
                                                                >
                                                                    <SelectTrigger className="w-full">
                                                                        <SelectValue />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        <SelectItem value="nova-2">nova-2</SelectItem>
                                                                        <SelectItem value="nova-3">nova-3</SelectItem>
                                                                        <SelectItem value="whisper-large">whisper-large</SelectItem>
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>
                                                        </div>
                                                        <div className="max-w-sm space-y-2">
                                                            <Label>Keywords</Label>
                                                            <Input
                                                                placeholder="keyword:boost (e.g. Alloy:100)"
                                                                value={agent.runtime_config?.stt_keywords || ''}
                                                                onChange={(e) =>
                                                                    setAgent({
                                                                        ...agent,
                                                                        runtime_config: {
                                                                            ...agent.runtime_config,
                                                                            stt_keywords: e.target.value,
                                                                        },
                                                                    })
                                                                }
                                                            />
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Text-to-Speech */}
                                                <div className="rounded-xl border border-border bg-card">
                                                    <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                                                        <Volume2 className="size-4 text-muted-foreground" />
                                                        <h3 className="font-medium">Text-to-Speech</h3>
                                                    </div>
                                                    <div className="space-y-4 p-4">
                                                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                                            <div className="space-y-2">
                                                                <Label>Provider</Label>
                                                                <Select
                                                                    value={agent.runtime_config?.tts_provider || 'cartesia'}
                                                                    onValueChange={(val) =>
                                                                        setAgent({
                                                                            ...agent,
                                                                            runtime_config: {
                                                                                ...agent.runtime_config,
                                                                                tts_provider: val as string,
                                                                            },
                                                                        })
                                                                    }
                                                                >
                                                                    <SelectTrigger className="w-full">
                                                                        <SelectValue />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        <SelectItem value="cartesia">Cartesia</SelectItem>
                                                                        <SelectItem value="elevenlabs">ElevenLabs</SelectItem>
                                                                        <SelectItem value="polly">Polly</SelectItem>
                                                                        <SelectItem value="azure">Azure</SelectItem>
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>
                                                            <div className="space-y-2">
                                                                <Label>Model</Label>
                                                                <Input
                                                                    placeholder="Model ID"
                                                                    value={agent.runtime_config?.tts_model || ''}
                                                                    onChange={(e) =>
                                                                        setAgent({
                                                                            ...agent,
                                                                            runtime_config: {
                                                                                ...agent.runtime_config,
                                                                                tts_model: e.target.value,
                                                                            },
                                                                        })
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="space-y-2">
                                                                <Label>Voice</Label>
                                                                <Input
                                                                    placeholder="Voice ID"
                                                                    value={agent.runtime_config?.tts_voice_id || ''}
                                                                    onChange={(e) =>
                                                                        setAgent({
                                                                            ...agent,
                                                                            runtime_config: {
                                                                                ...agent.runtime_config,
                                                                                tts_voice_id: e.target.value,
                                                                            },
                                                                        })
                                                                    }
                                                                />
                                                            </div>
                                                        </div>

                                                        <div className="flex items-center gap-3">
                                                            <Button variant="outline" size="sm" className="gap-1.5">
                                                                <Play className="size-3.5" />
                                                                Preview welcome message
                                                            </Button>
                                                        </div>

                                                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                                            <div className="space-y-3">
                                                                <div className="flex items-center justify-between">
                                                                    <Label>Speed</Label>
                                                                    <span className="rounded-md border border-border px-2 py-0.5 text-sm font-medium tabular-nums">
                                                                        {(agent.runtime_config?.tts_speed ?? 1.08).toFixed(2)}
                                                                    </span>
                                                                </div>
                                                                <Slider
                                                                    value={[agent.runtime_config?.tts_speed ?? 1.08]}
                                                                    onValueChange={([val]) =>
                                                                        setAgent({
                                                                            ...agent,
                                                                            runtime_config: {
                                                                                ...agent.runtime_config,
                                                                                tts_speed: parseFloat(val.toFixed(2)),
                                                                            },
                                                                        })
                                                                    }
                                                                    min={0.5}
                                                                    max={2}
                                                                    step={0.01}
                                                                />
                                                            </div>
                                                            <div className="space-y-3">
                                                                <div className="flex items-center justify-between">
                                                                    <Label>Buffer Size</Label>
                                                                    <span className="rounded-md border border-border px-2 py-0.5 text-sm font-medium tabular-nums">
                                                                        {agent.runtime_config?.tts_buffer_size ?? 200}
                                                                    </span>
                                                                </div>
                                                                <Slider
                                                                    value={[agent.runtime_config?.tts_buffer_size ?? 200]}
                                                                    onValueChange={([val]) =>
                                                                        setAgent({
                                                                            ...agent,
                                                                            runtime_config: {
                                                                                ...agent.runtime_config,
                                                                                tts_buffer_size: val,
                                                                            },
                                                                        })
                                                                    }
                                                                    min={50}
                                                                    max={500}
                                                                    step={10}
                                                                />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </TabsContent>

                                            {/* Engine Tab */}
                                            <TabsContent value="engine" className="space-y-6">
                                                {/* Behavior */}
                                                <div className="rounded-xl border border-border bg-card">
                                                    <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                                                        <Zap className="size-4 text-muted-foreground" />
                                                        <h3 className="font-medium">Behavior</h3>
                                                    </div>
                                                    <div className="space-y-4 p-4">
                                                        <div className="flex items-center justify-between">
                                                            <div>
                                                                <Label>Interruptibility</Label>
                                                                <p className="text-xs text-muted-foreground">
                                                                    Allow the caller to interrupt the agent while speaking
                                                                </p>
                                                            </div>
                                                            <Switch
                                                                checked={agent.runtime_config?.interruptibility ?? true}
                                                                onCheckedChange={(val) =>
                                                                    setAgent({
                                                                        ...agent,
                                                                        runtime_config: {
                                                                            ...agent.runtime_config,
                                                                            interruptibility: val,
                                                                        },
                                                                    })
                                                                }
                                                            />
                                                        </div>
                                                        <Separator />
                                                        <div className="flex items-center justify-between">
                                                            <div>
                                                                <Label>Streaming</Label>
                                                                <p className="text-xs text-muted-foreground">
                                                                    Stream LLM responses for lower latency
                                                                </p>
                                                            </div>
                                                            <Switch
                                                                checked={agent.runtime_config?.streaming ?? true}
                                                                onCheckedChange={(val) =>
                                                                    setAgent({
                                                                        ...agent,
                                                                        runtime_config: {
                                                                            ...agent.runtime_config,
                                                                            streaming: val,
                                                                        },
                                                                    })
                                                                }
                                                            />
                                                        </div>
                                                        <Separator />
                                                        <div className="max-w-xs space-y-2">
                                                            <Label>Initial Action</Label>
                                                            <Select
                                                                value={agent.runtime_config?.initial_action || 'SPEAK_FIRST'}
                                                                onValueChange={(val) =>
                                                                    setAgent({
                                                                        ...agent,
                                                                        runtime_config: {
                                                                            ...agent.runtime_config,
                                                                            initial_action: val as string,
                                                                        },
                                                                    })
                                                                }
                                                            >
                                                                <SelectTrigger className="w-full">
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem value="SPEAK_FIRST">Speak first</SelectItem>
                                                                    <SelectItem value="LISTEN_FIRST">Listen first</SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                            <p className="text-xs text-muted-foreground">
                                                                Whether the agent speaks the welcome message or waits for the caller
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Session Limits */}
                                                <div className="rounded-xl border border-border bg-card">
                                                    <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                                                        <Timer className="size-4 text-muted-foreground" />
                                                        <h3 className="font-medium">Session Limits</h3>
                                                    </div>
                                                    <div className="space-y-4 p-4">
                                                        <div className="space-y-3">
                                                            <div className="flex items-center justify-between">
                                                                <Label>Max call duration (seconds)</Label>
                                                                <span className="rounded-md border border-border px-2 py-0.5 text-sm font-medium tabular-nums">
                                                                    {agent.runtime_config?.max_duration_seconds ?? 900}
                                                                </span>
                                                            </div>
                                                            <Slider
                                                                value={[agent.runtime_config?.max_duration_seconds ?? 900]}
                                                                onValueChange={([val]) =>
                                                                    setAgent({
                                                                        ...agent,
                                                                        runtime_config: {
                                                                            ...agent.runtime_config,
                                                                            max_duration_seconds: val,
                                                                        },
                                                                    })
                                                                }
                                                                min={60}
                                                                max={3600}
                                                                step={30}
                                                            />
                                                        </div>

                                                        <Separator />

                                                        <div className="flex items-center justify-between">
                                                            <div>
                                                                <Label>Inactivity Timeout</Label>
                                                                <p className="text-xs text-muted-foreground">
                                                                    Remind the caller after silence and end the call if no response
                                                                </p>
                                                            </div>
                                                            <Switch
                                                                checked={agent.runtime_config?.inactivity_timeout_enabled ?? true}
                                                                onCheckedChange={(val) =>
                                                                    setAgent({
                                                                        ...agent,
                                                                        runtime_config: {
                                                                            ...agent.runtime_config,
                                                                            inactivity_timeout_enabled: val,
                                                                        },
                                                                    })
                                                                }
                                                            />
                                                        </div>

                                                        {agent.runtime_config?.inactivity_timeout_enabled !== false && (
                                                            <>
                                                                <div className="space-y-3">
                                                                    <div className="flex items-center justify-between">
                                                                        <Label>Timeout (seconds)</Label>
                                                                        <span className="rounded-md border border-border px-2 py-0.5 text-sm font-medium tabular-nums">
                                                                            {agent.runtime_config?.inactivity_timeout_seconds ?? 20}
                                                                        </span>
                                                                    </div>
                                                                    <Slider
                                                                        value={[agent.runtime_config?.inactivity_timeout_seconds ?? 20]}
                                                                        onValueChange={([val]) =>
                                                                            setAgent({
                                                                                ...agent,
                                                                                runtime_config: {
                                                                                    ...agent.runtime_config,
                                                                                    inactivity_timeout_seconds: val,
                                                                                },
                                                                            })
                                                                        }
                                                                        min={5}
                                                                        max={60}
                                                                        step={1}
                                                                    />
                                                                </div>
                                                                <div className="space-y-3">
                                                                    <div className="flex items-center justify-between">
                                                                        <div>
                                                                            <Label>Inactivity messages</Label>
                                                                            <p className="text-xs text-muted-foreground">
                                                                                The agent will pick one at random when the caller goes silent
                                                                            </p>
                                                                        </div>
                                                                        <Button
                                                                            variant="outline"
                                                                            size="sm"
                                                                            className="gap-1.5"
                                                                            onClick={() => {
                                                                                const current = agent.runtime_config?.inactivity_messages ?? [];
                                                                                setAgent({
                                                                                    ...agent,
                                                                                    runtime_config: {
                                                                                        ...agent.runtime_config,
                                                                                        inactivity_messages: [...current, ''],
                                                                                    },
                                                                                });
                                                                            }}
                                                                        >
                                                                            <Plus className="size-3.5" />
                                                                            Add
                                                                        </Button>
                                                                    </div>
                                                                    <div className="space-y-2">
                                                                        {(agent.runtime_config?.inactivity_messages ?? ['¿Me sigue escuchando?']).map((msg, idx) => (
                                                                            <div key={idx} className="flex items-center gap-2">
                                                                                <Input
                                                                                    placeholder="e.g. ¿Sigue en línea?"
                                                                                    value={msg}
                                                                                    onChange={(e) => {
                                                                                        const msgs = [...(agent.runtime_config?.inactivity_messages ?? ['¿Me sigue escuchando?'])];
                                                                                        msgs[idx] = e.target.value;
                                                                                        setAgent({
                                                                                            ...agent,
                                                                                            runtime_config: {
                                                                                                ...agent.runtime_config,
                                                                                                inactivity_messages: msgs,
                                                                                            },
                                                                                        });
                                                                                    }}
                                                                                />
                                                                                {(agent.runtime_config?.inactivity_messages ?? []).length > 1 && (
                                                                                    <Button
                                                                                        variant="ghost"
                                                                                        size="icon"
                                                                                        className="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                                                                                        onClick={() => {
                                                                                            const msgs = [...(agent.runtime_config?.inactivity_messages ?? [])];
                                                                                            msgs.splice(idx, 1);
                                                                                            setAgent({
                                                                                                ...agent,
                                                                                                runtime_config: {
                                                                                                    ...agent.runtime_config,
                                                                                                    inactivity_messages: msgs,
                                                                                                },
                                                                                            });
                                                                                        }}
                                                                                    >
                                                                                        <X className="size-3.5" />
                                                                                    </Button>
                                                                                )}
                                                                            </div>
                                                                        ))}
                                                                    </div>
                                                                </div>
                                                                <div className="space-y-2">
                                                                    <Label>Final message (before hang up)</Label>
                                                                    <Input
                                                                        placeholder="Parece que se cortó la llamada..."
                                                                        value={agent.runtime_config?.inactivity_final_message || ''}
                                                                        onChange={(e) =>
                                                                            setAgent({
                                                                                ...agent,
                                                                                runtime_config: {
                                                                                    ...agent.runtime_config,
                                                                                    inactivity_final_message: e.target.value,
                                                                                },
                                                                            })
                                                                        }
                                                                    />
                                                                </div>
                                                            </>
                                                        )}
                                                    </div>
                                                </div>
                                            </TabsContent>

                                            {/* Call Tab */}
                                            <TabsContent value="call" className="space-y-6">
                                                {/* Call Configuration */}
                                                <div className="rounded-xl border border-border bg-card">
                                                    <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                                                        <Phone className="size-4 text-muted-foreground" />
                                                        <h3 className="font-medium">Call Configuration</h3>
                                                    </div>
                                                    <div className="space-y-4 p-4">
                                                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                            <div className="space-y-2">
                                                                <Label>Telephony Provider</Label>
                                                                <Select
                                                                    value={agent.runtime_config?.telephony_provider || 'twilio'}
                                                                    onValueChange={(val) =>
                                                                        setAgent({
                                                                            ...agent,
                                                                            runtime_config: {
                                                                                ...agent.runtime_config,
                                                                                telephony_provider: val as string,
                                                                            },
                                                                        })
                                                                    }
                                                                >
                                                                    <SelectTrigger className="w-full">
                                                                        <SelectValue />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        <SelectItem value="twilio">Twilio</SelectItem>
                                                                        <SelectItem value="vonage">Vonage</SelectItem>
                                                                        <SelectItem value="telnyx">Telnyx</SelectItem>
                                                                        <SelectItem value="asterisk">Asterisk</SelectItem>
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>
                                                            <div className="space-y-2">
                                                                <Label>Ambient Noise</Label>
                                                                <div className="flex items-center gap-2">
                                                                    <Select
                                                                        value={agent.runtime_config?.ambient_noise || 'none'}
                                                                        onValueChange={(val) =>
                                                                            setAgent({
                                                                                ...agent,
                                                                                runtime_config: {
                                                                                    ...agent.runtime_config,
                                                                                    ambient_noise: val as string,
                                                                                },
                                                                            })
                                                                        }
                                                                    >
                                                                        <SelectTrigger className="w-full">
                                                                            <SelectValue />
                                                                        </SelectTrigger>
                                                                        <SelectContent>
                                                                            <SelectItem value="none">None</SelectItem>
                                                                            <SelectItem value="office">Office</SelectItem>
                                                                            <SelectItem value="cafe">Café</SelectItem>
                                                                            <SelectItem value="call-center">Call Center</SelectItem>
                                                                        </SelectContent>
                                                                    </Select>
                                                                    <Button variant="outline" size="icon" className="shrink-0">
                                                                        <Play className="size-3.5" />
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div className="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                                                            <div className="flex items-center justify-between">
                                                                <div className="flex items-center gap-2">
                                                                    <ListFilter className="size-4 text-muted-foreground" />
                                                                    <Label>Noise Cancellation</Label>
                                                                </div>
                                                                <Switch
                                                                    checked={agent.runtime_config?.noise_cancellation ?? false}
                                                                    onCheckedChange={(val) =>
                                                                        setAgent({
                                                                            ...agent,
                                                                            runtime_config: {
                                                                                ...agent.runtime_config,
                                                                                noise_cancellation: val,
                                                                            },
                                                                        })
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="flex items-center justify-between">
                                                                <div className="flex items-center gap-2">
                                                                    <PhoneOff className="size-4 text-muted-foreground" />
                                                                    <Label>Voicemail Detection</Label>
                                                                </div>
                                                                <Switch
                                                                    checked={agent.runtime_config?.voicemail_detection ?? false}
                                                                    onCheckedChange={(val) =>
                                                                        setAgent({
                                                                            ...agent,
                                                                            runtime_config: {
                                                                                ...agent.runtime_config,
                                                                                voicemail_detection: val,
                                                                            },
                                                                        })
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="flex items-center justify-between">
                                                                <div className="flex items-center gap-2">
                                                                    <Grid3X3 className="size-4 text-muted-foreground" />
                                                                    <Label>Keypad Input (DTMF)</Label>
                                                                </div>
                                                                <Switch
                                                                    checked={agent.runtime_config?.keypad_input_dtmf ?? false}
                                                                    onCheckedChange={(val) =>
                                                                        setAgent({
                                                                            ...agent,
                                                                            runtime_config: {
                                                                                ...agent.runtime_config,
                                                                                keypad_input_dtmf: val,
                                                                            },
                                                                        })
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="flex items-center justify-between">
                                                                <div className="flex items-center gap-2">
                                                                    <Calendar className="size-4 text-muted-foreground" />
                                                                    <Label>Auto Reschedule</Label>
                                                                    <Badge variant="secondary" className="text-[10px]">BETA</Badge>
                                                                </div>
                                                                <Switch
                                                                    checked={agent.runtime_config?.auto_reschedule ?? false}
                                                                    onCheckedChange={(val) =>
                                                                        setAgent({
                                                                            ...agent,
                                                                            runtime_config: {
                                                                                ...agent.runtime_config,
                                                                                auto_reschedule: val,
                                                                            },
                                                                        })
                                                                    }
                                                                />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Outbound call timing restrictions */}
                                                <div className="rounded-xl border border-border bg-card">
                                                    <div className="flex items-center justify-between px-4 py-3">
                                                        <div className="flex items-center gap-2">
                                                            <Clock className="size-4 text-muted-foreground" />
                                                            <h3 className="font-medium">Outbound call timing restrictions</h3>
                                                        </div>
                                                        <Switch
                                                            checked={agent.runtime_config?.outbound_timing_restrictions ?? false}
                                                            onCheckedChange={(val) =>
                                                                setAgent({
                                                                    ...agent,
                                                                    runtime_config: {
                                                                        ...agent.runtime_config,
                                                                        outbound_timing_restrictions: val,
                                                                    },
                                                                })
                                                            }
                                                        />
                                                    </div>
                                                </div>

                                                {/* Final Call Message */}
                                                <div className="rounded-xl border border-border bg-card">
                                                    <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                                                        <MessageSquare className="size-4 text-muted-foreground" />
                                                        <h3 className="font-medium">Final Call Message</h3>
                                                    </div>
                                                    <div className="p-4">
                                                        <Textarea
                                                            placeholder="e.g. Thank you for your time. Goodbye!"
                                                            className="min-h-[80px]"
                                                            value={agent.runtime_config?.final_call_message || ''}
                                                            onChange={(e) =>
                                                                setAgent({
                                                                    ...agent,
                                                                    runtime_config: {
                                                                        ...agent.runtime_config,
                                                                        final_call_message: e.target.value,
                                                                    },
                                                                })
                                                            }
                                                        />
                                                        <p className="mt-1 text-right text-xs text-muted-foreground">
                                                            {(agent.runtime_config?.final_call_message || '').length} chars
                                                        </p>
                                                    </div>
                                                </div>

                                                {/* Call Management */}
                                                <div className="rounded-xl border border-border bg-card">
                                                    <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                                                        <Settings className="size-4 text-muted-foreground" />
                                                        <h3 className="font-medium">Call Management</h3>
                                                    </div>
                                                    <div className="grid grid-cols-1 gap-6 p-4 sm:grid-cols-2">
                                                        <div className="space-y-3">
                                                            <div className="flex items-center justify-between">
                                                                <div className="flex items-center gap-2">
                                                                    <PhoneOff className="size-4 text-muted-foreground" />
                                                                    <Label>Hangup on User Silence</Label>
                                                                </div>
                                                                <Switch
                                                                    checked={agent.runtime_config?.hangup_on_silence_enabled ?? true}
                                                                    onCheckedChange={(val) =>
                                                                        setAgent({
                                                                            ...agent,
                                                                            runtime_config: {
                                                                                ...agent.runtime_config,
                                                                                hangup_on_silence_enabled: val,
                                                                            },
                                                                        })
                                                                    }
                                                                />
                                                            </div>
                                                            {agent.runtime_config?.hangup_on_silence_enabled !== false && (
                                                                <div className="flex items-center gap-3">
                                                                    <Slider
                                                                        value={[agent.runtime_config?.hangup_on_silence_seconds ?? 10]}
                                                                        onValueChange={([val]) =>
                                                                            setAgent({
                                                                                ...agent,
                                                                                runtime_config: {
                                                                                    ...agent.runtime_config,
                                                                                    hangup_on_silence_seconds: val,
                                                                                },
                                                                            })
                                                                        }
                                                                        min={5}
                                                                        max={60}
                                                                        step={1}
                                                                        className="flex-1"
                                                                    />
                                                                    <span className="rounded-md border border-border px-2 py-0.5 text-sm font-medium tabular-nums">
                                                                        {agent.runtime_config?.hangup_on_silence_seconds ?? 10}
                                                                    </span>
                                                                    <span className="text-xs text-muted-foreground">s</span>
                                                                </div>
                                                            )}
                                                        </div>
                                                        <div className="space-y-3">
                                                            <div className="flex items-center gap-2">
                                                                <Timer className="size-4 text-muted-foreground" />
                                                                <Label>Total Call Timeout</Label>
                                                            </div>
                                                            <div className="flex items-center gap-3">
                                                                <Slider
                                                                    value={[agent.runtime_config?.total_call_timeout ?? 300]}
                                                                    onValueChange={([val]) =>
                                                                        setAgent({
                                                                            ...agent,
                                                                            runtime_config: {
                                                                                ...agent.runtime_config,
                                                                                total_call_timeout: val,
                                                                            },
                                                                        })
                                                                    }
                                                                    min={30}
                                                                    max={3600}
                                                                    step={30}
                                                                    className="flex-1"
                                                                />
                                                                <span className="rounded-md border border-border px-2 py-0.5 text-sm font-medium tabular-nums">
                                                                    {agent.runtime_config?.total_call_timeout ?? 300}
                                                                </span>
                                                                <span className="text-xs text-muted-foreground">s</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Post Call Tasks */}
                                                <div className="rounded-xl border border-border bg-card">
                                                    <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                                                        <BarChart3 className="size-4 text-muted-foreground" />
                                                        <h3 className="font-medium">Post Call Tasks</h3>
                                                    </div>
                                                    <div className="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2">
                                                        <div className="space-y-2">
                                                            <div className="flex items-center gap-2">
                                                                <Label>Push all execution data to webhook</Label>
                                                                <a href="#" className="inline-flex items-center gap-1 text-xs text-primary hover:underline">
                                                                    See all events
                                                                    <ExternalLink className="size-3" />
                                                                </a>
                                                            </div>
                                                            <Input
                                                                placeholder="Your webhook URL"
                                                                value={agent.runtime_config?.webhook_url || ''}
                                                                onChange={(e) =>
                                                                    setAgent({
                                                                        ...agent,
                                                                        runtime_config: {
                                                                            ...agent.runtime_config,
                                                                            webhook_url: e.target.value,
                                                                        },
                                                                    })
                                                                }
                                                            />
                                                        </div>
                                                        <div className="space-y-2">
                                                            <Label>Summarization</Label>
                                                            <p className="text-xs text-muted-foreground">
                                                                Generate a summary of the conversation automatically.
                                                            </p>
                                                            <Switch
                                                                checked={agent.runtime_config?.summarization ?? false}
                                                                onCheckedChange={(val) =>
                                                                    setAgent({
                                                                        ...agent,
                                                                        runtime_config: {
                                                                            ...agent.runtime_config,
                                                                            summarization: val,
                                                                        },
                                                                    })
                                                                }
                                                            />
                                                        </div>
                                                    </div>
                                                </div>
                                            </TabsContent>

                                            {/* Tools Tab */}
                                            <TabsContent value="tools" className="space-y-6">
                                                <div className="rounded-xl border border-border bg-card">
                                                    <div className="flex items-center justify-between border-b border-border px-4 py-3">
                                                        <div className="flex items-center gap-2">
                                                            <Wrench className="size-4 text-muted-foreground" />
                                                            <h3 className="font-medium">Function Tools for LLM Models</h3>
                                                            <a href="#" className="inline-flex items-center gap-1 text-xs text-primary hover:underline">
                                                                View Docs
                                                                <ExternalLink className="size-3" />
                                                            </a>
                                                        </div>
                                                    </div>
                                                    <div className="p-4">
                                                        <p className="mb-4 text-sm font-medium">Add Tool</p>
                                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                            {[
                                                                { id: 'calendar', name: 'Calendar Availability', description: 'Check open meeting slots', icon: Calendar },
                                                                { id: 'book', name: 'Book Appointment', description: 'Create calendar booking', icon: Calendar },
                                                                { id: 'transfer', name: 'Transfer Call', description: 'Route call to human', icon: PhoneForwarded },
                                                                { id: 'custom', name: 'Custom Function', description: 'Connect any API endpoint', icon: Webhook },
                                                            ].map((tool) => (
                                                                <div
                                                                    key={tool.id}
                                                                    className="flex items-center justify-between rounded-lg border border-border p-3"
                                                                >
                                                                    <div className="flex items-center gap-3">
                                                                        <tool.icon className="size-4 text-muted-foreground" />
                                                                        <div>
                                                                            <p className="text-sm font-medium">{tool.name}</p>
                                                                            <p className="text-xs text-muted-foreground">{tool.description}</p>
                                                                        </div>
                                                                    </div>
                                                                    <Button variant="outline" size="sm" className="gap-1.5 shrink-0">
                                                                        <Plus className="size-3.5" />
                                                                        Add
                                                                    </Button>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Extractions */}
                                                <div className="rounded-xl border border-border bg-card">
                                                    <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                                                        <ListFilter className="size-4 text-muted-foreground" />
                                                        <h3 className="font-medium">Extractions</h3>
                                                        <Badge variant="secondary" className="text-[10px]">New</Badge>
                                                    </div>
                                                    <div className="p-4">
                                                        <div className="flex items-center gap-2 mb-4">
                                                            <Button variant="outline" size="sm" className="gap-1.5">
                                                                <Pen className="size-3.5" />
                                                                Test Extractions
                                                            </Button>
                                                        </div>
                                                        <div className="rounded-lg border border-dashed border-border p-8 text-center">
                                                            <ListFilter className="mx-auto size-8 text-muted-foreground" />
                                                            <p className="mt-3 text-sm font-medium text-muted-foreground">
                                                                No categories or extractions yet
                                                            </p>
                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                Create a category to organize your extractions
                                                            </p>
                                                            <Button variant="outline" className="mt-4 gap-2">
                                                                <Plus className="size-4" />
                                                                Create First Category
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </TabsContent>

                                            {/* Analytics Tab */}
                                            <TabsContent value="analytics" className="space-y-6">
                                                <div className="rounded-xl border border-border bg-card">
                                                    <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                                                        <BarChart3 className="size-4 text-muted-foreground" />
                                                        <h3 className="font-medium">Analytics</h3>
                                                    </div>
                                                    <div className="rounded-lg border border-dashed border-border m-4 p-8 text-center">
                                                        <BarChart3 className="mx-auto size-10 text-muted-foreground" />
                                                        <h3 className="mt-4 text-base font-semibold text-muted-foreground">
                                                            No data yet
                                                        </h3>
                                                        <p className="mt-2 text-sm text-muted-foreground">
                                                            Analytics will appear here once the agent starts receiving calls.
                                                        </p>
                                                    </div>
                                                </div>
                                            </TabsContent>

                                            {/* Inbound Tab */}
                                            <TabsContent value="inbound" className="space-y-6">
                                                <div className="rounded-xl border border-border bg-card">
                                                    <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                                                        <PhoneIncoming className="size-4 text-muted-foreground" />
                                                        <h3 className="font-medium">Inbound Configuration</h3>
                                                    </div>
                                                    <div className="rounded-lg border border-dashed border-border m-4 p-8 text-center">
                                                        <PhoneIncoming className="mx-auto size-10 text-muted-foreground" />
                                                        <h3 className="mt-4 text-base font-semibold text-muted-foreground">
                                                            No phone numbers configured
                                                        </h3>
                                                        <p className="mt-2 text-sm text-muted-foreground">
                                                            Purchase or connect phone numbers to receive inbound calls.
                                                        </p>
                                                        <Button variant="outline" className="mt-4 gap-2">
                                                            <Plus className="size-4" />
                                                            Add Phone Number
                                                        </Button>
                                                    </div>
                                                </div>
                                            </TabsContent>
                                        </Tabs>

                                        {/* Mobile: Save bar fijo abajo */}
                                        <div className="fixed inset-x-0 bottom-0 z-40 flex items-center gap-2 border-t border-border bg-card p-3 xl:hidden">
                                            <Button
                                                className="flex-1 gap-2"
                                                onClick={handleSave}
                                                disabled={isSaving}
                                            >
                                                <Save className="size-4" />
                                                {isSaving ? 'Saving...' : 'Save agent'}
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="icon"
                                                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                onClick={handleDelete}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                        {/* Spacer para que el contenido no quede detrás del bar fijo */}
                                        <div className="h-16 xl:hidden" />
                                    </>
                                )}
                            </div>
                        </main>

                        {/* Right Sidebar - Desktop */}
                        {agent && (
                            <aside className="hidden w-72 shrink-0 overflow-y-auto border-l border-border bg-card p-4 xl:block">
                                {rightSidebarContent}
                            </aside>
                        )}

                        {/* Right Sidebar - Mobile Sheet */}
                        <Sheet open={rightSheetOpen} onOpenChange={setRightSheetOpen}>
                            <SheetContent side="right" className="w-80 overflow-y-auto p-4">
                                <SheetHeader>
                                    <SheetTitle>Actions</SheetTitle>
                                </SheetHeader>
                                {rightSidebarContent}
                            </SheetContent>
                        </Sheet>
                    </div>

                    {/* Test Call Dialog */}
                    {agent && (
                        <Dialog open={testCallOpen} onOpenChange={setTestCallOpen}>
                            <DialogContent className="sm:max-w-lg">
                                <DialogHeader>
                                    <DialogTitle>Test via browser</DialogTitle>
                                    <DialogDescription>
                                        Start a live WebRTC session with your agent directly from the browser.
                                    </DialogDescription>
                                </DialogHeader>
                                <AgentTestCall
                                    tenantSlug={tenant.slug}
                                    agentId={agent.id}
                                    agentName={agent.name}
                                />
                            </DialogContent>
                        </Dialog>
                    )}

                    {/* New Agent Dialog */}
                    <Dialog open={newAgentOpen} onOpenChange={setNewAgentOpen}>
                        <DialogContent className="flex max-h-[90vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-2xl">
                            <DialogHeader className="border-b border-border px-6 py-4">
                                <DialogTitle className="text-lg">Select your use case and let AI build your agent</DialogTitle>
                                <DialogDescription>
                                    You can always modify &amp; edit it later.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="flex-1 overflow-y-auto px-6 py-5">
                                {/* Mode selector */}
                                <div className="grid grid-cols-2 gap-3">
                                    <button
                                        type="button"
                                        onClick={() => setNewAgentMode('auto')}
                                        className={cn(
                                            'flex flex-col items-center gap-2 rounded-xl border-2 p-5 text-sm font-medium transition-all',
                                            newAgentMode === 'auto'
                                                ? 'border-primary bg-primary/5 shadow-sm'
                                                : 'border-border hover:border-muted-foreground/30 hover:bg-muted/50',
                                        )}
                                    >
                                        <Settings className="size-6 text-muted-foreground" />
                                        Auto Build Agent
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setNewAgentMode('prebuilt')}
                                        className={cn(
                                            'flex flex-col items-center gap-2 rounded-xl border-2 p-5 text-sm font-medium transition-all',
                                            newAgentMode === 'prebuilt'
                                                ? 'border-primary bg-primary/5 shadow-sm'
                                                : 'border-border hover:border-muted-foreground/30 hover:bg-muted/50',
                                        )}
                                    >
                                        <LayoutGrid className="size-6 text-muted-foreground" />
                                        Pre built Agents
                                    </button>
                                </div>

                                {newAgentMode === 'auto' ? (
                                    <div className="mt-5 space-y-5">
                                        <p className="text-sm text-muted-foreground">
                                            Tell us about your ideal agent and we&apos;ll help you build it step by step.
                                        </p>

                                        <div className="space-y-2">
                                            <Label>Name of Agent <span className="text-destructive">*</span></Label>
                                            <Input
                                                placeholder="Enter agent name"
                                                value={newAgentForm.name}
                                                onChange={(e) =>
                                                    setNewAgentForm((prev) => ({ ...prev, name: e.target.value }))
                                                }
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label>Languages <span className="text-destructive">*</span></Label>
                                            <div className="flex flex-wrap gap-4">
                                                {['en', 'es', 'pt', 'fr', 'de', 'hi'].map((lang) => (
                                                    <label key={lang} className="flex items-center gap-2 text-sm cursor-pointer">
                                                        <Checkbox
                                                            checked={newAgentForm.languages.includes(lang)}
                                                            onCheckedChange={() => toggleLanguage(lang)}
                                                        />
                                                        {{ en: 'English', es: 'Spanish', pt: 'Portuguese', fr: 'French', de: 'German', hi: 'Hindi' }[lang]}
                                                    </label>
                                                ))}
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <Label>What do you want to achieve in this call? <span className="text-destructive">*</span></Label>
                                            <Textarea
                                                placeholder="Be descriptive as you would to a human who you are asking to lead the call..."
                                                className="min-h-[100px]"
                                                value={newAgentForm.objective}
                                                onChange={(e) =>
                                                    setNewAgentForm((prev) => ({ ...prev, objective: e.target.value }))
                                                }
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label>Ideal Next Steps after this call <span className="text-destructive">*</span></Label>
                                            <Textarea
                                                placeholder="Describe what should happen after the call is completed..."
                                                className="min-h-[80px]"
                                                value={newAgentForm.next_steps}
                                                onChange={(e) =>
                                                    setNewAgentForm((prev) => ({ ...prev, next_steps: e.target.value }))
                                                }
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label>FAQs / Business Documents / Any information</Label>
                                            <Textarea
                                                placeholder="Paste any context, FAQs, or documents the agent should know about..."
                                                className="min-h-[80px]"
                                                value={newAgentForm.faqs}
                                                onChange={(e) =>
                                                    setNewAgentForm((prev) => ({ ...prev, faqs: e.target.value }))
                                                }
                                            />
                                        </div>
                                    </div>
                                ) : (
                                    <div className="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        {agentTemplates.map((tpl) => (
                                            <button
                                                key={tpl.id}
                                                type="button"
                                                onClick={() => setSelectedTemplate(tpl.id)}
                                                className={cn(
                                                    'flex items-start gap-3 rounded-xl border-2 p-4 text-left transition-all',
                                                    selectedTemplate === tpl.id
                                                        ? 'border-primary bg-primary/5 shadow-sm'
                                                        : 'border-border hover:border-muted-foreground/30 hover:bg-muted/50',
                                                )}
                                            >
                                                <User className="mt-0.5 size-5 shrink-0 text-muted-foreground" />
                                                <div>
                                                    <p className="text-sm font-medium">{tpl.name}</p>
                                                    <p className="mt-0.5 text-xs leading-relaxed text-muted-foreground">
                                                        {tpl.description}
                                                    </p>
                                                </div>
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* Footer */}
                            <div className="border-t border-border bg-muted/30 px-6 py-4">
                                <div className="flex items-center justify-end gap-3">
                                    <Button
                                        variant="outline"
                                        onClick={() => setNewAgentOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        onClick={handleCreateAgent}
                                        disabled={
                                            isCreating ||
                                            (newAgentMode === 'auto'
                                                ? !newAgentForm.name.trim() || newAgentForm.languages.length === 0 || !newAgentForm.objective.trim()
                                                : !selectedTemplate)
                                        }
                                        className="gap-2"
                                    >
                                        <Sparkles className="size-4" />
                                        {isCreating ? 'Generating...' : 'Generate Agent'}
                                    </Button>
                                </div>

                                <div className="relative mt-4">
                                    <div className="absolute inset-0 flex items-center">
                                        <Separator />
                                    </div>
                                    <div className="relative flex justify-center">
                                        <span className="bg-muted/30 px-3 text-xs text-muted-foreground">OR</span>
                                    </div>
                                </div>

                                <Button
                                    variant="outline"
                                    className="mt-4 w-full"
                                    onClick={handleCreateFromScratch}
                                    disabled={isCreating}
                                >
                                    {isCreating ? 'Creating...' : 'I want to create an agent from scratch'}
                                </Button>
                            </div>
                        </DialogContent>
                    </Dialog>
                </div>
            </TooltipProvider>
        </AppLayout>
    );
}
