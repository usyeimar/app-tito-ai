import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Central\Web\Me\SecurityController::show
* @see app/Http/Controllers/Central/Web/Me/SecurityController.php:16
* @route 'https://app.tito.ai/me/security'
*/
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: 'https://app.tito.ai/me/security',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Central\Web\Me\SecurityController::show
* @see app/Http/Controllers/Central/Web/Me/SecurityController.php:16
* @route 'https://app.tito.ai/me/security'
*/
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Central\Web\Me\SecurityController::show
* @see app/Http/Controllers/Central/Web/Me/SecurityController.php:16
* @route 'https://app.tito.ai/me/security'
*/
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Central\Web\Me\SecurityController::show
* @see app/Http/Controllers/Central/Web/Me/SecurityController.php:16
* @route 'https://app.tito.ai/me/security'
*/
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Central\Web\Me\SecurityController::update
* @see app/Http/Controllers/Central/Web/Me/SecurityController.php:37
* @route 'https://app.tito.ai/me/password'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: 'https://app.tito.ai/me/password',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Central\Web\Me\SecurityController::update
* @see app/Http/Controllers/Central/Web/Me/SecurityController.php:37
* @route 'https://app.tito.ai/me/password'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Central\Web\Me\SecurityController::update
* @see app/Http/Controllers/Central/Web/Me/SecurityController.php:37
* @route 'https://app.tito.ai/me/password'
*/
update.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

const SecurityController = { show, update }

export default SecurityController