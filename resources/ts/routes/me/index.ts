import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
import profile937a89 from './profile'
import profilePicture from './profile-picture'
import password from './password'
/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::profile
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:21
* @route 'https://app.tito.ai/me/profile'
*/
export const profile = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: profile.url(options),
    method: 'get',
})

profile.definition = {
    methods: ["get","head"],
    url: 'https://app.tito.ai/me/profile',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::profile
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:21
* @route 'https://app.tito.ai/me/profile'
*/
profile.url = (options?: RouteQueryOptions) => {
    return profile.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::profile
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:21
* @route 'https://app.tito.ai/me/profile'
*/
profile.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: profile.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Central\Web\Me\ProfileController::profile
* @see app/Http/Controllers/Central/Web/Me/ProfileController.php:21
* @route 'https://app.tito.ai/me/profile'
*/
profile.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: profile.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Central\Web\Me\SecurityController::security
* @see app/Http/Controllers/Central/Web/Me/SecurityController.php:16
* @route 'https://app.tito.ai/me/security'
*/
export const security = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: security.url(options),
    method: 'get',
})

security.definition = {
    methods: ["get","head"],
    url: 'https://app.tito.ai/me/security',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Central\Web\Me\SecurityController::security
* @see app/Http/Controllers/Central/Web/Me/SecurityController.php:16
* @route 'https://app.tito.ai/me/security'
*/
security.url = (options?: RouteQueryOptions) => {
    return security.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Central\Web\Me\SecurityController::security
* @see app/Http/Controllers/Central/Web/Me/SecurityController.php:16
* @route 'https://app.tito.ai/me/security'
*/
security.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: security.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Central\Web\Me\SecurityController::security
* @see app/Http/Controllers/Central/Web/Me/SecurityController.php:16
* @route 'https://app.tito.ai/me/security'
*/
security.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: security.url(options),
    method: 'head',
})

const me = {
    profile: Object.assign(profile, profile937a89),
    profilePicture: Object.assign(profilePicture, profilePicture),
    security: Object.assign(security, security),
    password: Object.assign(password, password),
}

export default me