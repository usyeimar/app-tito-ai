import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentConfigController::getConfigById
* @see app/Http/Controllers/Tenant/API/Agent/AgentConfigController.php:32
* @route 'https://app.tito.ai/{tenant}/api/agents/{agentId}/config'
*/
export const getConfigById = (args: { tenant: string | number, agentId: string | number } | [tenant: string | number, agentId: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: getConfigById.url(args, options),
    method: 'get',
})

getConfigById.definition = {
    methods: ["get","head"],
    url: 'https://app.tito.ai/{tenant}/api/agents/{agentId}/config',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentConfigController::getConfigById
* @see app/Http/Controllers/Tenant/API/Agent/AgentConfigController.php:32
* @route 'https://app.tito.ai/{tenant}/api/agents/{agentId}/config'
*/
getConfigById.url = (args: { tenant: string | number, agentId: string | number } | [tenant: string | number, agentId: string | number ], options?: RouteQueryOptions) => {
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

    return getConfigById.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{agentId}', parsedArgs.agentId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentConfigController::getConfigById
* @see app/Http/Controllers/Tenant/API/Agent/AgentConfigController.php:32
* @route 'https://app.tito.ai/{tenant}/api/agents/{agentId}/config'
*/
getConfigById.get = (args: { tenant: string | number, agentId: string | number } | [tenant: string | number, agentId: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: getConfigById.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentConfigController::getConfigById
* @see app/Http/Controllers/Tenant/API/Agent/AgentConfigController.php:32
* @route 'https://app.tito.ai/{tenant}/api/agents/{agentId}/config'
*/
getConfigById.head = (args: { tenant: string | number, agentId: string | number } | [tenant: string | number, agentId: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: getConfigById.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentConfigController::getConfigBySlug
* @see app/Http/Controllers/Tenant/API/Agent/AgentConfigController.php:67
* @route 'https://app.tito.ai/{tenant}/api/agents/by-slug/{agentSlug}/config'
*/
export const getConfigBySlug = (args: { tenant: string | number, agentSlug: string | number } | [tenant: string | number, agentSlug: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: getConfigBySlug.url(args, options),
    method: 'get',
})

getConfigBySlug.definition = {
    methods: ["get","head"],
    url: 'https://app.tito.ai/{tenant}/api/agents/by-slug/{agentSlug}/config',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentConfigController::getConfigBySlug
* @see app/Http/Controllers/Tenant/API/Agent/AgentConfigController.php:67
* @route 'https://app.tito.ai/{tenant}/api/agents/by-slug/{agentSlug}/config'
*/
getConfigBySlug.url = (args: { tenant: string | number, agentSlug: string | number } | [tenant: string | number, agentSlug: string | number ], options?: RouteQueryOptions) => {
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

    return getConfigBySlug.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{agentSlug}', parsedArgs.agentSlug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentConfigController::getConfigBySlug
* @see app/Http/Controllers/Tenant/API/Agent/AgentConfigController.php:67
* @route 'https://app.tito.ai/{tenant}/api/agents/by-slug/{agentSlug}/config'
*/
getConfigBySlug.get = (args: { tenant: string | number, agentSlug: string | number } | [tenant: string | number, agentSlug: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: getConfigBySlug.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentConfigController::getConfigBySlug
* @see app/Http/Controllers/Tenant/API/Agent/AgentConfigController.php:67
* @route 'https://app.tito.ai/{tenant}/api/agents/by-slug/{agentSlug}/config'
*/
getConfigBySlug.head = (args: { tenant: string | number, agentSlug: string | number } | [tenant: string | number, agentSlug: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: getConfigBySlug.url(args, options),
    method: 'head',
})

const AgentConfigController = { getConfigById, getConfigBySlug }

export default AgentConfigController