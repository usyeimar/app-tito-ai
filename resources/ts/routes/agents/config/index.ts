import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentConfigController::byId
* @see app/Http/Controllers/Tenant/API/Agent/AgentConfigController.php:32
* @route 'https://app.tito.ai/{tenant}/api/agents/{agentId}/config'
*/
export const byId = (args: { tenant: string | number, agentId: string | number } | [tenant: string | number, agentId: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: byId.url(args, options),
    method: 'get',
})

byId.definition = {
    methods: ["get","head"],
    url: 'https://app.tito.ai/{tenant}/api/agents/{agentId}/config',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentConfigController::byId
* @see app/Http/Controllers/Tenant/API/Agent/AgentConfigController.php:32
* @route 'https://app.tito.ai/{tenant}/api/agents/{agentId}/config'
*/
byId.url = (args: { tenant: string | number, agentId: string | number } | [tenant: string | number, agentId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
            agentId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: args.tenant,
        agentId: args.agentId,
    }

    return byId.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{agentId}', parsedArgs.agentId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentConfigController::byId
* @see app/Http/Controllers/Tenant/API/Agent/AgentConfigController.php:32
* @route 'https://app.tito.ai/{tenant}/api/agents/{agentId}/config'
*/
byId.get = (args: { tenant: string | number, agentId: string | number } | [tenant: string | number, agentId: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: byId.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentConfigController::byId
* @see app/Http/Controllers/Tenant/API/Agent/AgentConfigController.php:32
* @route 'https://app.tito.ai/{tenant}/api/agents/{agentId}/config'
*/
byId.head = (args: { tenant: string | number, agentId: string | number } | [tenant: string | number, agentId: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: byId.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentConfigController::bySlug
* @see app/Http/Controllers/Tenant/API/Agent/AgentConfigController.php:67
* @route 'https://app.tito.ai/{tenant}/api/agents/by-slug/{agentSlug}/config'
*/
export const bySlug = (args: { tenant: string | number, agentSlug: string | number } | [tenant: string | number, agentSlug: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: bySlug.url(args, options),
    method: 'get',
})

bySlug.definition = {
    methods: ["get","head"],
    url: 'https://app.tito.ai/{tenant}/api/agents/by-slug/{agentSlug}/config',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentConfigController::bySlug
* @see app/Http/Controllers/Tenant/API/Agent/AgentConfigController.php:67
* @route 'https://app.tito.ai/{tenant}/api/agents/by-slug/{agentSlug}/config'
*/
bySlug.url = (args: { tenant: string | number, agentSlug: string | number } | [tenant: string | number, agentSlug: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
            agentSlug: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: args.tenant,
        agentSlug: args.agentSlug,
    }

    return bySlug.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{agentSlug}', parsedArgs.agentSlug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentConfigController::bySlug
* @see app/Http/Controllers/Tenant/API/Agent/AgentConfigController.php:67
* @route 'https://app.tito.ai/{tenant}/api/agents/by-slug/{agentSlug}/config'
*/
bySlug.get = (args: { tenant: string | number, agentSlug: string | number } | [tenant: string | number, agentSlug: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: bySlug.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentConfigController::bySlug
* @see app/Http/Controllers/Tenant/API/Agent/AgentConfigController.php:67
* @route 'https://app.tito.ai/{tenant}/api/agents/by-slug/{agentSlug}/config'
*/
bySlug.head = (args: { tenant: string | number, agentSlug: string | number } | [tenant: string | number, agentSlug: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: bySlug.url(args, options),
    method: 'head',
})

const config = {
    byId: Object.assign(byId, byId),
    bySlug: Object.assign(bySlug, bySlug),
}

export default config