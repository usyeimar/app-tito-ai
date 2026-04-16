import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\Web\Agent\AgentPageController::index
* @see app/Http/Controllers/Tenant/Web/Agent/AgentPageController.php:19
* @route 'https://app.tito.ai/{tenant}/agents'
*/
export const index = (args: { tenant: string | number } | [tenant: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: 'https://app.tito.ai/{tenant}/agents',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\Web\Agent\AgentPageController::index
* @see app/Http/Controllers/Tenant/Web/Agent/AgentPageController.php:19
* @route 'https://app.tito.ai/{tenant}/agents'
*/
index.url = (args: { tenant: string | number } | [tenant: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tenant: args }
    }

    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: args.tenant,
    }

    return index.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\Web\Agent\AgentPageController::index
* @see app/Http/Controllers/Tenant/Web/Agent/AgentPageController.php:19
* @route 'https://app.tito.ai/{tenant}/agents'
*/
index.get = (args: { tenant: string | number } | [tenant: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\Web\Agent\AgentPageController::index
* @see app/Http/Controllers/Tenant/Web/Agent/AgentPageController.php:19
* @route 'https://app.tito.ai/{tenant}/agents'
*/
index.head = (args: { tenant: string | number } | [tenant: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\Web\Agent\AgentPageController::show
* @see app/Http/Controllers/Tenant/Web/Agent/AgentPageController.php:32
* @route 'https://app.tito.ai/{tenant}/agents/{agent}'
*/
export const show = (args: { tenant: string | number, agent: string | number | { id: string | number } } | [tenant: string | number, agent: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: 'https://app.tito.ai/{tenant}/agents/{agent}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\Web\Agent\AgentPageController::show
* @see app/Http/Controllers/Tenant/Web/Agent/AgentPageController.php:32
* @route 'https://app.tito.ai/{tenant}/agents/{agent}'
*/
show.url = (args: { tenant: string | number, agent: string | number | { id: string | number } } | [tenant: string | number, agent: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
            agent: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: args.tenant,
        agent: typeof args.agent === 'object'
        ? args.agent.id
        : args.agent,
    }

    return show.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\Web\Agent\AgentPageController::show
* @see app/Http/Controllers/Tenant/Web/Agent/AgentPageController.php:32
* @route 'https://app.tito.ai/{tenant}/agents/{agent}'
*/
show.get = (args: { tenant: string | number, agent: string | number | { id: string | number } } | [tenant: string | number, agent: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\Web\Agent\AgentPageController::show
* @see app/Http/Controllers/Tenant/Web/Agent/AgentPageController.php:32
* @route 'https://app.tito.ai/{tenant}/agents/{agent}'
*/
show.head = (args: { tenant: string | number, agent: string | number | { id: string | number } } | [tenant: string | number, agent: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

const AgentPageController = { index, show }

export default AgentPageController