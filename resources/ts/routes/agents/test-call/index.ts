import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentTestCallController::start
* @see app/Http/Controllers/Tenant/API/Agent/AgentTestCallController.php:22
* @route 'https://app.tito.ai/{tenant}/api/ai/agents/{agent}/test-call'
*/
export const start = (args: { tenant: string | number, agent: string | number | { id: string | number } } | [tenant: string | number, agent: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: start.url(args, options),
    method: 'post',
})

start.definition = {
    methods: ["post"],
    url: 'https://app.tito.ai/{tenant}/api/ai/agents/{agent}/test-call',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentTestCallController::start
* @see app/Http/Controllers/Tenant/API/Agent/AgentTestCallController.php:22
* @route 'https://app.tito.ai/{tenant}/api/ai/agents/{agent}/test-call'
*/
start.url = (args: { tenant: string | number, agent: string | number | { id: string | number } } | [tenant: string | number, agent: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
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

    return start.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentTestCallController::start
* @see app/Http/Controllers/Tenant/API/Agent/AgentTestCallController.php:22
* @route 'https://app.tito.ai/{tenant}/api/ai/agents/{agent}/test-call'
*/
start.post = (args: { tenant: string | number, agent: string | number | { id: string | number } } | [tenant: string | number, agent: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: start.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentTestCallController::stop
* @see app/Http/Controllers/Tenant/API/Agent/AgentTestCallController.php:45
* @route 'https://app.tito.ai/{tenant}/api/ai/agents/{agent}/test-call/{session}'
*/
export const stop = (args: { tenant: string | number, agent: string | number | { id: string | number }, session: string | number } | [tenant: string | number, agent: string | number | { id: string | number }, session: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: stop.url(args, options),
    method: 'delete',
})

stop.definition = {
    methods: ["delete"],
    url: 'https://app.tito.ai/{tenant}/api/ai/agents/{agent}/test-call/{session}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentTestCallController::stop
* @see app/Http/Controllers/Tenant/API/Agent/AgentTestCallController.php:45
* @route 'https://app.tito.ai/{tenant}/api/ai/agents/{agent}/test-call/{session}'
*/
stop.url = (args: { tenant: string | number, agent: string | number | { id: string | number }, session: string | number } | [tenant: string | number, agent: string | number | { id: string | number }, session: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
            agent: args[1],
            session: args[2],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: args.tenant,
        agent: typeof args.agent === 'object'
        ? args.agent.id
        : args.agent,
        session: args.session,
    }

    return stop.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{session}', parsedArgs.session.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\API\Agent\AgentTestCallController::stop
* @see app/Http/Controllers/Tenant/API/Agent/AgentTestCallController.php:45
* @route 'https://app.tito.ai/{tenant}/api/ai/agents/{agent}/test-call/{session}'
*/
stop.delete = (args: { tenant: string | number, agent: string | number | { id: string | number }, session: string | number } | [tenant: string | number, agent: string | number | { id: string | number }, session: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: stop.url(args, options),
    method: 'delete',
})

const testCall = {
    start: Object.assign(start, start),
    stop: Object.assign(stop, stop),
}

export default testCall