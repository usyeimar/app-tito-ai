import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Central\Web\Tenancy\TenantController::store
* @see app/Http/Controllers/Central/Web/Tenancy/TenantController.php:49
* @route 'https://app.tito.ai/workspaces'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: 'https://app.tito.ai/workspaces',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Central\Web\Tenancy\TenantController::store
* @see app/Http/Controllers/Central/Web/Tenancy/TenantController.php:49
* @route 'https://app.tito.ai/workspaces'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Central\Web\Tenancy\TenantController::store
* @see app/Http/Controllers/Central/Web/Tenancy/TenantController.php:49
* @route 'https://app.tito.ai/workspaces'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Central\Web\Tenancy\TenantController::enter
* @see app/Http/Controllers/Central/Web/Tenancy/TenantController.php:59
* @route 'https://app.tito.ai/workspaces/{tenant}/enter'
*/
export const enter = (args: { tenant: string | { slug: string } } | [tenant: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: enter.url(args, options),
    method: 'get',
})

enter.definition = {
    methods: ["get","head"],
    url: 'https://app.tito.ai/workspaces/{tenant}/enter',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Central\Web\Tenancy\TenantController::enter
* @see app/Http/Controllers/Central/Web/Tenancy/TenantController.php:59
* @route 'https://app.tito.ai/workspaces/{tenant}/enter'
*/
enter.url = (args: { tenant: string | { slug: string } } | [tenant: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tenant: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'slug' in args) {
        args = { tenant: args.slug }
    }

    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.slug
        : args.tenant,
    }

    return enter.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Central\Web\Tenancy\TenantController::enter
* @see app/Http/Controllers/Central/Web/Tenancy/TenantController.php:59
* @route 'https://app.tito.ai/workspaces/{tenant}/enter'
*/
enter.get = (args: { tenant: string | { slug: string } } | [tenant: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: enter.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Central\Web\Tenancy\TenantController::enter
* @see app/Http/Controllers/Central/Web/Tenancy/TenantController.php:59
* @route 'https://app.tito.ai/workspaces/{tenant}/enter'
*/
enter.head = (args: { tenant: string | { slug: string } } | [tenant: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: enter.url(args, options),
    method: 'head',
})

const workspaces = {
    store: Object.assign(store, store),
    enter: Object.assign(enter, enter),
}

export default workspaces