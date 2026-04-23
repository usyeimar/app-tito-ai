import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { ChevronsUpDown, Building2, Plus } from 'lucide-react';
import { Link, usePage } from '@inertiajs/react';
import { useIsMobile } from '@/hooks/use-mobile';
import { workspaces as workspacesRoute } from '@/routes';

export function TenantSwitcher() {
    const page = usePage<{ tenant?: any; workspaces?: any[] }>();
    const tenant = page.props.tenant;
    const workspaces = page.props.workspaces || [];
    const { state } = useSidebar();
    const isMobile = useIsMobile();

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger
                        render={
                            <SidebarMenuButton
                                size="lg"
                                className="group data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                            />
                        }
                    >
                        <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                            {tenant ? (
                                <span className="text-sm font-semibold">{tenant.name.charAt(0).toUpperCase()}</span>
                            ) : (
                                <Building2 className="size-4" />
                            )}
                        </div>
                        <div className="grid flex-1 text-left text-sm leading-tight">
                            <span className="truncate font-semibold capitalize">
                                {tenant ? tenant.name : 'Central Hub'}
                            </span>
                            <span className="truncate text-xs">
                                {tenant ? 'Workspace' : 'Management'}
                            </span>
                        </div>
                        <ChevronsUpDown className="ml-auto size-4" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        align="start"
                        side={isMobile ? 'bottom' : 'right'}
                        sideOffset={4}
                    >
                        <DropdownMenuGroup>
                            <DropdownMenuLabel className="text-xs text-muted-foreground">
                                Workspaces
                            </DropdownMenuLabel>
                            {workspaces.map((ws) => (
                                <DropdownMenuItem key={ws.id} render={<a href={`/workspaces/${ws.slug}/change`} />}>
                                    <div className="flex w-full items-center gap-2 p-2">
                                        <div className="flex size-6 items-center justify-center rounded-sm border">
                                            <Building2 className="size-4 shrink-0" />
                                        </div>
                                        <span className="capitalize">{ws.name}</span>
                                    </div>
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuGroup>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem render={<a href={workspacesRoute().url} />}>
                            <div className="flex w-full items-center gap-2 p-2">
                                <div className="flex size-6 items-center justify-center rounded-md border bg-background">
                                    <Plus className="size-4" />
                                </div>
                                <div className="font-medium text-muted-foreground">Add Workspace</div>
                            </div>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
