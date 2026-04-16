import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::show
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:21
* @route 'https://app.tito.ai/me/profile'
*/
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: 'https://app.tito.ai/me/profile',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::show
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:21
* @route 'https://app.tito.ai/me/profile'
*/
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::show
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:21
* @route 'https://app.tito.ai/me/profile'
*/
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::show
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:21
* @route 'https://app.tito.ai/me/profile'
*/
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

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

/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::updateProfilePicture
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:51
* @route 'https://app.tito.ai/me/profile-picture'
*/
export const updateProfilePicture = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updateProfilePicture.url(options),
    method: 'post',
})

updateProfilePicture.definition = {
    methods: ["post"],
    url: 'https://app.tito.ai/me/profile-picture',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::updateProfilePicture
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:51
* @route 'https://app.tito.ai/me/profile-picture'
*/
updateProfilePicture.url = (options?: RouteQueryOptions) => {
    return updateProfilePicture.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::updateProfilePicture
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:51
* @route 'https://app.tito.ai/me/profile-picture'
*/
updateProfilePicture.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updateProfilePicture.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::removeProfilePicture
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:65
* @route 'https://app.tito.ai/me/profile-picture'
*/
export const removeProfilePicture = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: removeProfilePicture.url(options),
    method: 'delete',
})

removeProfilePicture.definition = {
    methods: ["delete"],
    url: 'https://app.tito.ai/me/profile-picture',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::removeProfilePicture
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:65
* @route 'https://app.tito.ai/me/profile-picture'
*/
removeProfilePicture.url = (options?: RouteQueryOptions) => {
    return removeProfilePicture.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::removeProfilePicture
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:65
* @route 'https://app.tito.ai/me/profile-picture'
*/
removeProfilePicture.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: removeProfilePicture.url(options),
    method: 'delete',
})

const ProfileController = { show, update, updateProfilePicture, removeProfilePicture }

export default ProfileController