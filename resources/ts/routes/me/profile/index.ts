import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::update
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:37
* @route 'https://app.tito.ai/me/profile'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: 'https://app.tito.ai/me/profile',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::update
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:37
* @route 'https://app.tito.ai/me/profile'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::update
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:37
* @route 'https://app.tito.ai/me/profile'
*/
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

const profile = {
    update: Object.assign(update, update),
}

export default profile