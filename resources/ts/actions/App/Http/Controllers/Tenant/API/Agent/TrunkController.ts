import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\API\Agent\TrunkController::index
* @see app/Http/Controllers/Tenant/API/Agent/TrunkController.php:25
* @route 'https://app.tito.ai/{tenant}/api/ai/trunks'
*/
export const index = (args: { tenant: string | number } | [tenant: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: 'https://app.tito.ai/{tenant}/api/ai/trunks',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\API\Agent\TrunkController::index
* @see app/Http/Controllers/Tenant/API/Agent/TrunkController.php:25
* @route 'https://app.tito.ai/{tenant}/api/ai/trunks'
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
* @see \App\Http\Controllers\Tenant\API\Agent\TrunkController::index
* @see app/Http/Controllers/Tenant/API/Agent/TrunkController.php:25
* @route 'https://app.tito.ai/{tenant}/api/ai/trunks'
*/
index.get = (args: { tenant: string | number } | [tenant: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\API\Agent\TrunkController::index
* @see app/Http/Controllers/Tenant/API/Agent/TrunkController.php:25
* @route 'https://app.tito.ai/{tenant}/api/ai/trunks'
*/
index.head = (args: { tenant: string | number } | [tenant: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\API\Agent\TrunkController::store
* @see app/Http/Controllers/Tenant/API/Agent/TrunkController.php:37
* @route 'https://app.tito.ai/{tenant}/api/ai/trunks'
*/
export const store = (args: { tenant: string | number } | [tenant: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: 'https://app.tito.ai/{tenant}/api/ai/trunks',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\API\Agent\TrunkController::store
* @see app/Http/Controllers/Tenant/API/Agent/TrunkController.php:37
* @route 'https://app.tito.ai/{tenant}/api/ai/trunks'
*/
store.url = (args: { tenant: string | number } | [tenant: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\API\Agent\TrunkController::store
* @see app/Http/Controllers/Tenant/API/Agent/TrunkController.php:37
* @route 'https://app.tito.ai/{tenant}/api/ai/trunks'
*/
store.post = (args: { tenant: string | number } | [tenant: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\API\Agent\TrunkController::show
* @see app/Http/Controllers/Tenant/API/Agent/TrunkController.php:50
* @route 'https://app.tito.ai/{tenant}/api/ai/trunks/{trunk}'
*/
export const show = (args: { tenant: string | number, trunk: string | number | { id: string | number } } | [tenant: string | number, trunk: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: 'https://app.tito.ai/{tenant}/api/ai/trunks/{trunk}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\API\Agent\TrunkController::show
* @see app/Http/Controllers/Tenant/API/Agent/TrunkController.php:50
* @route 'https://app.tito.ai/{tenant}/api/ai/trunks/{trunk}'
*/
show.url = (args: { tenant: string | number, trunk: string | number | { id: string | number } } | [tenant: string | number, trunk: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
            trunk: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: args.tenant,
        trunk: typeof args.trunk === 'object'
        ? args.trunk.id
        : args.trunk,
    }

    return show.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{trunk}', parsedArgs.trunk.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\API\Agent\TrunkController::show
* @see app/Http/Controllers/Tenant/API/Agent/TrunkController.php:50
* @route 'https://app.tito.ai/{tenant}/api/ai/trunks/{trunk}'
*/
show.get = (args: { tenant: string | number, trunk: string | number | { id: string | number } } | [tenant: string | number, trunk: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\API\Agent\TrunkController::show
* @see app/Http/Controllers/Tenant/API/Agent/TrunkController.php:50
* @route 'https://app.tito.ai/{tenant}/api/ai/trunks/{trunk}'
*/
show.head = (args: { tenant: string | number, trunk: string | number | { id: string | number } } | [tenant: string | number, trunk: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\API\Agent\TrunkController::update
* @see app/Http/Controllers/Tenant/API/Agent/TrunkController.php:62
* @route 'https://app.tito.ai/{tenant}/api/ai/trunks/{trunk}'
*/
export const update = (args: { tenant: string | number, trunk: string | number | { id: string | number } } | [tenant: string | number, trunk: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: 'https://app.tito.ai/{tenant}/api/ai/trunks/{trunk}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Tenant\API\Agent\TrunkController::update
* @see app/Http/Controllers/Tenant/API/Agent/TrunkController.php:62
* @route 'https://app.tito.ai/{tenant}/api/ai/trunks/{trunk}'
*/
update.url = (args: { tenant: string | number, trunk: string | number | { id: string | number } } | [tenant: string | number, trunk: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
            trunk: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: args.tenant,
        trunk: typeof args.trunk === 'object'
        ? args.trunk.id
        : args.trunk,
    }

    return update.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{trunk}', parsedArgs.trunk.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\API\Agent\TrunkController::update
* @see app/Http/Controllers/Tenant/API/Agent/TrunkController.php:62
* @route 'https://app.tito.ai/{tenant}/api/ai/trunks/{trunk}'
*/
update.patch = (args: { tenant: string | number, trunk: string | number | { id: string | number } } | [tenant: string | number, trunk: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Tenant\API\Agent\TrunkController::destroy
* @see app/Http/Controllers/Tenant/API/Agent/TrunkController.php:75
* @route 'https://app.tito.ai/{tenant}/api/ai/trunks/{trunk}'
*/
export const destroy = (args: { tenant: string | number, trunk: string | number | { id: string | number } } | [tenant: string | number, trunk: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: 'https://app.tito.ai/{tenant}/api/ai/trunks/{trunk}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Tenant\API\Agent\TrunkController::destroy
* @see app/Http/Controllers/Tenant/API/Agent/TrunkController.php:75
* @route 'https://app.tito.ai/{tenant}/api/ai/trunks/{trunk}'
*/
destroy.url = (args: { tenant: string | number, trunk: string | number | { id: string | number } } | [tenant: string | number, trunk: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
            trunk: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: args.tenant,
        trunk: typeof args.trunk === 'object'
        ? args.trunk.id
        : args.trunk,
    }

    return destroy.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{trunk}', parsedArgs.trunk.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\API\Agent\TrunkController::destroy
* @see app/Http/Controllers/Tenant/API/Agent/TrunkController.php:75
* @route 'https://app.tito.ai/{tenant}/api/ai/trunks/{trunk}'
*/
destroy.delete = (args: { tenant: string | number, trunk: string | number | { id: string | number } } | [tenant: string | number, trunk: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const TrunkController = { index, store, show, update, destroy }

export default TrunkController