import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
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

const password = {
    update: Object.assign(update, update),
}

export default password