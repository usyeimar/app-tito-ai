import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::update
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:51
* @route 'https://app.tito.ai/me/profile-picture'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(options),
    method: 'post',
})

update.definition = {
    methods: ["post"],
    url: 'https://app.tito.ai/me/profile-picture',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::update
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:51
* @route 'https://app.tito.ai/me/profile-picture'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::update
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:51
* @route 'https://app.tito.ai/me/profile-picture'
*/
update.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::destroy
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:65
* @route 'https://app.tito.ai/me/profile-picture'
*/
export const destroy = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: 'https://app.tito.ai/me/profile-picture',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::destroy
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:65
* @route 'https://app.tito.ai/me/profile-picture'
*/
destroy.url = (options?: RouteQueryOptions) => {
    return destroy.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::destroy
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:65
* @route 'https://app.tito.ai/me/profile-picture'
*/
destroy.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

const profilePicture = {
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default profilePicture